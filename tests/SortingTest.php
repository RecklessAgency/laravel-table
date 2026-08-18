<?php

namespace Reckless\Table\Tests;

use PHPUnit\Framework\Attributes\Test;
use Reckless\Table\Table;
use Reckless\Table\Tests\Fixtures\SortableUser;

class SortingTest extends TestCase
{
    #[Test]
    public function sorted_scope_orders_by_the_requested_field_and_direction(): void
    {
        $this->seedUsers(3);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'desc']);

        $query = SortableUser::sorted();

        $this->assertStringContainsString('order by "users"."email" desc', $query->toSql());
        $this->assertSame(
            ['user3@example.com', 'user2@example.com', 'user1@example.com'],
            $query->pluck('email')->all()
        );
    }

    #[Test]
    public function sorted_scope_ignores_a_field_that_is_not_sortable(): void
    {
        $this->withRequest('/users', ['sort' => 'password', 'dir' => 'desc']);

        $this->assertStringNotContainsString('order by', SortableUser::sorted()->toSql());
    }

    #[Test]
    public function sorted_scope_falls_back_to_the_primary_key_and_config_default(): void
    {
        $this->withRequest('/users');

        $this->assertStringContainsString('order by "users"."id" asc', SortableUser::sorted()->toSql());
    }

    #[Test]
    public function sorted_scope_honours_the_configured_default_direction(): void
    {
        config(['reckless-tables.default_direction' => 'desc']);
        $this->withRequest('/users');

        $this->assertStringContainsString('order by "users"."id" desc', SortableUser::sorted()->toSql());
    }

    #[Test]
    public function sorted_scope_uses_a_custom_sort_method_when_one_exists(): void
    {
        $this->withRequest('/users', ['sort' => 'full_name']);

        // Resolved as 'sort' . Str::studly('full_name') => sortFullName()
        $this->assertStringContainsString('order by "users"."first_name" asc', SortableUser::sorted()->toSql());
    }

    #[Test]
    public function sorted_scope_accepts_an_explicit_field_and_direction(): void
    {
        $this->withRequest('/users');

        $this->assertStringContainsString(
            'order by "users"."username" desc',
            SortableUser::sorted('username', 'desc')->toSql()
        );
    }

    #[Test]
    public function sort_urls_toggle_direction_and_preserve_the_current_query(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'asc']);

        $column = $this->columnFor('email');

        $this->assertTrue($column->isSorted());
        $this->assertSame('asc', $column->getDirection());
        // url() normalises the trailing slash the package appends before the query.
        $this->assertSame('http://localhost/users?sort=email&dir=desc', $column->getSortURL());
    }

    #[Test]
    public function sort_urls_for_unsorted_columns_use_the_default_direction(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'asc']);

        $column = $this->columnFor('username');

        $this->assertFalse($column->isSorted());
        $this->assertStringContainsString('sort=username', $column->getSortURL());
        $this->assertStringContainsString('dir=asc', $column->getSortURL());
    }

    #[Test]
    public function sortable_columns_render_as_links_with_a_direction_indicator(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'asc']);

        $html = Table::create(SortableUser::all(), ['email'])->render();

        // Blade escapes the ampersand in the href.
        $this->assertStringContainsString('<a href="http://localhost/users?sort=email&amp;dir=desc">', $html);
        $this->assertStringContainsString('fa-sort-asc', $html);
    }

    #[Test]
    public function non_sortable_columns_render_as_plain_text(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::all(), ['password'])->render();

        $this->assertStringNotContainsString('<a href', $html);
    }

    #[Test]
    public function allowed_parameters_are_carried_through_sort_urls(): void
    {
        config(['reckless-tables.allowed_parameters' => ['search']]);

        $this->seedUsers(1);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'asc', 'search' => 'bob']);

        $this->assertStringContainsString('search=bob', $this->columnFor('email')->getSortURL());
    }

    #[Test]
    public function parameters_outside_the_allow_list_are_dropped_from_sort_urls(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users', ['sort' => 'email', 'tracking' => 'xyz']);

        $this->assertStringNotContainsString('tracking', $this->columnFor('email')->getSortURL());
    }

    /**
     * Build a table and return the column for the given field, with its model options
     * applied (which is what makes a column sortable).
     */
    private function columnFor(string $field)
    {
        foreach (Table::create(SortableUser::all())->getColumns() as $column) {
            if ($column->getField() === $field) {
                return $column;
            }
        }

        $this->fail("No column was generated for field [{$field}].");
    }
}
