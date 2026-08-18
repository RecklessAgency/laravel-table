<?php

namespace Reckless\Table\Tests;

use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Reckless\Table\Facades\Table as TableFacade;
use Reckless\Table\Table;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_package_config_under_the_reckless_tables_namespace(): void
    {
        $this->assertSame('sort', config('reckless-tables.key_field'));
        $this->assertSame('dir', config('reckless-tables.key_direction'));
        $this->assertSame('asc', config('reckless-tables.default_direction'));
        $this->assertSame([], config('reckless-tables.allowed_parameters'));
    }

    #[Test]
    public function it_registers_the_table_binding_and_facade(): void
    {
        $this->assertInstanceOf(Table::class, $this->app->make('table'));
        $this->assertInstanceOf(Table::class, TableFacade::create(collect([])));
    }

    #[Test]
    public function it_registers_the_reckless_view_namespace(): void
    {
        $this->assertTrue(View::exists('reckless::table'));
    }
}
