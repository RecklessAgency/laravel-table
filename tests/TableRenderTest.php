<?php

namespace Reckless\Table\Tests;

use PHPUnit\Framework\Attributes\Test;
use Reckless\Table\Table;
use Reckless\Table\Tests\Fixtures\RenderedUser;
use Reckless\Table\Tests\Fixtures\SortableUser;

class TableRenderTest extends TestCase
{
    #[Test]
    public function it_renders_eloquent_models_with_auto_generated_columns(): void
    {
        $this->seedUsers(3);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::all())->render();

        $this->assertStringContainsString('<table class="table">', $html);

        // Labels come from ucwords(str_replace('_', ' ', $field)).
        $this->assertStringContainsString('Username', $html);
        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('First Name', $html);

        // Timestamps are excluded by getFieldsFromModels().
        $this->assertStringNotContainsString('Updated At', $html);
        $this->assertStringNotContainsString('Created At', $html);

        $this->assertSame(3, substr_count($html, '@example.com'));
        $this->assertStringContainsString('user2@example.com', $html);
    }

    #[Test]
    public function it_renders_only_the_columns_it_is_given_in_order(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::all(), ['email', 'username'])->render();

        $this->assertLessThan(strpos($html, 'Username'), strpos($html, 'Email'));
        $this->assertStringNotContainsString('Password', $html);
        $this->assertSame(2, substr_count($html, '</th>'));
    }

    #[Test]
    public function it_renders_a_column_label_from_a_field_label_pair(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $html = Table::create(SortableUser::all(), ['email' => 'E-mail address'])->render();

        $this->assertStringContainsString('E-mail address', $html);
    }

    #[Test]
    public function it_renders_a_column_renderer_closure(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $table = Table::create(SortableUser::all(), ['username']);
        $table->addColumn('email', 'Email', function ($model) {
            return '<b>' . $model->email . '</b>';
        });

        $html = $table->render();

        $this->assertStringContainsString('<b>user1@example.com</b>', $html);
    }

    #[Test]
    public function it_inserts_a_column_at_a_given_index(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $table = Table::create(SortableUser::all(), ['username']);
        $table->addColumn('email', 'Email', function ($model) {
            return $model->email;
        }, 0);

        $html = $table->render();

        $this->assertLessThan(strpos($html, 'Username'), strpos($html, 'Email'));
    }

    #[Test]
    public function it_applies_column_classes_and_data_attributes(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $table = Table::create(SortableUser::all(), ['username']);
        $column = $table->getColumns()[0];
        $column->addClass('text-end');
        $column->setData('role', 'name');

        $html = $table->render();

        $this->assertStringContainsString('class="text-end"', $html);
        $this->assertStringContainsString('data-role="name"', $html);
    }

    #[Test]
    public function it_prefers_a_rendered_accessor_over_the_raw_attribute(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $html = Table::create(RenderedUser::all(), ['username'])->render();

        $this->assertStringContainsString('<em>user1</em>', $html);
    }

    #[Test]
    public function it_renders_an_empty_collection_without_triggering_diagnostics(): void
    {
        $this->withRequest('/users');

        $html = Table::create(collect([]))->render();

        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringNotContainsString('<tr>', $html);
    }

    #[Test]
    public function it_renders_a_table_built_with_no_rows(): void
    {
        $this->withRequest('/users');

        $html = (new Table)->render();

        $this->assertStringContainsString('<tbody>', $html);
    }

    #[Test]
    public function it_renders_with_view_vars_cleared(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $table = Table::create(SortableUser::all(), ['username']);
        $table->setView('reckless::table', false);

        $this->assertStringContainsString('user1', $table->render());
    }

    #[Test]
    public function it_renders_extra_view_vars(): void
    {
        $this->seedUsers(1);
        $this->withRequest('/users');

        $table = Table::create(SortableUser::all(), ['username']);
        $table->setView('reckless::table', ['class' => 'table table-striped']);

        $this->assertStringContainsString('<table class="table table-striped">', $table->render());
    }
}
