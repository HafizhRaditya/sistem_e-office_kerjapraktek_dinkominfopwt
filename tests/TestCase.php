<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Seed the isolated test database once after migrate:fresh. */
    protected bool $seed = true;

    /**
     * RefreshDatabase is destructive by design. Refuse to run unless PHPUnit
     * points at an explicitly named PostgreSQL test database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! app()->environment('testing') || $connection !== 'pgsql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Test database safety check failed: expected a PostgreSQL database ending in '_test', got '{$connection}:{$database}'."
            );
        }
    }
}
