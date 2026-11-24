<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookRating;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_require_authentication_and_hide_foreign_books(): void
    {
        $book = Book::factory()->create();
        $url = "/api/books/{$book->id}/ratings";
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url, ['rating' => 3])->assertUnauthorized();
        $this->getJson('/api/book-rating-groups')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, ['rating' => -1])->assertNotFound();
        $this->getJson('/api/books/999999/ratings')->assertNotFound();
    }

    public function test_every_save_appends_a_version_and_latest_uses_id_even_when_dates_match(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        $this->getJson("/api/books/{$book->id}")->assertJsonPath('data.latest_rating', null);
        $this->freezeTime();
        $first = $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 0, 'review' => 'Primeira leitura'])
            ->assertCreated()->assertJsonPath('data.rating', 0)->assertJsonPath('data.book_file_id', null)->json('data');
        $last = $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 4.5, 'review' => null])
            ->assertCreated()->assertJsonPath('data.rating', 4.5)->json('data');
        $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 4.5])->assertCreated();
        $this->assertDatabaseCount('book_ratings', 3);
        $this->assertDatabaseHas('book_ratings', ['id' => $first['id'], 'rating' => 0, 'review' => 'Primeira leitura']);
        $this->getJson("/api/books/{$book->id}")->assertJsonPath('data.latest_rating.id', $last['id'] + 1);
        $this->getJson('/api/books')->assertJsonPath('data.0.latest_rating.id', $last['id'] + 1);
        $this->getJson("/api/books/{$book->id}/ratings")->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $last['id'] + 1)->assertJsonPath('data.2.id', $first['id']);
    }

    public function test_server_assigns_ownership_and_current_file_and_history_is_paginated(): void
    {
        $book = Book::factory()->create();
        $file = $book->file()->create(['original_name' => 'a.epub', 'disk' => 'local', 'storage_key' => 'a', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1]);
        $this->actingAs($book->user);
        for ($i = 0; $i < 26; $i++) {
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 5, 'user_id' => 999, 'book_id' => 999, 'book_file_id' => 999])->assertCreated();
        }
        $this->assertDatabaseHas('book_ratings', ['book_id' => $book->id, 'user_id' => $book->user_id, 'book_file_id' => $file->id]);
        $this->getJson("/api/books/{$book->id}/ratings?page=2")->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 26);
    }

    #[DataProvider('invalidRatings')]
    public function test_invalid_ratings_are_rejected(array $payload, string $field): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user)->postJson("/api/books/{$book->id}/ratings", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('book_ratings', 0);
    }

    public static function invalidRatings(): array
    {
        return [
            [[], 'rating'], [['rating' => null], 'rating'], [['rating' => -0.5], 'rating'],
            [['rating' => 5.5], 'rating'], [['rating' => 2.25], 'rating'], [['rating' => 'abc'], 'rating'],
            [['rating' => []], 'rating'], [['rating' => 2, 'review' => []], 'review'],
            [['rating' => 2, 'review' => str_repeat('á', 10001)], 'review'],
        ];
    }

    public function test_all_half_steps_and_maximum_review_are_accepted(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        for ($step = 0; $step <= 10; $step++) {
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => $step / 2, 'review' => str_repeat('á', 10000)])->assertCreated();
        }
    }

    public function test_groups_and_filters_use_only_latest_ratings_and_distinguish_zero_from_unrated(): void
    {
        $collection = Collection::factory()->create();
        $user = $collection->user;
        $this->actingAs($user);
        for ($step = 0; $step <= 10; $step++) {
            $book = Book::factory()->for($user)->for($collection)->create();
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 5])->assertCreated();
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => $step / 2])->assertCreated();
        }
        Book::factory()->for($user)->create();
        $foreign = Book::factory()->create();
        BookRating::create(['book_id' => $foreign->id, 'user_id' => $foreign->user_id, 'rating' => 5]);
        $groups = $this->getJson('/api/book-rating-groups')->assertOk()->assertJsonCount(7, 'data')->assertJsonPath('meta.total_books_count', 12);
        foreach ([5, 4, 3, 2, 1, 0] as $index => $band) {
            $expected = $band === 5 ? 1 : 2;
            $groups->assertJsonPath("data.{$index}.key", (string) $band)->assertJsonPath("data.{$index}.books_count", $expected);
            $this->getJson("/api/books?rating_band={$band}")->assertOk()->assertJsonPath('meta.total', $expected);
        }
        $groups->assertJsonPath('data.6.key', 'none')->assertJsonPath('data.6.books_count', 1);
        $this->getJson('/api/books?rating_band=none')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/books?rating_band=none&collection_id={$collection->id}")->assertJsonPath('meta.total', 0);
        $this->getJson("/api/books?rating_band=4&collection_id={$collection->id}")->assertJsonPath('meta.total', 2);
        foreach (['', '6', '-1', '4.5', 'abc', '0.5', 'none[]'] as $invalid) {
            $this->getJson('/api/books?rating_band='.urlencode($invalid))->assertUnprocessable()->assertJsonValidationErrors('rating_band');
        }
        $this->getJson('/api/books?rating_band[]=1')->assertUnprocessable();
    }

    public function test_band_pagination_movement_empty_groups_and_query_count(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->for($user)->count(26)->create();
        $this->actingAs($user);
        $this->getJson('/api/book-rating-groups')->assertJsonPath('data.0.books_count', 0)->assertJsonPath('data.6.books_count', 26);
        $this->getJson('/api/books?rating_band=none&page=2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $books->first()->id);
        foreach ($books as $book) {
            BookRating::create(['book_id' => $book->id, 'user_id' => $user->id, 'rating' => 4.5]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/books?rating_band=4')->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26);
        $this->assertLessThanOrEqual(10, count(DB::getQueryLog()));
        DB::flushQueryLog();
        $this->getJson('/api/book-rating-groups')->assertJsonPath('data.1.books_count', 26);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->postJson('/api/books/'.$books->first()->id.'/ratings', ['rating' => 0])->assertCreated();
        $this->getJson('/api/books?rating_band=4&page=2')->assertJsonCount(0, 'data')->assertJsonPath('meta.last_page', 1);
        $this->getJson('/api/book-rating-groups')->assertJsonPath('data.1.books_count', 25)->assertJsonPath('data.5.books_count', 1);
    }

    public function test_history_is_removed_with_book_or_account_including_books_with_files(): void
    {
        Queue::fake();
        foreach (['book', 'user'] as $target) {
            $book = Book::factory()->create();
            $file = $book->file()->create(['original_name' => 'a.epub', 'disk' => 'local', 'storage_key' => 'a', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1]);
            $rating = BookRating::create(['book_id' => $book->id, 'book_file_id' => $file->id, 'user_id' => $book->user_id, 'rating' => 2]);
            if ($target === 'book') {
                $this->actingAs($book->user)->deleteJson("/api/books/{$book->id}")->assertNoContent();
            } else {
                $book->user->delete();
            }
            $this->assertDatabaseMissing('book_ratings', ['id' => $rating->id]);
        }
    }

    public function test_adding_a_file_does_not_rewrite_the_file_reference_of_older_ratings(): void
    {
        $book = Book::factory()->create();
        $this->actingAs($book->user);
        $first = $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 2])->assertCreated()->json('data.id');
        $file = $book->file()->create(['original_name' => 'a.epub', 'disk' => 'local', 'storage_key' => 'a', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1]);
        $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 3])->assertCreated()->assertJsonPath('data.book_file_id', $file->id);
        $this->assertDatabaseHas('book_ratings', ['id' => $first, 'book_file_id' => null]);
        $this->getJson("/api/books/{$book->id}/ratings")->assertJsonPath('data.1.book_file_id', null);
    }

    public function test_referenced_files_cannot_be_deleted_individually(): void
    {
        $book = Book::factory()->create();
        $file = $book->file()->create(['original_name' => 'a.epub', 'disk' => 'local', 'storage_key' => 'a', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1]);
        BookRating::create(['book_id' => $book->id, 'book_file_id' => $file->id, 'user_id' => $book->user_id, 'rating' => 2]);
        $this->expectException(QueryException::class);
        $file->delete();
    }

    public function test_stored_versions_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $book = Book::factory()->create();
        $rating = BookRating::create(['book_id' => $book->id, 'user_id' => $book->user_id, 'rating' => 2]);
        $this->assertFalse($rating->update(['rating' => 4]));
        $this->assertFalse($rating->delete());
        $this->assertDatabaseHas('book_ratings', ['id' => $rating->id, 'rating' => 2]);
    }
}
