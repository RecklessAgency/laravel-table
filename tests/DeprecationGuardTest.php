<?php

namespace Reckless\Table\Tests;

use Monolog\Level;
use PHPUnit\Framework\Attributes\Test;

/**
 * The whole "works on PHP 8.5" claim rests on the deprecation guard in TestCase, and
 * that guard has several ways to quietly become a no-op: Laravel discards deprecations
 * during tests unless LOG_DEPRECATIONS_WHILE_TESTING is set, and the guard only reports
 * records it can attribute to this package. These tests prove both halves still work.
 */
class DeprecationGuardTest extends TestCase
{
    #[Test]
    public function the_deprecations_channel_captures_php_deprecations(): void
    {
        // A genuine E_DEPRECATED, routed through Laravel's error handler.
        str_replace('\\', '/', null);

        $this->assertNotEmpty(
            $this->deprecationHandler()->getRecords(),
            'Laravel is discarding deprecations; check LOG_DEPRECATIONS_WHILE_TESTING in phpunit.xml.'
        );

        $this->deprecationHandler()->clear();
    }

    #[Test]
    public function the_guard_reports_deprecations_attributed_to_the_package(): void
    {
        $source = realpath(__DIR__ . '/../src');

        $this->deprecationHandler()->handle($this->fakeDeprecation(
            "ErrorException: something is deprecated in /vendor/some/helper.php:12\n"
            . "Stack trace:\n"
            . "#0 {$source}/Table.php(205): class_basename(NULL)\n"
        ));

        $this->assertSame(
            ['ErrorException: something is deprecated in /vendor/some/helper.php:12 (at Table.php:205)'],
            $this->packageDeprecations()
        );

        $this->deprecationHandler()->clear();
    }

    #[Test]
    public function the_guard_ignores_deprecations_from_outside_the_package(): void
    {
        $this->deprecationHandler()->handle($this->fakeDeprecation(
            "ErrorException: unrelated framework deprecation in /vendor/other.php:3\n"
            . "Stack trace:\n"
            . "#0 /vendor/other/thing.php(9): something()\n"
        ));

        $this->assertSame([], $this->packageDeprecations());

        $this->deprecationHandler()->clear();
    }

    private function fakeDeprecation(string $message): \Monolog\LogRecord
    {
        return new \Monolog\LogRecord(
            datetime: new \Monolog\DateTimeImmutable(true),
            channel: 'deprecations',
            level: Level::Warning,
            message: $message,
        );
    }
}
