<?php

namespace Reckless\Table\Tests;

use PHPUnit\Framework\Attributes\Test;
use Reckless\Table\BlankModel;
use Reckless\Table\Table;

class BlankModelTest extends TestCase
{
    #[Test]
    public function it_wraps_stdclass_rows_and_reads_them_via_arr_get(): void
    {
        $this->withRequest('/users');

        $html = Table::create(collect([
            (object) ['id' => 1, 'username' => 'bob'],
            (object) ['id' => 2, 'username' => 'alice'],
        ]))->render();

        $this->assertStringContainsString('bob', $html);
        $this->assertStringContainsString('alice', $html);
        $this->assertStringContainsString('<th', $html);
    }

    #[Test]
    public function it_reads_raw_query_builder_results(): void
    {
        $this->seedUsers(2);
        $this->withRequest('/users');

        $html = Table::create(\DB::table('users')->get())->render();

        $this->assertStringContainsString('user1@example.com', $html);
        $this->assertStringContainsString('user2@example.com', $html);
    }

    #[Test]
    public function blank_model_returns_null_for_missing_keys_and_exposes_to_array(): void
    {
        $model = new BlankModel(['a' => 1]);

        $this->assertSame(1, $model->a);
        $this->assertNull($model->nope);
        $this->assertSame(['a' => 1], $model->toArray());
    }

    #[Test]
    public function blank_model_supports_dot_notation_and_swallows_method_calls(): void
    {
        $model = new BlankModel(['nested' => ['key' => 'value']]);

        $this->assertSame('value', $model->{'nested.key'});
        $this->assertNull($model->anyMethod());
    }
}
