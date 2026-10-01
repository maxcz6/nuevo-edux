<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $testDb = env('DB_DATABASE', 'aula_virtual_testing');
        $app['config']->set('database.connections.pgsql.database', $testDb);
        return $app;
    }
}
