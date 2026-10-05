<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\NullHandler;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== 'mysql-concurrency' || config('database.connections.mysql.database') !== 'foco_concurrency' || config('database.connections.mysql.url') || ! $app->environment('testing')) {
    throw new RuntimeException('Worker exige o banco MySQL isolado de concorrência.');
}
config(['logging.channels.audit' => ['driver' => 'monolog', 'handler' => NullHandler::class]]);
$job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($job['gate'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Timeout na barreira de início.');
    }
    usleep(10000);
}
// Hold the first actual application lock briefly so concurrent requests overlap.
$held = false;
DB::listen(function ($query) use (&$held) {
    if (! $held && str_contains(strtolower($query->sql), 'for update')) {
        $held = true;
        usleep(500000);
    }
});
$request = Request::create($job['url'], 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$job['token'],
], json_encode($job['payload'], JSON_THROW_ON_ERROR));
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'lock_held' => $held], JSON_THROW_ON_ERROR);
