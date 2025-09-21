<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // CRITICAL: Prevent running tests on non-test database
        // This prevents accidentally wiping the development database
        $database = config('database.connections.pgsql.database');

        // List of allowed test database names (local and CI)
        $allowedTestDatabases = [
            'learning_app_test',    // Local development and GitHub Actions CI
            'learning_app_e2e',     // GitHub Actions E2E
        ];

        if (! in_array($database, $allowedTestDatabases)) {
            throw new \Exception(
                "🚨 CRITICAL ERROR: Tests attempting to run on non-test database!\n".
                '   Expected one of: '.implode(', ', $allowedTestDatabases)."\n".
                "   Actual: {$database}\n".
                "   \n".
                "   This would WIPE YOUR DATABASE!\n".
                "   \n".
                "   To run tests safely, use:\n".
                "   ./scripts/safe-test.sh\n".
                "   \n".
                "   Or manually set:\n".
                '   APP_ENV=testing DB_DATABASE=learning_app_test php artisan test'
            );
        }

        // Disable CSRF middleware for all tests to prevent 419 errors
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
    }
}
