<?php

namespace Tests\MySql;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== 'mysql-concurrency' || config('database.connections.mysql.database') !== 'foco_concurrency' || config('database.connections.mysql.url') || ! $app->environment('testing')) {
            throw new \RuntimeException('Testes exigem MySQL isolado foco_concurrency em mysql-concurrency.');
        }

        return $app;
    }

    private function fixture(int $units = 1): array
    {
        $hotel = Hotel::create(['name' => 'Hotel concorrência']);
        $category = new RoomCategory(['name' => 'Standard']);
        $category->hotel_id = $hotel->id;
        $category->save();
        $rooms = [];
        for ($i = 0; $i < $units; $i++) {
            $rooms[] = Room::create(['hotel_id' => $hotel->id, 'room_category_id' => $category->id, 'name' => 'Unidade '.$i]);
        }
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => 'manager']);

        return [$category, $rooms, $user->createToken('integration')->plainTextToken];
    }

    private function payload(): array
    {
        return ['check_in' => '2028-08-10', 'check_out' => '2028-08-11',
            'guests' => [['name' => 'Teste', 'last_name' => 'Concorrência', 'phone' => '123']],
            'dailies' => [['date' => '2028-08-10', 'value' => '100.00']]];
    }

    private function race(string $url, array $payloads, string $token): array
    {
        $dir = sys_get_temp_dir().'/foco-race-'.bin2hex(random_bytes(8));
        mkdir($dir);
        $processes = [];
        try {
            foreach ($payloads as $i => $payload) {
                $process = new Process([PHP_BINARY, base_path('tests/MySql/worker.php')], base_path());
                $process->setTimeout(30);
                $process->setInput(json_encode(['url' => $url, 'payload' => $payload, 'token' => $token, 'ready' => $dir.'/'.$i, 'gate' => $dir.'/go'], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(glob($dir.'/*')) < count($payloads)) {
                if (microtime(true) > $deadline) {
                    throw new \RuntimeException('Workers não ficaram prontos no prazo.');
                }
                usleep(10000);
            }
            file_put_contents($dir.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertTrue($results[0]['lock_held']);
            $this->assertTrue($results[1]['lock_held']);

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    private function statuses(array $results): array
    {
        $statuses = array_column($results, 'status');
        sort($statuses);

        return $statuses;
    }

    public function test_simultaneous_direct_reservations_cannot_overlap(): void
    {
        [$category, $rooms, $token] = $this->fixture();
        $payload = $this->payload() + ['room_id' => $rooms[0]->id];
        $this->assertSame([201, 409], $this->statuses($this->race('/api/reservations', [$payload, $payload], $token)));
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('guests', 1);
    }

    public function test_category_last_unit_and_direct_reservation_share_inventory(): void
    {
        [$category, $rooms, $token] = $this->fixture();
        $categoryPayload = $this->payload() + ['room_category_id' => $category->id];
        $direct = $this->payload() + ['room_id' => $rooms[0]->id];
        $this->assertSame([201, 409], $this->statuses($this->race('/api/reservations', [$categoryPayload, $direct], $token)));
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_category_allocates_distinct_rooms_simultaneously(): void
    {
        [$category, $rooms, $token] = $this->fixture(2);
        $payload = $this->payload() + ['room_category_id' => $category->id];
        $results = $this->race('/api/reservations', [$payload, $payload], $token);
        $this->assertSame([201, 201], $this->statuses($results));
        $this->assertNotSame($results[0]['body']['room_id'], $results[1]['body']['room_id']);
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_simultaneous_payments_cannot_exceed_balance(): void
    {
        [$category, $rooms, $token] = $this->fixture();
        $reservation = Reservation::create(['room_id' => $rooms[0]->id, 'check_in' => '2028-08-10', 'check_out' => '2028-08-11', 'total' => '100.00']);
        $one = ['method' => 1, 'value' => '80.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'];
        $two = array_replace($one, ['idempotency_key' => '123e4567-e89b-42d3-a456-426614174001']);
        $this->assertSame([201, 422], $this->statuses($this->race('/api/reservations/'.$reservation->id.'/payments', [$one, $two], $token)));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('80.00', $reservation->payments()->firstOrFail()->value);
    }

    public function test_simultaneous_payment_retries_create_one_record(): void
    {
        [$category, $rooms, $token] = $this->fixture();
        $reservation = Reservation::create(['room_id' => $rooms[0]->id, 'check_in' => '2028-08-10', 'check_out' => '2028-08-11', 'total' => '100.00']);
        $payload = ['method' => 1, 'value' => '80.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'];
        $results = $this->race('/api/reservations/'.$reservation->id.'/payments', [$payload, $payload], $token);
        $this->assertSame([200, 201], $this->statuses($results));
        $this->assertSame($results[0]['body']['payment']['id'], $results[1]['body']['payment']['id']);
        $this->assertDatabaseCount('payments', 1);
    }
}
