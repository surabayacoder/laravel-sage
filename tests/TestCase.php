<?php

namespace Surabayacoder\Sage\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Surabayacoder\Sage\SageServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            SageServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('sage.api_key', 'testing');
        $app['config']->set('sage.vector_store.driver', 'file');
        $app['config']->set('sage.vector_store.drivers.file.path', 'test_vector_db.json');

        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root'   => __DIR__.'/temp',
        ]);
    }
}
