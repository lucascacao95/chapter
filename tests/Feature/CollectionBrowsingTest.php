<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_can_be_filtered_by_owned_collection_with_independent_pagination(): void
    {
        $collection = Collection::factory()->create();
        $books = Book::factory()->for($collection->user)->for($collection)->count(26)->create();
        $other = Collection::factory()->for($collection->user)->create();
        Book::factory()->for($collection->user)->for($other)->count(3)->create();
        Book::factory()->for($collection->user)->create();
        Book::factory()->create();
        $this->actingAs($collection->user)->getJson("/api/books?collection_id={$collection->id}")
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)
            ->assertJsonPath('data.0.id', $books->last()->id);
        $this->getJson("/api/books?collection_id={$collection->id}&page=2")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $books->first()->id);
        $this->getJson("/api/books?collection_id={$other->id}")->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/books')->assertJsonPath('meta.total', 30);
    }

    public function test_uncollected_filter_is_private_and_paginated(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->count(26)->create();
        $collection = Collection::factory()->for($user)->create();
        Book::factory()->for($user)->for($collection)->create();
        Book::factory()->create();
        $this->actingAs($user)->getJson('/api/books?collection_id=none&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 26)
            ->assertJsonPath('data.0.collection_id', null);
    }

    public function test_empty_collection_is_valid_and_invalid_filters_are_indistinguishable(): void
    {
        $collection = Collection::factory()->create();
        $foreign = Collection::factory()->create();
        $this->actingAs($collection->user)->getJson("/api/books?collection_id={$collection->id}")
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        foreach ([$foreign->id, 999999, 'abc', '', '0', '-1'] as $filter) {
            $this->getJson('/api/books?collection_id='.urlencode((string) $filter))
                ->assertUnprocessable()->assertJsonPath('errors.collection_id.0', 'Selecione uma coleção válida.');
        }
        $this->getJson('/api/books?collection_id[]=1')->assertUnprocessable()->assertJsonValidationErrors('collection_id');
    }

    public function test_collection_counts_include_empty_collections_and_only_owned_books(): void
    {
        $collection = Collection::factory()->create();
        $empty = Collection::factory()->for($collection->user)->create();
        Book::factory()->for($collection->user)->for($collection)->count(3)->create();
        Book::factory()->for($collection->user)->count(2)->create();
        Book::factory()->count(5)->create();
        Collection::factory()->create();
        $this->actingAs($collection->user)->getJson('/api/collections')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $empty->id)
            ->assertJsonPath('data.0.books_count', 0)->assertJsonPath('data.1.books_count', 3)
            ->assertJsonPath('meta.total_books_count', 5)->assertJsonPath('meta.uncollected_books_count', 2);
    }

    public function test_counts_refresh_after_assignment_removal_and_creation(): void
    {
        $collection = Collection::factory()->create();
        $book = Book::factory()->for($collection->user)->create();
        $this->actingAs($collection->user)->patchJson("/api/books/{$book->id}", ['collection_id' => $collection->id])->assertOk();
        $this->getJson('/api/collections')->assertJsonPath('data.0.books_count', 1)->assertJsonPath('meta.uncollected_books_count', 0);
        $this->patchJson("/api/books/{$book->id}", ['collection_id' => null])->assertOk();
        $this->getJson('/api/collections')->assertJsonPath('data.0.books_count', 0)->assertJsonPath('meta.uncollected_books_count', 1);
        $this->postJson('/api/collections', ['name' => 'Nova'])->assertCreated();
        $this->getJson('/api/collections')->assertJsonPath('data.0.books_count', 0)->assertJsonPath('meta.total', 2);
    }

    public function test_headers_remain_paginated_without_per_collection_book_queries(): void
    {
        $user = User::factory()->create();
        Collection::factory()->for($user)->count(26)->create();
        $this->actingAs($user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/collections')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26);
        $bookQueries = collect(DB::getQueryLog())->filter(fn ($entry) => str_contains($entry['query'], '"books"'))->count();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(3, $bookQueries);
        $this->getJson('/api/collections?page=2')->assertOk()->assertJsonCount(1, 'data');
    }
}
