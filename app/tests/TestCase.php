<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Testes exigem SQLite em memória. Remova o cache de configuração antes de executar.');
        }
        config(['logging.channels.audit' => ['driver' => 'monolog', 'handler' => NullHandler::class], 'logging.default' => 'null']);

        return $app;
    }
}
