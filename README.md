# Laravel Tables
[![Made for Laravel 13](https://img.shields.io/badge/laravel-13.0-red.svg)](http://laravel.com/)
[![PHP 8.3+](https://img.shields.io/badge/php-8.3%2B-blue.svg)](https://www.php.net/)
[![Latest Tag](https://img.shields.io/github/tag/reckless/laravel-table.svg)](https://github.com/recklessAgency/laravel-table/releases)
[![Tests](https://github.com/RecklessAgency/laravel-table/actions/workflows/tests.yml/badge.svg)](https://github.com/RecklessAgency/laravel-table/actions/workflows/tests.yml)

This package contains flexible ways of rendering Eloquent collections as dynamic HTML tables.  This includes
techniques for sortable columns, customizable cell data, automatic pagination, ~~user-definable rows-per-page, batch
action handling, and extensible filtering~~ (coming soon).


## Requirements

| Package | Requires |
| --- | --- |
| Laravel | 13.x |
| PHP | 8.3, 8.4 or 8.5 |

Earlier Laravel versions live on their own branches (`laravel-12`, `laravel-11`, and so on).

The package requires `laravel/framework` rather than individual `illuminate/*` packages
on purpose: it uses the `config()`, `view()` and `url()` helpers, which ship in
Illuminate's Foundation component and have no standalone package.

## Installation

Require the package:

```
composer require reckless/laravel-table:dev-laravel-13
```

The service provider and the `Table` facade are registered automatically through package
discovery, so no `config/app.php` changes are needed.

Publish the views and config if you want to customise them:

```
php artisan vendor:publish
```

## Usage

**In order to render an HTML table of Eloquent models into a view**, first create a Table object, passing in your
 model collection (this could be done in your controller, repository, or any service class):

 ```php
 $rows = User::get(); // Get all users from the database
 $table = Table::create($rows); // Generate a Table based on these "rows"
 ```

 Then pass that object to your view:

```php
return view('users.index', ['table' => $table]);
```

In your view, the table object can be rendered using its `render` function:

```php
{!! $table->render() !!}
```

Which would render something like this:

![Basic example](https://raw.githubusercontent.com/reckless/laravel-table/master/examples/images/basic_initialization.png)

### Sorting

To add links in your headers which sort the indicated column, add the `Sortable` trait to your model.  Since no
fields are allowed to be sorted by default (for security reasons), also add a `sortable` array containing allowed fields.

```php
use Reckless\Table\Traits\Sortable;

class User extends Model {

	use Sortable;

    /**
     * The attributes which may be used for sorting dynamically.
     *
     * @var array
     */
    protected $sortable = ['username', 'email', 'created_at'];
```

This adds the `sortable` scope to your model, which you should use when retrieving rows.  Altering our example,
`$rows = User::get()` becomes:

 ```php
 $rows = User::sorted()->get(); // Get all users from the database, but listen to the user Request and sort accordingly
```

Now, our table will be rendered with links in the header:

![Sortable example](https://raw.githubusercontent.com/reckless/laravel-table/master/examples/images/sortable_initialization.png)

The links will contain query strings like `?sort=username&direction=asc`.

### Pagination

If you paginate your Eloquent collection, it will automatically be rendered below the table:

 ```php
 $rows = User::sorted()->paginate(); // Get all users from the database, sort, and paginate
```

## Customization

### Columns

Pass in a second argument to your database call / Table creation, **columns**:

```php
 $table = Table::create($rows, ['username', 'created_at']); // Generate a Table based on these "rows"
```


### Cells

You can specify a closure to use when rendering cell data when adding the column:

```php
// We pass in the field, label, and a callback accepting the model data of the row it's currently rendering
$table->addColumn('created_at', 'Added', function($model) {
    return $model->created_at->diffForHumans();
});
```

Also, since the table is accessing our model's attributes, we can add or modify any column key we'd like by using
[accessors](https://laravel.com/docs/13.x/eloquent-mutators#accessors-and-mutators):

```php
    protected function getRenderedCreatedAtAttribute()
    {
        // We access the following diff string with "$model->rendered_created_at"
        return $this->created_at->diffForHumans();
    }
```

The default view favors the `rendered_foobar` attribute, if present, otherwise it uses the `foobar` attribute.

### View

A copy of the view file is located in `/resources/vendor/reckless/tables/` after you've run `php artisan vendor:publish`.
You can copy this file wherever you'd like and alter it, then tell your table to use the new view:

```php
$table->setView('users.table');
```

## Pagination

If the collection passed in is a `LengthAwarePaginator` (i.e. the result of `paginate()`),
the view renders its links below the table, and the current sort field, sort direction and
any `allowed_parameters` are appended to those links.

`simplePaginate()` and cursor pagination results are treated as plain collections — no links
are rendered for them.

## Testing

The suite runs against a real Laravel application via `orchestra/testbench`, with an
in-memory SQLite database:

```
composer test
```

Tests also fail on any PHP deprecation raised from this package's own code, which is what
keeps the supported-PHP claim honest.

To run the suite against a PHP version you don't have installed locally, mount the project
into the official image — the dependency tree is pure PHP, so a `vendor/` built on any
supported version works:

```
docker run --rm -v "$PWD":/app -w /app -e LOG_DEPRECATIONS_WHILE_TESTING=true \
  php:8.5-cli php vendor/bin/phpunit
```
