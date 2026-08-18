<?php

namespace Reckless\Table\Tests\Fixtures;

/**
 * The view prefers a "rendered_{field}" attribute over the raw one, so this model
 * decorates its username through a legacy Eloquent accessor.
 */
class RenderedUser extends SortableUser
{
    public function getRenderedUsernameAttribute()
    {
        return '<em>' . $this->username . '</em>';
    }
}
