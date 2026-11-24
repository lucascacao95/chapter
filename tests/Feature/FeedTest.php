<?php

namespace Tests\Feature;

use App\Jobs\DeleteBookObjects;
use App\Jobs\PublishFeedActivity;
use App\Models\Book;
use App\Models\FeedActivity;
use App\Models\User;
use App\Services\Feed\RequestFeedPublication;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    private function share(Book $book, ?float $rating = null, ?string $review = null): FeedActivity
    {
        $this->actingAs($book->user);
        if ($rating === null) {
            $activity = app(RequestFeedPublication::class)->handle($book);
        } else {
            $id = $this->postJson("/api/books/{$book->id}/ratings", ['rating' => $rating, 'review' => $review, 'share_to_feed' => true])
                ->assertCreated()->json('data.feed_activity.id');
            $activity = FeedActivity::findOrFail($id);
        }
        (new PublishFeedActivity($activity->id))->handle();

        return $activity->refresh();
    }

    public function test_guest_endpoints_are_protected_and_authenticated_home_is_the_feed(): void
    {
        $this->getJson('/api/feed')->assertUnauthorized();
        $this->getJson('/api/feed-activities/1/cover')->assertUnauthorized();
        $this->deleteJson('/api/feed-activities/1')->assertUnauthorized();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertOk();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('recent_books', 0));
    }

    public function test_manual_books_and_ratings_are_private_unless_each_creation_opts_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/books', ['title' => 'Privado'])->assertCreated()->assertJsonPath('data.feed_activity', null);
        $book = Book::sole();
        $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 4])->assertCreated()->assertJsonPath('data.feed_activity', null);
        $this->assertDatabaseCount('feed_activities', 0);
        $this->postJson('/api/books', ['title' => 'Compartilhado', 'share_to_feed' => true])->assertCreated()
            ->assertJsonPath('data.feed_activity.status', 'pending');
        $first = $this->share($book, 0, 'Original');
        $second = $this->share($book, 4.5, 'Outra leitura');
        $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 2, 'review' => 'Privado'])->assertCreated();
        $this->getJson('/api/feed')->assertJsonCount(2, 'data');
        $this->assertSame('published', $first->fresh()->status);
        $this->assertSame('published', $second->fresh()->status);
        $this->assertDatabaseCount('book_ratings', 4);
        Queue::assertPushed(PublishFeedActivity::class, 3);
        $this->patchJson("/api/books/{$book->id}", ['share_to_feed' => true])->assertUnprocessable();
        foreach (['yes', null, []] as $invalid) {
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 2, 'share_to_feed' => $invalid])->assertUnprocessable();
        }
    }

    public function test_feed_is_directional_includes_own_and_historical_shared_items_only(): void
    {
        $viewer = User::factory()->create();
        $followed = Book::factory()->create();
        $follower = Book::factory()->create();
        $stranger = Book::factory()->create();
        $own = Book::factory()->for($viewer)->create();
        $visible = $this->share($followed, 4.5, 'Histórico compartilhado');
        $mine = $this->share($own);
        $this->share($follower);
        $this->share($stranger);
        $viewer->following()->attach($followed->user_id);
        $follower->user->following()->attach($viewer->id);
        $this->assertTrue($viewer->following->sole()->is($followed->user));
        $this->assertTrue($followed->user->followers->sole()->is($viewer));
        $this->actingAs($viewer)->getJson('/api/feed')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $mine->id)->assertJsonPath('data.1.id', $visible->id)
            ->assertJsonMissingPath('data.1.actor.email')->assertJsonMissingPath('data.1.book.id')
            ->assertJsonMissingPath('data.1.book.file')->assertJsonMissingPath('data.1.source_key');
        $this->getJson("/api/books/{$followed->id}")->assertNotFound();
        $viewer->following()->detach($followed->user_id);
        $this->getJson('/api/feed')->assertJsonCount(1, 'data');
    }

    public function test_self_follows_and_duplicate_relations_are_rejected_and_cascade(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $viewer->following()->attach($other->id);
        foreach ([$viewer->id, $other->id] as $target) {
            try {
                DB::transaction(fn () => $viewer->following()->attach($target));
                $this->fail('Invalid follow was accepted');
            } catch (QueryException) {
                $this->assertDatabaseCount('user_follows', 1);
            }
        }
        $other->delete();
        $this->assertDatabaseCount('user_follows', 0);
    }

    public function test_publication_waits_for_processing_and_keeps_snapshots_across_retries(): void
    {
        $book = Book::factory()->create(['title' => 'Título extraído', 'authors' => ['Autora']]);
        $file = $book->file()->create(['original_name' => 'secret.epub', 'disk' => 'local', 'storage_key' => 'original.epub', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1, 'processing_status' => 'pending']);
        $activity = $this->share($book, 3.5, 'Versão original');
        $this->assertSame('pending', $activity->status);
        $this->getJson('/api/feed')->assertJsonCount(0, 'data');
        $file->update(['processing_status' => 'failed']);
        (new PublishFeedActivity($activity->id))->handle();
        $this->assertSame('failed', $activity->fresh()->status);
        $file->update(['processing_status' => 'completed']);
        $book->forceFill(['cover_path' => 'private-cover.jpg'])->save();
        Storage::disk('local')->put('private-cover.jpg', 'synthetic-image');
        (new PublishFeedActivity($activity->id))->handle();
        $activity->refresh();
        $this->assertSame('published', $activity->status);
        $this->assertNotSame($book->cover_path, $activity->cover_path);
        $this->assertSame('synthetic-image', Storage::disk('local')->get($activity->cover_path));
        $this->actingAs($book->user)->patchJson("/api/books/{$book->id}", ['title' => 'Editado', 'authors' => ['Outra']])->assertOk();
        Storage::disk('local')->put('private-cover.jpg', 'changed-image');
        (new PublishFeedActivity($activity->id))->handle();
        $this->getJson('/api/feed')->assertJsonPath('data.0.book.title', 'Título extraído')
            ->assertJsonPath('data.0.book.authors', ['Autora'])->assertJsonPath('data.0.rating.value', 3.5)
            ->assertJsonPath('data.0.rating.review', 'Versão original');
        $this->assertSame('synthetic-image', Storage::disk('local')->get($activity->cover_path));
        $this->assertDatabaseCount('feed_activities', 1);
    }

    public function test_cover_authorization_withdrawal_and_cleanup_do_not_modify_private_source(): void
    {
        $book = Book::factory()->create();
        $activity = $this->share($book, 4.5, 'Comentário');
        Storage::disk('local')->put('feed-covers/test.jpg', 'image');
        $activity->update(['cover_disk' => 'local', 'cover_path' => 'feed-covers/test.jpg']);
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get("/api/feed-activities/{$activity->id}/cover")->assertNotFound();
        $viewer->following()->attach($book->user_id);
        $this->get("/api/feed-activities/{$activity->id}/cover")->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->deleteJson("/api/feed-activities/{$activity->id}")->assertNotFound();
        $viewer->following()->detach($book->user_id);
        $this->get("/api/feed-activities/{$activity->id}/cover")->assertNotFound();
        $this->actingAs($book->user)->deleteJson("/api/feed-activities/{$activity->id}")->assertNoContent();
        $this->deleteJson("/api/feed-activities/{$activity->id}")->assertNoContent();
        (new PublishFeedActivity($activity->id))->handle();
        $this->assertSame('withdrawn', $activity->fresh()->status);
        $this->get("/api/feed-activities/{$activity->id}/cover")->assertNotFound();
        $this->getJson('/api/feed')->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('book_ratings', ['id' => $activity->book_rating_id, 'rating' => 4.5, 'review' => 'Comentário']);
        Queue::assertPushed(DeleteBookObjects::class, fn ($job) => in_array('feed-covers/test.jpg', $job->keys));
    }

    public function test_deleted_sources_are_not_republished_and_their_copies_are_cleaned(): void
    {
        foreach (['book', 'user'] as $target) {
            $book = Book::factory()->create();
            $activity = $this->share($book);
            $activity->update(['cover_disk' => 'local', 'cover_path' => "feed-covers/{$activity->id}.jpg"]);
            if ($target === 'book') {
                $this->actingAs($book->user)->deleteJson("/api/books/{$book->id}")->assertNoContent();
            } else {
                DB::transaction(fn () => $book->user->delete());
            }
            (new PublishFeedActivity($activity->id))->handle();
            $this->assertDatabaseMissing('feed_activities', ['id' => $activity->id]);
            Queue::assertPushed(DeleteBookObjects::class, fn ($job) => in_array("feed-covers/{$activity->id}.jpg", $job->keys));
        }
    }

    public function test_cursor_pages_are_stable_and_do_not_leak_unfollowed_activities(): void
    {
        $book = Book::factory()->create();
        $this->freezeTime();
        for ($i = 0; $i < 42; $i++) {
            $this->share($book, 4.5);
        }
        $this->actingAs($book->user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson('/api/feed')->assertOk()->assertJsonCount(20, 'data');
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $new = $this->share($book, 5);
        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(20, 'data');
        $third = $this->getJson($second->json('links.next'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('links.next', null);
        $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'), array_column($third->json('data'), 'id'));
        $this->assertCount(42, array_unique($ids));
        $this->assertNotContains($new->id, $ids);
        $this->getJson('/api/feed?cursor=invalid')->assertUnprocessable();
    }

    public function test_recent_books_include_private_ratings_without_touching_book_timestamp(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->subDays(2));
        $old = Book::factory()->for($user)->create(['title' => 'Avaliado depois']);
        $originalDate = $old->updated_at->toISOString();
        $this->travel(1)->days();
        $new = Book::factory()->for($user)->create();
        $third = Book::factory()->for($user)->create();
        Book::factory()->create();
        $this->travel(1)->days();
        $this->actingAs($user)->postJson("/api/books/{$old->id}/ratings", ['rating' => 0])->assertCreated();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('recent_books', 3)
            ->where('recent_books.0.id', $old->id)->where('recent_books.1.id', $third->id)->where('recent_books.2.id', $new->id));
        $this->assertSame($originalDate, $old->fresh()->updated_at->toISOString());
    }
}
