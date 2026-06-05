<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    private static bool $mongodbMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'mongodb') {
            $this->resetMongoDatabase();
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function resetMongoDatabase(): void
    {
        if (! self::$mongodbMigrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$mongodbMigrated = true;

            return;
        }

        $database = DB::connection('mongodb')->getDatabase();

        foreach ($database->listCollectionNames() as $collectionName) {
            if ($collectionName === config('database.migrations.table', 'migrations')) {
                continue;
            }

            $database->selectCollection($collectionName)->deleteMany([]);
        }
    }
}
