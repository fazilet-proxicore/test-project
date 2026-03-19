# Pulse API Scaffolding Guidelines

This guide describes the exact steps and copy-ready templates to introduce a brand‑new domain table with a full REST API in Pulse (Laravel 12, PHP 8.2+). Follow this to reliably reproduce what we did for Store Chains and other resources.

Contents
- Prerequisites
- Inputs checklist
- Files to create/update
- Implementation steps with templates
    - Migration(s)
    - Eloquent Model (with PHPDoc)
    - Factory
    - Seeder (idempotent)
    - API Resource
    - Form Requests (Store/Update) with Scribe docs
    - Controller (Spatie Query Builder + Scribe)
    - Routes
    - Feature Tests
- Docs, Testing, and Formatting
- MySQL‑safe FK/index teardown rules
- Example: Minimal filter/sort set


## Prerequisites
- **Proxicore Bedrock**: This project requires the `proxicore/bedrock` package, which contains shared code, traits, and services used across Proxicore services (Tenancy, Geography, Settings, etc.).
- Spatie Query Builder is available and configured.
- Scribe is installed for API documentation generation.
- PHPUnit configured; tests use SQLite in‑memory.
- Composer scripts:
    - `composer dev` (dev server + queue + logs)
    - `composer test` (clears config and runs tests)
    - `composer setup` (installs, sets up .env, keygen, migrates)


## Inputs checklist
Before you start, decide and write down:
- Entity name (Singular, StudlyCase), e.g. `StoreChain`.
- Table name (snake_case plural), e.g. `store_chains`.
- Columns: names, types, nullability, defaults.
- Relations to other tables (belongsTo/hasMany/etc.).
- Which columns should be filterable and how:
    - partial (LIKE) vs exact.
- Which columns are sortable.
- Seeding strategy (presets, randoms via factory).


## Files to create/update
- database/migrations/<timestamp>_create_{table}_table.php
- app/Models/{Entity}.php
- database/factories/{Entity}Factory.php
- database/seeders/{Entity}Seeder.php
- app/Http/Resources/{Entity}Resource.php
- app/Http/Requests/Store{Entity}Request.php
- app/Http/Requests/Update{Entity}Request.php
- app/Http/Controllers/Api/{Entity}Controller.php
- routes/api.php (add apiResource route)
- tests/Feature/{Entity}ApiTest.php
- database/seeders/DatabaseSeeder.php (call the seeder in order)


## Implementation steps with templates
Use the templates below. Replace placeholders consistently:
- {Entity} → e.g. StoreChain
- {entity} → e.g. storeChain
- {table} → e.g. store_chains


### 1) Migration: create table
Create with timestamps, columns, and constraints. Use `$table->primaryKey()` and `$table->tenant()` (macros provided by Bedrock) for standard Pulse entities. Example:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{table}', function (Blueprint $table) {
            $table->primaryKey();
            $table->timestamps();
            $table->tenant();
            // Columns
            $table->string('name');
            $table->string('country_code', 2); // ISO Country (example)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table}');
    }
};
```

If you need to add a nullable FK column on another table (like `customers.store_chain_id`), use a dedicated migration that drops FK → index → column in `down()` (see FK/index teardown section). Use `foreignUlid` for relations to standard Pulse entities.


### 2) Eloquent Model with PHPDoc
Standard Pulse entities should extend `TenantModel` from the Bedrock package. Use Bedrock traits like `HasCountryCode` or `HasPhone` where applicable.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany; // if needed
use Proxicore\Bedrock\Tenancy\Models\TenantModel;
use Proxicore\Bedrock\Geography\Models\Traits\HasCountryCode;

/**
 * @property string $id (ULID)
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * // Add all scalar attributes below
 * @property string $name
 * @property string $country_code
 *
 * // Add relation properties (read‑only) if any
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer> $customers
 *
 * @mixin \Eloquent
 */
class {Entity} extends TenantModel
{
    use HasFactory;
    use HasCountryCode;

    protected $fillable = [
        'name',
        'country_code',
    ];

    // Example relation
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
```

Adjust relations/attributes to your entity.


### 3) Factory
Use `Str::ulid()` for ID if not automatically handled, or rely on model behavior.

```php
<?php

namespace Database\Factories;

use App\Models\{Entity};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<{Entity}> */
class {Entity}Factory extends Factory
{
    protected $model = {Entity}::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Group',
            'country_code' => fake()->countryCode(),
        ];
    }
}
```


### 4) Seeder (idempotent)
- Do not duplicate rows when re‑running.

```php
<?php

namespace Database\Seeders;

use App\Models\{Entity};
use Illuminate\Database\Seeder;

class {Entity}Seeder extends Seeder
{
    public function run(): void
    {
        if ({Entity}::count() > 0) {
            return;
        }

        $presets = [
            ['name' => 'Mega Stores', 'country_code' => 'US'],
            ['name' => 'Nordic Market Group', 'country_code' => 'SE'],
            ['name' => 'Euro Retail Collective', 'country_code' => 'DE'],
        ];

        foreach ($presets as $data) {
            {Entity}::create($data);
        }

        {Entity}::factory()->count(3)->create();
    }
}
```

Wire it in `DatabaseSeeder` in the right order.


### 5) API Resource
Use ISO‑8601 for timestamps and include only the fields you want public. Use camelCase keys for nested related resources and `whenLoaded()` for consistency.

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property string $country_code
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class {Entity}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'name' => $this->name,
            'country_code' => $this->country_code,
            // Example of nested relation
            'customers' => CustomerResource::collection($this->whenLoaded('customers')),
        ];
    }
}
```


### 6) Form Requests (validation + Scribe docs)
Use `ulid` validation for IDs.

Store:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{Entity}Request extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The {entity} name.',
                'example' => 'Mega Stores',
            ],
            'country_code' => [
                'description' => 'ISO 3166-1 alpha-2 country code.',
                'example' => 'US',
            ],
        ];
    }
}
```

Update:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{Entity}Request extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The {entity} name.',
                'example' => 'Mega Stores',
            ],
            'country_code' => [
                'description' => 'ISO 3166-1 alpha-2 country code.',
                'example' => 'US',
            ],
        ];
    }
}
```


### 7) Controller (Spatie Query Builder + Scribe)
Use `@group Model APIs` and `@subgroup {Entity}s`. Include a `$relations` array for consistent eager loading.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store{Entity}Request;
use App\Http\Requests\Update{Entity}Request;
use App\Http\Resources\{Entity}Resource;
use App\Models\{Entity};
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Model APIs
 *
 * @subgroup {Entity}s
 */
class {Entity}Controller extends Controller
{
    private array $relations = [
        'customers',
    ];

    /**
     * List {entity}s
     *
     * Retrieve a paginated list of {entity}s. Supports filtering and sorting via Spatie Query Builder.
     *
     * @queryParam filter[name] string Filter by partial name match. Example: Mega
     * @queryParam filter[country_code] string Filter by exact ISO country code. Example: US
     * @queryParam filter[id] string Filter by exact ULID. Example: 01jk...
     * @queryParam sort string Sortable fields: id, name, country_code, created_at, updated_at. Prefix with '-' for descending. Default: name. Example: -created_at
     * @queryParam per_page integer Number of items per page. Default: 15. Example: 25
     * @queryParam page integer Page number. Default: 1. Example: 2
     */
    public function index(Request $request)
    {
        $items = QueryBuilder::for({Entity}::query()->with($this->relations))
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('country_code'),
                AllowedFilter::exact('id'),
            ])
            ->allowedSorts(['id', 'name', 'country_code', 'created_at', 'updated_at'])
            ->defaultSort('name')
            ->paginate(perPage: (int)($request->integer('per_page') ?: 15))
            ->appends($request->query());

        return {Entity}Resource::collection($items);
    }

    /** Create a {entity} */
    public function store(Store{Entity}Request $request)
    {
        $item = {Entity}::create($request->validated());
        $item->load($this->relations);

        return (new {Entity}Resource($item))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Show a {entity} */
    public function show({Entity} $item)
    {
        return new {Entity}Resource($item->loadMissing($this->relations));
    }

    /** Update a {entity} */
    public function update(Update{Entity}Request $request, {Entity} $item)
    {
        $item->update($request->validated());
        $item->load($this->relations);

        return new {Entity}Resource($item);
    }

    /** Delete a {entity} */
    public function destroy({Entity} $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
```

Note: Route model binding parameter names (`$item`) can be renamed to `{entity}` for clarity; update PHPDoc `@urlParam` accordingly if you use Scribe examples.


### 8) Routes
Add an API resource route in `routes/api.php`:

```php
Route::apiResource('{table}', \App\Http\Controllers\Api\{Entity}Controller::class);
```

For non-standard URI names, pass your path (e.g., `store-chains`).


### 9) Feature Tests
Cover index/filter/sort/show/store/update/destroy. Example skeleton:

```php
<?php

namespace Tests\Feature;

use App\Models\{Entity};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {Entity}ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_list(): void
    {
        {Entity}::factory()->count(3)->create();
        $response = $this->getJson('/api/{table}');
        $response->assertOk()->assertJsonStructure([
            'data' => [[ 'id', 'name', 'created_at', 'updated_at' ]],
            'links', 'meta',
        ]);
    }

    public function test_index_can_filter_by_partial_name(): void
    {
        {Entity}::factory()->create(['name' => 'Alpha']);
        {Entity}::factory()->create(['name' => 'Zulu']);
        $response = $this->getJson('/api/{table}?filter[name]=Alp');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_can_sort_by_name_desc(): void
    {
        {Entity}::factory()->create(['name' => 'Alpha']);
        {Entity}::factory()->create(['name' => 'Zulu']);
        $response = $this->getJson('/api/{table}?sort=-name');
        $response->assertOk();
    }

    public function test_show_returns_single_resource(): void
    {
        $item = {Entity}::factory()->create();
        $response = $this->getJson('/api/{table}/'.$item->id);
        $response->assertOk()->assertJsonPath('data.id', $item->id);
    }

    public function test_store_creates_and_validates(): void
    {
        $bad = $this->postJson('/api/{table}', ['name' => 'Only name']);
        $bad->assertStatus(422);

        $payload = ['name' => 'New', 'country_code' => 'US'];
        $res = $this->postJson('/api/{table}', $payload);
        $res->assertCreated()->assertJsonPath('data.name', 'New');
    }

    public function test_update_modifies(): void
    {
        $item = {Entity}::factory()->create(['name' => 'Old']);
        $res = $this->putJson('/api/{table}/'.$item->id, ['name' => 'New']);
        $res->assertOk()->assertJsonPath('data.name', 'New');
    }

    public function test_destroy_deletes(): void
    {
        $item = {Entity}::factory()->create();
        $res = $this->deleteJson('/api/{table}/'.$item->id);
        $res->assertNoContent();
        $this->assertDatabaseMissing('{table}', ['id' => $item->id]);
    }
}
```

Adjust fields as your entity requires. For entities with unique constraints, include duplicate checks.


## Docs, Testing, and Formatting
- Documentation (Scribe):
    - Annotate controller methods with `@group`, `@queryParam`, `@urlParam`, and describe body params in Form Requests via `bodyParameters()`.
    - Generate: `php artisan scribe:generate`.
- Tests: `composer test` (uses SQLite in‑memory; no external services required).
- Code style: run `./vendor/bin/pint` before committing.


## MySQL‑safe FK/index teardown rules
When adding FKs in migrations on MySQL, always:
- Name FK and index explicitly in `up()`:
    - FK: `customers_store_chain_id_foreign`
    - Index: `customers_store_chain_id_index`
- In `down()`, wrap in `Schema::withoutForeignKeyConstraints(function () { ... })` and drop in this order:
    1) `$table->dropForeign('<fk_name>');`
    2) `$table->dropIndex('<index_name>');`
    3) `$table->dropColumn('<column>');`

If you need to drop a table referenced by other FKs, also wrap the `dropIfExists()` in `Schema::withoutForeignKeyConstraints()`.

Note: MySQL auto‑creates an index for FK columns. You may omit the explicit index to reduce teardown noise; if you do create it, you must drop FK first.


## Example: Minimal filter/sort set
- Filters:
    - Partial: `AllowedFilter::partial('name')`
    - Exact: `AllowedFilter::exact('id')`
- Sorts: `['id', 'name', 'created_at', 'updated_at']`
- Default sort: `.defaultSort('name')`


## Seeder ordering in DatabaseSeeder
Ensure new seeders are called before seeders that depend on them. Example:

```php
$this->call([
    CustomerGroupSeeder::class,
    CustomerStatusSeeder::class,
    TagSeeder::class,
    StoreChainSeeder::class, // new entity seeder here if others depend on it
    CustomerSeeder::class,
]);
```


## Conventions and notes
- Attributes: follow snake_case columns and PSR‑4 naming.
- API Resource nested relation keys should be camelCase (e.g., `storeChain`) when you include related resources via `whenLoaded()`.
- Return ISO‑8601 strings for timestamps via `->toISOString()`.
- Keep request alias mapping out unless explicitly required by the API contract; validate exactly the keys you expect.
- Use transactions when create/update spans multiple tables and must be atomic.
