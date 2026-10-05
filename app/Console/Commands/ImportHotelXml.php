<?php

namespace App\Console\Commands;

use App\Interfaces\Services\ReservationServiceInterface;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportHotelXml extends Command
{
    protected $signature = 'hotels:import {--path= : Pasta com hotels.xml, rooms.xml e reserves.xml}';

    protected $description = 'Importa XMLs em uma transação; rejeita o lote se houver dados inválidos';

    public function handle(ReservationServiceInterface $service): int
    {
        $path = $this->option('path') ?: database_path('xml');
        try {
            $hotels = $this->read($path.'/hotels.xml', 'Hotels');
            $rooms = $this->read($path.'/rooms.xml', 'Rooms');
            $reserves = $this->read($path.'/reserves.xml', 'Reserves');
            DB::transaction(function () use ($hotels, $rooms, $reserves, $service) {
                $seen = [];
                foreach ($hotels->Hotel as $node) {
                    $id = $this->id($node['id'], $seen, 'hotel');
                    $name = trim((string) $node->Name);
                    if ($name === '' || mb_strlen($name) > 255) {
                        throw new RuntimeException("Hotel $id: nome inválido.");
                    }
                    Hotel::updateOrCreate(['external_id' => $id], ['name' => $name]);
                }
                $seen = [];
                foreach ($rooms->Room as $node) {
                    $id = $this->id($node['id'], $seen, 'quarto');
                    $hotel = Hotel::where('external_id', (string) $node['hotelCode'])->firstOrFail();
                    $name = trim((string) $node->Name);
                    if ($name === '' || mb_strlen($name) > 255) {
                        throw new RuntimeException("Quarto $id: nome inválido.");
                    }
                    $room = Room::where('external_id', $id)->lockForUpdate()->first();
                    if ($room && $room->hotel_id != $hotel->id && $room->reservations()->exists()) {
                        throw new RuntimeException("Quarto $id possui reservas e não pode mudar de hotel.");
                    }
                    if ($room && $room->hotel_id != $hotel->id && $room->room_category_id !== null) {
                        throw new RuntimeException("Quarto $id possui categoria e não pode mudar de hotel pela importação.");
                    }
                    Room::updateOrCreate(['external_id' => $id], ['hotel_id' => $hotel->id, 'name' => $name]);
                }
                $seen = [];
                foreach ($reserves->Reserve as $node) {
                    $id = $this->id($node['id'], $seen, 'reserva');
                    $room = Room::where('external_id', (string) $node['roomCode'])->firstOrFail();
                    if ($room->hotel->external_id != (string) $node['hotelCode']) {
                        throw new RuntimeException("Reserva $id: quarto não pertence ao hotel informado.");
                    }
                    $data = ['room_id' => $room->id, 'check_in' => (string) $node->CheckIn, 'check_out' => (string) $node->CheckOut, 'guests' => [], 'dailies' => [], 'payments' => []];
                    foreach ($node->Guests->Guest ?? [] as $guest) {
                        $data['guests'][] = ['name' => (string) $guest->Name, 'last_name' => (string) $guest->LastName, 'phone' => (string) $guest->Phone];
                    }
                    foreach ($node->Dailies->Daily ?? [] as $daily) {
                        $data['dailies'][] = ['date' => (string) $daily->Date, 'value' => (string) $daily->Value];
                    }
                    foreach ($node->Payments->Payment ?? [] as $payment) {
                        $data['payments'][] = ['method' => (string) $payment->Method, 'value' => (string) $payment->Value];
                    }
                    try {
                        $reservation = $service->save($data, $id);
                        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $node->Total) || $service->cents((string) $node->Total) !== $service->cents($reservation->total)) {
                            throw new RuntimeException('Total difere da soma das diárias.');
                        }
                    } catch (ValidationException $exception) {
                        throw new RuntimeException("Reserva $id: ".implode(' ', $exception->validator->errors()->all()), 0, $exception);
                    } catch (\Throwable $exception) {
                        throw new RuntimeException("Reserva $id: ".$exception->getMessage(), 0, $exception);
                    }
                }
            });
            $this->info('Importação concluída. IDs externos existentes foram atualizados sem duplicação.');
            Log::info('Importação XML concluída', ['path' => $path]);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Lote revertido: '.$exception->getMessage());
            Log::error('Importação XML rejeitada', ['error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    private function read(string $file, string $root): \SimpleXMLElement
    {
        if (! is_file($file) || filesize($file) > 10 * 1024 * 1024) {
            throw new RuntimeException("Arquivo ausente ou maior que 10 MB: $file");
        }
        $content = file_get_contents($file);
        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new RuntimeException('DTD e entidades XML não são permitidas.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || $xml->getName() !== $root) {
                throw new RuntimeException("XML inválido: $file");
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function id(mixed $value, array &$seen, string $kind): int
    {
        $value = (string) $value;
        if (! ctype_digit($value) || (int) $value < 1 || isset($seen[(int) $value])) {
            throw new RuntimeException("ID inválido ou duplicado de $kind: $value");
        }
        $seen[(int) $value] = true;

        return (int) $value;
    }
}
