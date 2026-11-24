<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_collection_endpoints_require_authentication(): void
    {
        $this->getJson('/api/collections')->assertUnauthorized();
        $this->postJson('/api/collections', ['name' => 'Leituras'])->assertUnauthorized();
        $this->getJson('/api/collections/1')->assertUnauthorized();
        $this->patchJson('/api/collections/1', ['name' => 'Leituras'])->assertUnauthorized();
        $this->deleteJson('/api/collections/1')->assertUnauthorized();
    }

    public function test_user_can_create_consult_rename_and_delete_a_collection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $created = $this->postJson('/api/collections', ['name' => '  Ficção brasileira  '])
            ->assertCreated()->assertJsonPath('data.name', 'Ficção brasileira')
            ->assertJsonStructure(['data' => ['id', 'name']]);

        $id = $created->json('data.id');
        $this->assertIsInt($id);
        $this->assertDatabaseHas('collections', ['id' => $id, 'user_id' => $user->id, 'name' => 'Ficção brasileira']);
        $this->getJson("/api/collections/{$id}")->assertOk()
            ->assertExactJson(['data' => ['id' => $id, 'name' => 'Ficção brasileira']]);
        $this->patchJson("/api/collections/{$id}", ['name' => '  Favoritos  '])->assertOk()
            ->assertExactJson(['data' => ['id' => $id, 'name' => 'Favoritos']]);
        $this->assertDatabaseHas('collections', ['id' => $id, 'name' => 'Favoritos', 'user_id' => $user->id]);
        $this->deleteJson("/api/collections/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('collections', ['id' => $id]);
    }

    public function test_listing_is_paginated_in_descending_id_order_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $collections = Collection::factory()->for($user)->count(26)->create();
        Collection::factory()->count(2)->create();
        $ids = $collections->pluck('id')->reverse()->values();

        $this->actingAs($user)->getJson('/api/collections')->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('data.0.id', $ids[0])
            ->assertJsonPath('data.24.id', $ids[24])
            ->assertJsonMissingPath('data.0.user_id');
        $this->getJson('/api/collections?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[25]);
    }

    public function test_listing_without_collections_is_empty(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/collections')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_other_users_collections_cannot_be_accessed_or_changed(): void
    {
        $collection = Collection::factory()->create(['name' => 'Privada']);
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/collections/{$collection->id}")->assertNotFound();
        $this->patchJson("/api/collections/{$collection->id}", ['name' => 'Outra'])->assertNotFound();
        $this->patchJson("/api/collections/{$collection->id}", ['name' => ''])->assertNotFound();
        $this->deleteJson("/api/collections/{$collection->id}")->assertNotFound();
        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'name' => 'Privada', 'user_id' => $collection->user_id]);
    }

    public function test_missing_collections_return_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/collections/999')->assertNotFound();
        $this->patchJson('/api/collections/999', ['name' => 'Nova'])->assertNotFound();
        $this->deleteJson('/api/collections/999')->assertNotFound();
    }

    public function test_payload_cannot_choose_or_change_the_owner_or_id(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson('/api/collections', ['name' => 'Minha', 'user_id' => $other->id, 'id' => 12345])->assertCreated();
        $id = $response->json('data.id');
        $this->assertNotSame(12345, $id);
        $this->assertDatabaseHas('collections', ['id' => $id, 'user_id' => $user->id]);
        $this->patchJson("/api/collections/{$id}", ['name' => 'Renomeada', 'user_id' => $other->id, 'id' => 12345])->assertOk();
        $this->assertDatabaseHas('collections', ['id' => $id, 'name' => 'Renomeada', 'user_id' => $user->id]);
    }

    public function test_duplicate_names_are_allowed_on_creation_and_update(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/collections', ['name' => 'Favoritos'])->assertCreated();
        $this->postJson('/api/collections', ['name' => 'Favoritos'])->assertCreated();
        $response = $this->postJson('/api/collections', ['name' => 'Outro nome'])->assertCreated();
        $this->patchJson('/api/collections/'.$response->json('data.id'), ['name' => 'Favoritos'])->assertOk();
        $this->assertDatabaseCount('collections', 3);
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected_on_creation_and_update(mixed $name): void
    {
        $collection = Collection::factory()->create(['name' => 'Original']);
        $this->actingAs($collection->user);
        $this->postJson('/api/collections', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/collections/{$collection->id}", ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('collections', 1);
        $this->assertSame('Original', $collection->fresh()->name);
    }

    public static function invalidNames(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'array' => [[]],
            'number' => [123],
            'too long' => [str_repeat('á', 256)],
        ];
    }

    public function test_missing_name_is_rejected_and_errors_are_in_portuguese(): void
    {
        $collection = Collection::factory()->create();
        $this->actingAs($collection->user)->postJson('/api/collections', [])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Informe o nome da coleção.');
        $this->patchJson("/api/collections/{$collection->id}", [])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Informe o nome da coleção.');
    }

    public function test_name_with_255_characters_is_accepted(): void
    {
        $name = str_repeat('á', 255);
        $this->actingAs(User::factory()->create())->postJson('/api/collections', ['name' => $name])
            ->assertCreated()->assertJsonPath('data.name', $name);
    }

    public function test_relationships_and_cascade_deletion_preserve_other_users_collections(): void
    {
        $collection = Collection::factory()->create();
        $other = Collection::factory()->create();
        $user = $collection->user;
        $this->assertTrue($user->collections->sole()->is($collection));
        $user->delete();
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('collections', ['id' => $other->id]);
    }
}
