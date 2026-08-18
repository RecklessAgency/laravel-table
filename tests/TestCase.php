<?php

namespace Reckless\Table\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\TestHandler;
use Orchestra\Testbench\TestCase as Orchestra;
use Reckless\Table\Facades\Table as TableFacade;
use Reckless\Table\Providers\TableServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [TableServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Table' => TableFacade::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Laravel logs PHP deprecations rather than throwing them, and swallows them
        // entirely while testing unless LOG_DEPRECATIONS_WHILE_TESTING is set (see
        // phpunit.xml). Capture them in memory so tearDown() can fail on any that
        // originate from this package.
        $app['config']->set('logging.channels.deprecations', [
            'driver'  => 'monolog',
            'handler' => TestHandler::class,
        ]);
        $app['config']->set('logging.deprecations', [
            'channel' => 'deprecations',
            'trace'   => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('password')->default('secret');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Gather while the app is alive, assert once it has been torn down: an
        // assertion failure here would otherwise skip parent::tearDown() and leave
        // Laravel's error handlers installed, which PHPUnit reports as risky.
        $deprecations = $this->packageDeprecations();

        parent::tearDown();

        $this->assertSame([], $deprecations, 'PHP deprecations were triggered from the package.');
    }

    /**
     * PHP deprecations attributable to this package, including ones raised inside
     * framework helpers on our behalf. Each is reduced to "message (at file:line)"
     * so a failure stays readable.
     *
     * @return array<int, string>
     */
    protected function packageDeprecations(): array
    {
        if (! $this->app) {
            return [];
        }

        $source = realpath(__DIR__ . '/../src');

        $deprecations = [];

        foreach ($this->deprecationHandler()->getRecords() as $record) {
            $message = is_array($record) ? $record['message'] : $record->message;

            preg_match_all('#(' . preg_quote($source, '#') . '[^(]*|.*table\.blade\.php)\((\d+)\)#', $message, $frames);

            if ($frames[0] === []) {
                continue;
            }

            $deprecations[] = sprintf(
                '%s (at %s:%s)',
                strtok($message, "\n"),
                basename($frames[1][0]),
                $frames[2][0]
            );
        }

        return $deprecations;
    }

    protected function deprecationHandler(): TestHandler
    {
        foreach (Log::channel('deprecations')->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return $handler;
            }
        }

        $this->fail('The deprecations TestHandler is not configured.');
    }

    /**
     * Swap the container's request. Using instance() fires the 'request' rebinding
     * callback registered by RoutingServiceProvider, which keeps the URL generator
     * and the paginator's path resolver in step with Request::input().
     */
    protected function withRequest(string $uri, array $query = []): void
    {
        $this->app->instance('request', Request::create($uri, 'GET', $query));
    }

    protected function seedUsers(int $count = 3): void
    {
        $rows = [];

        foreach (range(1, $count) as $i) {
            $rows[] = [
                'username'   => 'user' . $i,
                'email'      => 'user' . $i . '@example.com',
                'first_name' => 'First' . $i,
                'password'   => 'secret',
                'created_at' => '2026-01-0' . $i . ' 00:00:00',
                'updated_at' => '2026-01-0' . $i . ' 00:00:00',
            ];
        }

        DB::table('users')->insert($rows);
    }
}
