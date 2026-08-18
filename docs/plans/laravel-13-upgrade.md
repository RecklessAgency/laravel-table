# Upgrade `reckless/laravel-table` to Laravel 13 + PHP 8.5

## Context

`reckless/laravel-table` is an ~850-line Laravel package (namespace `Reckless\Table\`) that renders sortable HTML tables from Eloquent collections. It is versioned by branch: `laravel-8` → `laravel-9` → `laravel-10` → `laravel-11` → `laravel-12`, and every one of those upgrades changed exactly one line — the `laravel/framework` constraint in `composer.json`.

We now need a `laravel-13` branch so the package can be used on Laravel 13 projects, and we need to know it actually works on PHP 8.5.

The complication: **the package currently has no working verification of any kind.** `tests/Gbrock/SanityTest.php` extends `PHPUnit_Framework_TestCase`, a class removed in PHPUnit 6, while `composer.json` dev-requires PHPUnit ^9.5 — so the one test in the repo cannot even load. `phpunit.xml` uses a PHPUnit 4/5 schema whose attributes are hard errors against PHPUnit 10+. `.travis.yml` targets PHP 5.4/5.5 on a CI service that no longer exists. Nothing in the suite has ever booted a Laravel container, which is where every one of this package's failure modes lives (config merge, view namespace, `Request::input()`, `URL::getRequest()`, Eloquent scopes, paginator rendering).

So the deliverable is not just a version bump: it is a bump plus the minimum harness needed to *prove* the bump, run against a real PHP 8.5 runtime.

### What the research already settled

Verified against `laravel/framework` 13.x source — **every framework API this package touches survives Laravel 13**, so there are no forced code changes:

- `Arrayable::toArray()` still has no declared return type → `BlankModel::toArray()` is fine
- `Request::only()` (now via `Illuminate\Support\Traits\InteractsWithData`), `Request::input()`, `Request::merge()`
- `UrlGenerator::getRequest()` (`Column.php:216`)
- `LengthAwarePaginator::render()` / `links()` / `appends()`
- Legacy `getIsSortableAttribute()` accessors (`HasAttributes::hasGetMutator`) and legacy `scopeSorted()` prefix scopes (`Model::hasNamedScope`)
- `ServiceProvider::register()`/`boot()` and `Facade::getFacadeAccessor()` are all still untyped

Verified via Packagist: `laravel/framework` 13.x requires PHP `^8.3`; `orchestra/testbench ^11.0` is the Laravel 13 line (requires PHP `^8.3`, framework `^13.x`, phpunit `^11.5.50|^12.5.8|^13.0.0`); `phpunit/phpunit` 12.5.x requires PHP `>=8.3` (13.x requires `>=8.4.1`, so 12 is the version that spans our whole matrix).

PHP 8.4/8.5 audit: all files pass `php -l` on 8.4.7. No implicit-nullable parameters (there are **no type declarations anywhere** in the package), no `${}` interpolation, no `each()`, no curly offsets, no dynamic properties, no internal interfaces needing `#[\ReturnTypeWillChange]`. PHP 8.5's own new warnings (non-array destructuring, out-of-range float→int, NAN coercion) have no matching code.

### Agreed scope

Bump + a working test harness. Deliberately **out** of scope: the wider modernisation (native type signatures, rewriting `addColumn()`'s `func_get_args()` dispatch, PHPStan) — see *Deferred* below.

---

## 1. Branch and repo hygiene

```bash
git checkout laravel-12
git checkout -b laravel-13
```

Add `.gitignore` (the repo has none, so step 3 would otherwise stage `vendor/`):

```
/vendor/
/composer.lock
/.phpunit.cache/
.phpunit.result.cache
```

This is a library, so the lockfile stays uncommitted (it already is on this branch line).

## 2. `composer.json`

Full replacement of the `require` / `require-dev` blocks, plus three new blocks. Keep the existing `name`, `description`, `keywords`, `license`, `authors` and `autoload` untouched.

```json
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.0"
    },
    "require-dev": {
        "orchestra/testbench": "^11.0",
        "phpunit/phpunit": "^12.5.8"
    },
    "autoload": {
        "psr-4": {
            "Reckless\\Table\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Reckless\\Table\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Reckless\\Table\\Providers\\TableServiceProvider"
            ],
            "aliases": {
                "Table": "Reckless\\Table\\Facades\\Table"
            }
        }
    },
    "config": {
        "sort-packages": true
    },
    "scripts": {
        "test": "@php vendor/bin/phpunit"
    }
```

Line-by-line rationale:

- **`php: ^8.3`** — declares the real floor (the framework's), rather than leaving it unstated as today.
- **`laravel/framework: ^13.0`** — the bump. Note `laravel/framework` (not `illuminate/support`) is *correct and must stay*: the package calls `config()`, `view()` and `url()`, which live in `Illuminate/Foundation/helpers.php`, and Foundation has no split `illuminate/*` package. Worth a README note so it isn't "tidied" later.
- **`laravel/helpers` removed** — it was in `require` solely for the single `array_get()` call at `src/BlankModel.php:17` (confirmed: the only symbol from that package used anywhere). Removal is contingent on the `Arr::get` fix in step 5.
- **`orchestra/testbench: ^11.0`** — the harness. Booting a real container is the whole point; hand-rolling one from `illuminate/*` parts would be more code than the package itself.
- **`phpunit/phpunit: ^12.5.8`** — matches testbench's floor and Laravel 13's own recommendation, and installs on 8.3 through 8.5, so one XML schema covers the entire CI matrix.
- **`extra.laravel`** — auto-discovery, which deletes a manual `config/app.php` step from the README. Safe for existing consumers: `Application::register()` short-circuits on an already-registered provider.

## 3. Install and pre-flight

```bash
composer validate --strict
composer update --prefer-stable
composer why-not php 8.5.9        # expect no blockers
composer check-platform-reqs
```

## 4. Test harness

| Action | Path |
|---|---|
| delete | `tests/bootstrap.php` — registers PSR-0 prefix `Gbrock\` (stale vendor name) for a class in the global namespace; dead code, replaced by `autoload-dev` |
| delete | `tests/Gbrock/SanityTest.php` and the directory |
| rewrite | `phpunit.xml` |
| add | `tests/TestCase.php` |
| add | `tests/Fixtures/SortableUser.php` |

### `phpunit.xml` — full replacement (PHPUnit 12 schema)

Everything in the current file goes: `backupStaticAttributes`, `convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions` and `syntaxCheck` were all removed by PHPUnit 10 and are schema errors now. The new file needs `bootstrap="vendor/autoload.php"`, `cacheDirectory`, the xsi schema location, `<testsuite>` pointing at `tests` (not `./tests/Gbrock/`), a `<source>` block with `restrictDeprecations`/`restrictNotices`/`restrictWarnings` scoped to `src`, `failOnDeprecation`/`failOnWarning`/`failOnNotice`, `beStrictAboutOutputDuringTests`, and a `<php>` block setting:

```xml
<env name="APP_ENV" value="testing"/>
<env name="LOG_DEPRECATIONS_WHILE_TESTING" value="true"/>
```

That second env var is load-bearing — see below.

### `tests/TestCase.php`

Extends `Orchestra\Testbench\TestCase` with:

- `getPackageProviders()` → `[TableServiceProvider::class]`
- `getPackageAliases()` → `['Table' => Facades\Table::class]` (exercises the untyped `getFacadeAccessor()`)
- `defineEnvironment()` → in-memory SQLite on the `testing` connection, plus the deprecations log channel below
- `setUp()` → `Schema::create('users', …)` with `id`, `username`, `email`, `first_name` (nullable), `password`, timestamps. No migration files, no `RefreshDatabase` — in-memory SQLite dies with the app.
- `withRequest(string $uri, array $query = [])` helper → `$this->app->instance('request', Request::create($uri, 'GET', $query))`. Use `instance()` specifically: `RoutingServiceProvider` registers a `rebinding('request')` callback that calls `$app['url']->setRequest()`, so this one call keeps `Request::input()`, `URL::getRequest()->path()` **and** the paginator's current-path resolver consistent. A bare `Request::swap()` would not.
- A deprecation guard (see below).

### The deprecation guard — why it needs to exist

I verified this in `Illuminate\Foundation\Bootstrap\HandleExceptions` (13.x):

```php
error_reporting(-1);                                    // line 47, unconditional
// handleError():
if ($this->isDeprecation($level)) { $this->handleDeprecationError(...); }   // logged, never thrown
elseif (error_reporting() & $level) { throw new ErrorException(...); }
// shouldIgnoreDeprecationErrors():
|| (static::$app->runningUnitTests() && ! Env::get('LOG_DEPRECATIONS_WHILE_TESTING'));
```

Three consequences that shape the design:

1. **`-d error_reporting=E_ALL` on the CLI is a no-op** — Laravel resets it to `-1` at bootstrap.
2. **Warnings and notices become thrown `ErrorException`s** inside a booted app. Good news: PHP 8.5's new warnings would hard-fail a test rather than scroll past.
3. **Deprecations are silently discarded during tests** unless `LOG_DEPRECATIONS_WHILE_TESTING` is set. PHPUnit's own `failOnDeprecation` never sees them, because Laravel replaces PHPUnit's error handler without chaining.

So the guard is: point `logging.deprecations` at a `deprecations` channel using `Monolog\Handler\TestHandler` with `'trace' => true`, then in `tearDown()` assert that no captured record's message mentions `realpath(__DIR__.'/../src')`. Filtering on `src/` means an unrelated framework or testbench deprecation doesn't redden the suite, while a deprecation triggered *inside* a vendor helper on our behalf (e.g. `class_basename()` → `str_replace()`) still fails, because the trace names our file.

Add one meta-test (`tests/DeprecationGuardTest.php`) that deliberately triggers a deprecation and asserts the handler recorded it, then clears it. Without that, the guard can silently become a no-op and nobody would notice.

## 5. Smoke tests, and the source fixes they force

~18 tests across five files. Each maps to an integration point that is currently verified-by-reading-source-only. Three of them **fail against the current code**; those failures are pre-existing bugs, and the fixes are listed against them.

### `tests/ServiceProviderTest.php`
1. `it_merges_package_config_under_the_reckless_tables_namespace` — `config('reckless-tables.key_field') === 'sort'`, `key_direction === 'dir'`, `default_direction === 'asc'`, `allowed_parameters === []`. Proves `mergeConfigFrom()` and that every `config('reckless-tables.*')` call site has data.
2. `it_registers_the_table_binding_and_facade` — `$this->app->make('table')` is a `Table`; `TableFacade::create(collect([]))` returns a `Table`.
3. `it_registers_the_reckless_view_namespace` — `View::exists('reckless::table')`.

### `tests/TableRenderTest.php`
4. `it_renders_eloquent_models_with_auto_generated_columns` — 3 users → `Table::create(SortableUser::all())->render()`. Asserts `<table class="table">`, `<th>` for `Username`/`Email` (the `ucwords(str_replace('_',' '))` labelling), one `<tr>` per row, cell values present, and `Updated At` **absent** (the timestamp exclusion in `getFieldsFromModels()`, which also exercises `$model->isSortable` resolving through the legacy accessor on L13).
5. `it_renders_only_the_columns_it_is_given_in_order` — `Table::create($rows, ['email','username'])`; assert `Email` precedes `Username`.
6. `it_renders_a_column_renderer_closure` — `$table->addColumn('email','Email', fn ($m) => '<b>'.$m->email.'</b>')`. The only coverage of `forward_static_call_array([new Column(), 'create'], $args)`, the `$this->columns[] =& $new_column` reference append, and `Column::create()`'s 3-arg branch — i.e. the odd legacy constructs, pinned on PHP 8.5.
7. `it_inserts_a_column_at_a_given_index` — 4th arg `0`; assert it renders first. Covers the `array_splice(..., [&$new_column])` branch.
8. `it_prefers_a_rendered_accessor_over_the_raw_attribute` — fixture with `getRenderedUsernameAttribute()`; covers the Blade `$r->{'rendered_'.$field} ?? $r->{$field}` path.
9. `it_renders_an_empty_collection_without_triggering_diagnostics` — **FAILS TODAY.** `Table::create(collect([]))->render()` hits `class_basename($models->first())` at `src/Table.php:205` with `null` → `str_replace(): Passing null to parameter #3 is deprecated`. This is the one genuine PHP-8-era deprecation in the package, and it sits on the main render path for the commonest real edge case (no results).
   → **Fix:** `src/Table.php:205` becomes `$models->first() instanceof \stdClass`. Behaviour identical, deprecation gone.
10. `it_renders_with_view_vars_cleared` — **FAILS TODAY.** `setView('reckless::table', false)` assigns `$this->viewVars = false`, which reaches `array_merge($this->viewVars, [...])` at `src/Table.php:159` → `TypeError`, on a documented API surface, on every PHP 8.
    → **Fix:** `src/Table.php:56-62` becomes `$this->viewVars = is_array($vars) ? $vars : [];`.
11. `it_renders_a_table_built_with_no_rows` — **FAILS TODAY.** `Table::create([])` (or the bare `app('table')` binding) leaves `$models` and `$columns` null → `count(null)` `TypeError` in the view.
    → **Fix:** `protected $columns = [];` and a `?? collect()` fallback for `rows` in `getData()`.

### `tests/SortingTest.php`
12. `sorted_scope_orders_by_the_requested_field_and_direction` — `withRequest('/users', ['sort'=>'email','dir'=>'desc'])`; assert `toSql()` contains `order by "users"."email" desc` and the fetched order matches. Proves legacy `scopeSorted` prefix resolution on L13 plus `Request::input()` through the facade.
13. `sorted_scope_ignores_a_field_that_is_not_sortable` — `?sort=password` → no `order by`. The documented security behaviour, currently untested.
14. `sorted_scope_falls_back_to_the_primary_key_and_config_default` — no query params → `order by "users"."id" asc`.
15. `sorted_scope_uses_a_custom_sort_method_when_one_exists` — fixture with `sortFullName($query, $dir)`, `?sort=full_name` → `order by "users"."first_name"`. Covers `Str::studly` + `method_exists` + `call_user_func`.
16. `sort_urls_toggle_direction_and_preserve_the_current_query` — with `?sort=email&dir=asc`, assert `getSortURL() === 'http://localhost/users/?sort=email&dir=desc'`, `isSorted() === true`. **The `URL::getRequest()->path()` + `url()` + `http_build_query` test.**
17. `sortable_columns_render_as_links_with_a_direction_indicator` — assert `<a href=` and `fa-sort-asc` in output.
18. `allowed_parameters_are_carried_through_sort_urls` — set `reckless-tables.allowed_parameters` to `['search']`, request `?search=bob`, assert `search=bob` survives into `getSortURL()`. Exercises `count(config(…))` at `Column.php:228` — the only coverage this feature will ever have.

### `tests/PaginationTest.php`
19. `it_appends_sorting_parameters_to_pagination_links` — 5 users, `?sort=email&dir=desc`, `SortableUser::sorted()->paginate(2)`, render; assert `page=2`, `sort=email`, `dir=desc` in output. Covers `appendPaginationLinks()`, `Request::only()` via `InteractsWithData`, `appends()`, and `LengthAwarePaginator::render()` *through the Blade view* (so it also proves L13's default pagination view resolves). `paginate(2)` matters — with a single page the paginator renders nothing and the test proves nothing.
    → **Fix (same lines as #9):** `src/Table.php:228` and `resources/views/table.blade.php:51` currently compare `class_basename(...) == 'LengthAwarePaginator'`, which throws the same null deprecation for a no-rows table. Replace with `instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator`. Use the *LengthAware* contract, not the broader `Paginator` one, so `simplePaginate()` behaviour stays exactly as it is today — a compatibility branch is the wrong place to start supporting new paginator types.
20. `it_renders_no_pagination_markup_for_a_plain_collection` — guards the `instanceof` change against over-matching.

### `tests/BlankModelTest.php`
21. `it_wraps_stdclass_rows_and_reads_them_via_arr_get` — `Table::create(collect([(object) ['id'=>1,'username'=>'bob']]))->render()`; assert `bob` present. Proves the `array_get` → `Arr::get` swap and that raw `DB::table()->get()` results still work.
    → **Fix:** `src/BlankModel.php:17` → `Arr::get($this->attributes, $name)`, with `use Illuminate\Support\Arr;`. This is what allows `laravel/helpers` out of `require`.
22. `blank_model_returns_null_for_missing_keys_and_exposes_to_array` — `assertNull($m->nope)`, `assertSame(['a'=>1], $m->toArray())`. Confirms the untyped `toArray()` still satisfies L13's `Arrayable`.

### Total source diff

Five files, ~8 changed lines: `src/BlankModel.php` (`Arr::get` + import), `src/Table.php` (`viewVars` cast, `$columns = []`, `rows` fallback, two `instanceof`), `resources/views/table.blade.php` (one `instanceof`). Every line traces to a named failing test.

## 6. PHP 8.5 verification

`php:8.5-cli` is PHP 8.5.9 and already ships `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`, `xml`, `xmlwriter`, `tokenizer`, `ctype`, `curl`. It lacks `git`/`unzip`, so Composer can't resolve inside it without `apt-get` — but it doesn't need to. `vendor/` is 100% PHP, and resolution is provably identical across 8.4/8.5 (nothing in the tree floors above 8.3), so: **resolve on the host, execute on 8.5.**

```bash
# Baseline on the host (PHP 8.4.7)
vendor/bin/phpunit

# The real target: PHP 8.5.9
docker run --rm -v "$PWD":/app -w /app \
  -e LOG_DEPRECATIONS_WHILE_TESTING=true \
  php:8.5-cli \
  php -d display_errors=1 -d memory_limit=512M \
      vendor/bin/phpunit --display-deprecations --display-warnings --display-notices --display-errors
```

Note `display_errors=1` is for pre-boot output only; don't bother with `error_reporting=E_ALL` (Laravel overwrites it). If a genuine 8.5-resolved lockfile is ever wanted, run Composer inside the container behind a one-off `apt-get install git unzip` and finish with `composer check-platform-reqs`.

Raw Docker beats ddev here: ddev would add a `.ddev/` directory to a package that has no front end and no database (in-memory SQLite), 8.5 isn't preinstalled in its webimage so the first start builds a derived image, and it ends up a heavyweight wrapper around exactly the one `docker run` above.

## 7. CI

Delete `.travis.yml`. Add `.github/workflows/tests.yml`:

- Triggers: push to `master` and `laravel-*`, plus pull requests.
- Matrix, `fail-fast: false`: `php: ['8.3','8.4','8.5']` × `dependencies: [lowest, highest]`.
- Steps: `actions/checkout`, `shivammathur/setup-php` (extensions `mbstring, dom, xml, xmlwriter, curl, sqlite3, pdo_sqlite`, `coverage: none`), `composer validate --strict`, `ramsey/composer-install@v3` with `dependency-versions: ${{ matrix.dependencies }}`, then `vendor/bin/phpunit --display-deprecations --display-warnings --display-notices`.

**PHP 8.5 is a required job, not `continue-on-error`.** 8.5 has been GA since late 2025, Laravel 13 declares `^8.3` with no upper bound, and `setup-php` ships a stable 8.5 build — marking the branch's headline target as allowed-to-fail would defeat its purpose. `prefer-lowest` is worth having on a library: it's what catches testbench 11.0.0's own framework floor pulling resolution somewhere unexpected. No coverage job — a coverage number on 850 lines of smoke-tested code is theatre and it forces xdebug/pcov into the matrix.

## 8. README

Currently badged "Made for Laravel 7", documents manual `config/app.php` registration, and links the Laravel 5.0 docs. Update: supported versions (Laravel 13, PHP 8.3+), auto-discovery replacing the manual registration step, a Testing section with the Docker one-liner, and a note that `laravel/framework` is required deliberately (Foundation helpers) so it doesn't get swapped for `illuminate/support` later.

---

## Deferred (raise as follow-up tickets, do not do here)

- **Three exception classes that don't exist.** `ModelMissingSortableArrayException` (`src/Traits/Sortable.php:19`), `CallableFunctionNotProvidedException` (`src/Column.php:300`), `ColumnKeyNotProvidedException` (`src/Table.php:91`, docblock only). Every one of those `throw` statements is an instant `Error: Class not found` — a real bug, but pre-existing, unrelated to 8.5, and not on any path a smoke test walks. Related: `Sortable::getSortable()` reads `$this->sortable` without `isset`, so a model that uses the trait but forgets to declare the property gets a `Warning`-turned-`ErrorException` instead of the intended exception. Fix the classes and that `isset` together.
- **`func_get_args()` dispatch** in `Table::addColumn()` and `Column::create()`. That *is* the public API; adding real signatures is a BC break for a 2.0. Tests 6 and 7 pin the 3- and 4-arg forms so that work can be done safely later.
- **`forward_static_call_array([new Column(), 'create'], …)`, `array_splice(…, [&$new_column])`, `$this->columns[] =& $new_column`** (`src/Table.php:74-83`). Legal on 8.5, ugly, zero functional payoff to changing now — but `forward_static_call_array` on an instance is a plausible future deprecation target, worth revisiting before PHP 9.
- **`strtolower($direction)` where `$direction` defaults to `false`** (`src/Traits/Sortable.php:11`). Traced it: `strtolower(false)` is `''` (bool→string coercion is not deprecated, only null), and every downstream consumer treats `''` as falsy and falls back to the config default. The only visible artefact is a stray `dir=` in some URLs. No observable behaviour change and no 8.5 impact ⇒ no diff justified here. Test 14 pins the fallback.
- **Cursor pagination** stays unsupported (different contract). Say so in the README rather than half-supporting it.
- **PHPStan.** With zero type declarations, level 0-2 finds roughly what `php -l` already found, and level 5+ without Laravel awareness drowns in `undefined method Model::getSortable()` and `no value type specified in iterable`. Getting signal needs `larastan` plus a committed baseline — a config file whose job is suppressing its own tool. Worth **one ad-hoc larastan level-5 run in a scratch checkout** as a discovery exercise (it would have found the `array_get` and missing-exception issues instantly), triaged into a ticket. Add it properly on the follow-up "native types" branch, where it will have something to check.

---

## Verification

Ordered — each step gates the next.

```bash
composer validate --strict
composer update --prefer-stable
composer why-not php 8.5.9              # expect: no blockers
composer check-platform-reqs
```

1. **Harness boots**: land `TestCase`, the fixture, the rewritten `phpunit.xml` and tests 1-3, and get them green. Nothing else is trustworthy until the container boots.
2. **Guard fires**: land `DeprecationGuardTest` and confirm it detects a deliberate deprecation. If this doesn't work, the PHP 8.5 claim has no evidence behind it.
3. **Green paths**: land tests 4-8, 12-18, 21-22 and the `Arr::get` fix; expect green (these are the APIs already verified present in L13).
4. **Red paths**: land tests 9, 10, 11, 19, 20; expect **red**; apply the ~8 source-line fixes; re-run to green.
5. **Full suite on the host** (PHP 8.4.7): `vendor/bin/phpunit`
6. **Full suite on PHP 8.5.9**: the `docker run … php:8.5-cli` command from §6. Must be green with zero deprecations attributed to `src/`.
7. **Lowest deps**: `composer update --prefer-lowest --prefer-stable && vendor/bin/phpunit`, then restore with `composer update --prefer-stable`.
8. **CI**: push the branch and confirm all six matrix jobs pass, 8.5 included.

Report actual output at steps 5-7. "Should work on 8.5" is not the deliverable; a green run on 8.5.9 is.
