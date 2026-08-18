<?php

namespace Reckless\Table\Tests;

use PHPUnit\Framework\Attributes\Test;
use Reckless\Table\Table;
use Reckless\Table\Tests\Fixtures\SortableUser;

class PaginationTest extends TestCase
{
    #[Test]
    public function it_appends_sorting_parameters_to_pagination_links(): void
    {
        $this->seedUsers(5);
        $this->withRequest('/users', ['sort' => 'email', 'dir' => 'desc']);

        // Two per page so the paginator actually has pages to render.
        $html = Table::create(SortableUser::sorted()->paginate(2))->render();

        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('sort=email', $html);
        $this->assertStringContainsString('dir=desc', $html);

        // The sort survived pagination: page one holds the highest emails.
        $this->assertStringContainsString('user5@example.com', $html);
        $this->assertStringNotContainsString('user1@example.com', $html);
    }

    #[Test]
    public function it_appends_allowed_parameters_to_pagination_links(): void
    {
        config(['reckless-tables.allowed_parameters' => ['search']]);

        $this->seedUsers(5);
        $this->withRequest('/users', ['sort' => 'email', 'search' => 'bob']);

        $html = Table::create(SortableUser::sorted()->paginate(2))->render();

        $this->assertStringContainsString('search=bob', $html);
    }

    #[Test]
    public function it_renders_no_pagination_markup_for_a_plain_collection(): void
    {
        $this->seedUsers(3);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::all())->render();

        $this->assertStringNotContainsString('page=', $html);
    }

    #[Test]
    public function it_renders_no_pagination_markup_for_a_single_page(): void
    {
        $this->seedUsers(2);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::sorted()->paginate(10))->render();

        $this->assertStringContainsString('user1@example.com', $html);
        $this->assertStringNotContainsString('page=2', $html);
    }
}
