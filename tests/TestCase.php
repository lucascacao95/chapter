<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();

        // Stop before RefreshDatabase can migrate any persistent database.
        if (! $app->environment('testing')
            || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            throw new LogicException('Os testes exigem APP_ENV=testing e SQLite em memória. Nenhuma migration de teste foi executada.');
        }

        return $app;
    }
}
