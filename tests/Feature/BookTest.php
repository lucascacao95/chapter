<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_endpoints_require_authentication(): void
    {
        $this->getJson('/api/books')->assertUnauthorized();
        $this->postJson('/api/books', ['title' => 'Livro'])->assertUnauthorized();
        $this->getJson('/api/books/1')->assertUnauthorized();
        $this->patchJson('/api/books/1', ['title' => 'Livro'])->assertUnauthorized();
        $this->deleteJson('/api/books/1')->assertUnauthorized();
    }

    public function test_user_can_create_consult_update_and_delete_a_book_with_metadata(): void
    {
        $collection = Collection::factory()->create();
        $this->actingAs($collection->user);
        $data = [
            'title' => '  Leituras brasileiras  ',
            'authors' => ['  Autora Um  ', 'Autor Dois'],
            'page_count' => 320,
            'publisher' => 'Editora Exemplo',
            'edition' => '2ª edição revisada',
            'description' => 'Uma descrição do livro.',
            'language' => 'pt-BR',
            'published_year' => 2024,
            'isbn' => '978-85-00000-00-0',
            'collection_id' => $collection->id,
        ];
        $response = $this->postJson('/api/books', $data)->assertCreated()
            ->assertJsonPath('data.title', 'Leituras brasileiras')
            ->assertJsonPath('data.authors', ['Autora Um', 'Autor Dois']);
        $id = $response->json('data.id');
        $this->assertIsInt($id);
        $data['title'] = 'Leituras brasileiras';
        $data['authors'] = ['Autora Um', 'Autor Dois'];
        $this->getJson("/api/books/{$id}")->assertOk()->assertJson(['data' => $data])
            ->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']])
            ->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.cover_path');
        $this->assertDatabaseHas('books', ['id' => $id, 'user_id' => $collection->user_id, 'collection_id' => $collection->id]);
        $this->patchJson("/api/books/{$id}", ['title' => 'Novo título'])->assertOk()->assertJsonPath('data.title', 'Novo título');
        $this->deleteJson("/api/books/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $id]);
        $this->assertDatabaseHas('collections', ['id' => $collection->id]);
    }

    public function test_only_title_is_required_and_optional_metadata_defaults_to_null(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/books', ['title' => 'Livro'])
            ->assertCreated()->assertJsonPath('data.title', 'Livro')
            ->assertJsonPath('data.authors', null)->assertJsonPath('data.page_count', null)
            ->assertJsonPath('data.collection_id', null)->assertJsonPath('data.publisher', null)
            ->assertJsonPath('data.edition', null)->assertJsonPath('data.description', null)
            ->assertJsonPath('data.language', null)->assertJsonPath('data.published_year', null)
            ->assertJsonPath('data.isbn', null);
        $this->assertNull(Book::sole()->cover_path);
    }

    public function test_listing_is_paginated_ordered_and_private(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->for($user)->count(26)->create();
        Book::factory()->count(2)->create();
        $ids = $books->pluck('id')->reverse()->values();
        $this->actingAs($user)->getJson('/api/books')->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26)->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('data.24.id', $ids[24]);
        $this->getJson('/api/books?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[25]);
    }

    public function test_empty_library_returns_an_empty_list(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/books')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_other_users_books_are_not_accessible_even_with_invalid_payloads(): void
    {
        $book = Book::factory()->create(['title' => 'Privado']);
        $this->actingAs(User::factory()->create());
        $this->getJson("/api/books/{$book->id}")->assertNotFound();
        $this->patchJson("/api/books/{$book->id}", ['title' => 'Outro'])->assertNotFound();
        $this->patchJson("/api/books/{$book->id}", ['title' => ''])->assertNotFound();
        $this->deleteJson("/api/books/{$book->id}")->assertNotFound();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Privado', 'user_id' => $book->user_id]);
    }

    public function test_missing_books_return_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/books/999')->assertNotFound();
        $this->patchJson('/api/books/999', ['title' => 'Livro'])->assertNotFound();
        $this->deleteJson('/api/books/999')->assertNotFound();
    }

    public function test_owner_id_and_internal_cover_reference_cannot_be_supplied_in_payload(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $data = ['title' => 'Meu livro', 'user_id' => $other->id, 'id' => 12345, 'cover_path' => 'private/other-user.jpg'];
        $response = $this->actingAs($user)->postJson('/api/books', $data)->assertCreated();
        $id = $response->json('data.id');
        $this->assertNotSame(12345, $id);
        $this->patchJson("/api/books/{$id}", $data)->assertOk();
        $this->assertDatabaseHas('books', ['id' => $id, 'user_id' => $user->id, 'cover_path' => null]);
    }

    public function test_collection_can_be_changed_or_removed_without_replacing_other_metadata(): void
    {
        $first = Collection::factory()->create();
        $second = Collection::factory()->for($first->user)->create();
        $book = Book::factory()->for($first->user)->for($first)->create(['title' => 'Título', 'authors' => ['Autora'], 'description' => 'Descrição']);
        $this->actingAs($first->user)->patchJson("/api/books/{$book->id}", ['collection_id' => $second->id])
            ->assertOk()->assertJsonPath('data.collection_id', $second->id)->assertJsonPath('data.title', 'Título');
        $this->patchJson("/api/books/{$book->id}", ['collection_id' => null, 'description' => null])
            ->assertOk()->assertJsonPath('data.collection_id', null)->assertJsonPath('data.description', null)
            ->assertJsonPath('data.authors', ['Autora'])->assertJsonPath('data.title', 'Título');
    }

    public function test_missing_or_other_users_collections_are_rejected_on_create_and_update(): void
    {
        $book = Book::factory()->create();
        $foreign = Collection::factory()->create();
        $this->actingAs($book->user);
        foreach ([$foreign->id, 999999] as $collectionId) {
            $this->postJson('/api/books', ['title' => 'Livro', 'collection_id' => $collectionId])
                ->assertUnprocessable()->assertJsonPath('errors.collection_id.0', 'Selecione uma coleção válida.');
            $this->patchJson("/api/books/{$book->id}", ['collection_id' => $collectionId])
                ->assertUnprocessable()->assertJsonValidationErrors('collection_id');
        }
        $this->assertDatabaseCount('books', 1);
        $this->assertNull($book->fresh()->collection_id);
    }

    public function test_deleting_a_collection_preserves_its_books_without_a_collection(): void
    {
        $collection = Collection::factory()->create();
        $book = Book::factory()->for($collection->user)->for($collection)->create();
        $this->actingAs($collection->user)->deleteJson("/api/collections/{$collection->id}")->assertNoContent();
        $this->getJson("/api/books/{$book->id}")->assertOk()->assertJsonPath('data.collection_id', null);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'user_id' => $book->user_id, 'collection_id' => null]);
    }

    public function test_relationships_and_user_deletion_preserve_other_users_books(): void
    {
        $collection = Collection::factory()->create();
        $book = Book::factory()->for($collection->user)->for($collection)->create();
        $other = Book::factory()->create();
        $this->assertTrue($book->user->books->sole()->is($book));
        $this->assertTrue($book->collection->is($collection));
        $this->assertTrue($collection->books->sole()->is($book));
        $book->user->delete();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseHas('books', ['id' => $other->id]);
    }

    public function test_duplicate_titles_and_isbns_are_allowed(): void
    {
        $this->actingAs(User::factory()->create());
        $data = ['title' => 'Título repetido', 'isbn' => '978-85-00000-00-0'];
        $this->postJson('/api/books', $data)->assertCreated();
        $this->postJson('/api/books', $data)->assertCreated();
        $this->assertDatabaseCount('books', 2);
    }

    #[DataProvider('invalidMetadata')]
    public function test_invalid_metadata_is_rejected_on_creation_and_update(string $field, mixed $value): void
    {
        $book = Book::factory()->create(['title' => 'Original']);
        $this->actingAs($book->user);
        $create = ['title' => 'Título'];
        $update = [];
        data_set($create, $field, $value);
        data_set($update, $field, $value);
        $this->postJson('/api/books', $create)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->patchJson("/api/books/{$book->id}", $update)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Original', $book->fresh()->title);
        $this->assertDatabaseCount('books', 1);
    }

    public static function invalidMetadata(): array
    {
        return [
            'empty title' => ['title', '  '],
            'null title' => ['title', null],
            'non textual title' => ['title', 123],
            'long title' => ['title', str_repeat('á', 256)],
            'authors must be a list' => ['authors', 'Autora'],
            'authors must not be an object' => ['authors', ['name' => 'Autora']],
            'too many authors' => ['authors', array_fill(0, 101, 'Autora')],
            'empty author' => ['authors.0', '  '],
            'non textual author' => ['authors.0', 123],
            'long author' => ['authors.0', str_repeat('a', 256)],
            'zero pages' => ['page_count', 0],
            'negative pages' => ['page_count', -1],
            'fractional pages' => ['page_count', 1.5],
            'overflow pages' => ['page_count', 2147483648],
            'long publisher' => ['publisher', str_repeat('a', 256)],
            'long edition' => ['edition', str_repeat('a', 256)],
            'long description' => ['description', str_repeat('a', 10001)],
            'long language' => ['language', str_repeat('a', 36)],
            'zero publication year' => ['published_year', 0],
            'large publication year' => ['published_year', 10000],
            'long isbn' => ['isbn', str_repeat('a', 33)],
            'invalid collection id' => ['collection_id', 'abc'],
        ];
    }

    public function test_title_is_required_on_creation_with_portuguese_error(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/books', [])
            ->assertUnprocessable()->assertJsonPath('errors.title.0', 'Informe o título do livro.');
    }

    public function test_array_and_numeric_validation_errors_are_in_portuguese(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/books', [
            'title' => 'Livro',
            'authors' => 'Autora',
            'page_count' => 0,
            'published_year' => 10000,
            'collection_id' => 'abc',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.authors.0', 'Os autores devem ser uma lista de nomes.')
            ->assertJsonPath('errors.page_count.0', 'A quantidade de páginas deve ser maior que zero.')
            ->assertJsonPath('errors.published_year.0', 'O ano de publicação deve estar entre 1 e 9999.')
            ->assertJsonPath('errors.collection_id.0', 'Selecione uma coleção válida.');
    }

    public function test_numeric_metadata_is_serialized_as_integers_and_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->create());
        $created = $this->postJson('/api/books', [
            'title' => str_repeat('á', 255),
            'page_count' => '200',
            'published_year' => '2024',
            'authors' => ['Autora'],
        ])->assertCreated()->assertJsonPath('data.page_count', 200)->assertJsonPath('data.published_year', 2024);
        $this->patchJson('/api/books/'.$created->json('data.id'), [
            'page_count' => null, 'published_year' => null, 'authors' => null,
        ])->assertOk()->assertJsonPath('data.page_count', null)
            ->assertJsonPath('data.published_year', null)->assertJsonPath('data.authors', null);
    }
}
