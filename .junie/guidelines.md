#Pulse Junie Guidelines

Pulse is a repository to handle Customers as a CMS....

Here local guidelines

My Laravel docs

....


Hello world

# API Scaffolding Guidelines

This guide describes the exact steps and copy-ready templates to introduce a brand‑new domain table with a full REST API (Laravel 12, PHP 8.2+).

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
- PostgreSQL‑safe FK/index teardown rules


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
- Entity name (Singular, StudlyCase), e.g. `Product`.
- Table name (snake_case plural), e.g. `products`.
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
- {Entity} → e.g. Product
- {entity} → e.g. product
- {table} → e.g. products


### 1) Migration: create table
Create with timestamps, columns, and constraints. Use `$table->primaryKey()` and `$table->tenant()` (macros provided by Bedrock) for standard entities. Example:

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
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table}');
    }
};
```

If you need to add a nullable FK column on another table, use a dedicated migration that drops FK → index → column in `down()`. Use `foreignUlid` for relations to standard entities.


### 2) Eloquent Model with PHPDoc
Standard entities should extend `TenantModel` from the Bedrock package.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Proxicore\Bedrock\Tenancy\Models\TenantModel;

/**
 * @property string $id (ULID)
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * // Add all scalar attributes below
 * @property string $sku
 * @property string $name
 * @property string|null $description
 *
 * // Add relation properties (read‑only) if any
 * @property-read \App\Models\Category|null $category
 *
 * @mixin \Eloquent
 */
class {Entity} extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```


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
            'sku' => strtoupper(fake()->bothify('PROD-####-????')),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
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
            ['sku' => 'TSHIRT-BLUE-L', 'name' => 'Blue T-Shirt Large'],
            ['sku' => 'MUG-WH-01', 'name' => 'White Ceramic Mug'],
        ];

        foreach ($presets as $data) {
            {Entity}::create($data);
        }

        {Entity}::factory()->count(10)->create();
    }
}
```


### 5) API Resource
Use ISO‑8601 for timestamps and include only the fields you want public. Use camelCase keys for nested related resources and `whenLoaded()` for consistency.

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
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
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            // Example of nested relation
            'category' => new CategoryResource($this->whenLoaded('category')),
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
            'sku' => ['required', 'string', 'max:100', 'unique:{table},sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'ulid', 'exists:categories,id'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return [
            'sku' => [
                'description' => 'Unique identifier for the {entity}.',
                'example' => 'PROD-123',
            ],
            'name' => [
                'description' => 'The {entity} name.',
                'example' => 'Wireless Headphones',
            ],
            'description' => [
                'description' => 'A detailed description of the {entity}.',
                'example' => 'High-quality noise-cancelling headphones.',
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
        'category',
    ];

    /**
     * List {entity}s
     *
     * Retrieve a paginated list of {entity}s. Supports filtering and sorting via Spatie Query Builder.
     *
     * @queryParam filter[name] string Filter by partial name match. Example: Wireless
     * @queryParam filter[sku] string Filter by exact SKU match. Example: PROD-123
     * @queryParam filter[id] string Filter by exact ULID. Example: 01jk...
     * @queryParam sort string Sortable fields: id, name, sku, created_at, updated_at. Prefix with '-' for descending. Default: name. Example: -created_at
     * @queryParam per_page integer Number of items per page. Default: 15. Example: 25
     */
    public function index(Request $request)
    {
        $items = QueryBuilder::for({Entity}::query()->with($this->relations))
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('sku'),
                AllowedFilter::exact('id'),
            ])
            ->allowedSorts(['id', 'name', 'sku', 'created_at', 'updated_at'])
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


### 8) Routes
Add an API resource route in `routes/api.php`:

```php
Route::apiResource('{table}', \App\Http\Controllers\Api\{Entity}Controller::class);
```


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
            'data' => [[ 'id', 'sku', 'name', 'created_at', 'updated_at' ]],
            'links', 'meta',
        ]);
    }

    public function test_index_can_filter_by_sku(): void
    {
        {Entity}::factory()->create(['sku' => 'UNIQUE-1']);
        {Entity}::factory()->create(['sku' => 'OTHER-2']);
        $response = $this->getJson('/api/{table}?filter[sku]=UNIQUE-1');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_store_creates_and_validates(): void
    {
        $payload = ['sku' => 'NEW-SKU', 'name' => 'New Product'];
        $res = $this->postJson('/api/{table}', $payload);
        $res->assertCreated()->assertJsonPath('data.sku', 'NEW-SKU');
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


## Docs, Testing, and Formatting
- Documentation (Scribe): `php artisan scribe:generate`.
- Tests: `composer test`.
- Code style: run `./vendor/bin/pint`.


## MySQL‑safe FK/index teardown rules
When adding FKs in migrations on MySQL, always:
- Name FK and index explicitly in `up()`.
- In `down()`, wrap in `Schema::withoutForeignKeyConstraints(function () { ... })` and drop in this order:
    1) `$table->dropForeign('<fk_name>');`
    2) `$table->dropIndex('<index_name>');`
    3) `$table->dropColumn('<column>');`
