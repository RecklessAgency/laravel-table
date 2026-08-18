<?php

namespace Reckless\Table\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Reckless\Table\Traits\Sortable;

class SortableUser extends Model
{
    use Sortable;

    protected $table = 'users';

    protected $guarded = [];

    /** Fields the user is allowed to sort by. */
    protected $sortable = ['id', 'username', 'email'];

    /** Custom sorter, resolved by Sortable via 'sort' . Str::studly($field). */
    public function sortFullName($query, $direction)
    {
        return $query->orderBy('users.first_name', $direction);
    }
}
