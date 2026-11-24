<?php

namespace Tests\Feature;

use App\Jobs\DeleteBookObjects;
use App\Jobs\ExtractBookMetadata;
use App\Jobs\PublishFeedActivity;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\FeedActivity;
use App\Models\User;
use App\Services\Books\PdfReader;
use App\Services\Books\PrepareBookCover;
use App\Services\Books\ReadEpubMetadata;
use App\Services\Feed\RequestFeedPublication;
use Database\Seeders\FeedDemoSeeder;
use Database\Seeders\Support\DemoBookFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function process(BookFile $file): void
    {
        (new ExtractBookMetadata($file))->handle(app(ReadEpubMetadata::class), app(PdfReader::class), app(PrepareBookCover::class));
    }

    public function test_real_epub_and_pdf_uploads_wait_for_processing_and_resume_both_activity_types(): void
    {
        Queue::fake();
        Mail::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['epub', 'pdf'] as $format) {
            $upload = app(DemoBookFiles::class)->make($format, 'Livro sintetico');
            try {
                $private = $this->post('/api/books/upload', ['file' => $upload], ['Accept' => 'application/json'])
                    ->assertCreated()->assertJsonPath('data.feed_activity', null);
                $response = $this->post('/api/books/upload', ['file' => $upload, 'share_to_feed' => '1'], ['Accept' => 'application/json'])
                    ->assertCreated()->assertJsonPath('data.feed_activity.status', 'pending');
            } finally {
                @unlink($upload->getPathname());
            }
            $book = Book::findOrFail($response->json('data.id'));
            $this->postJson("/api/books/{$book->id}/ratings", ['rating' => 4.5, 'share_to_feed' => true])->assertCreated();
            foreach ($book->feedActivities as $activity) {
                (new PublishFeedActivity($activity->id))->handle();
            }
            $this->assertSame(0, $book->feedActivities()->where('status', 'published')->count());
            $this->process($book->file);
            $this->assertSame('completed', $book->file->fresh()->processing_status);
            $this->assertNotNull($book->fresh()->cover_path);
            foreach ($book->feedActivities as $activity) {
                (new PublishFeedActivity($activity->id))->handle();
            }
            $this->assertSame(2, $book->feedActivities()->where('status', 'published')->count());
            $this->assertSame(0, Book::findOrFail($private->json('data.id'))->feedActivities()->count());
        }
        Mail::assertNothingSent();
        Queue::assertPushed(PublishFeedActivity::class);
    }

    public function test_terminal_processing_failure_and_failed_publisher_do_not_expose_content(): void
    {
        Queue::fake();
        $book = Book::factory()->create();
        $file = $book->file()->create(['original_name' => 'a.epub', 'disk' => 'local', 'storage_key' => 'absent', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1]);
        $this->actingAs($book->user)->postJson("/api/books/{$book->id}/ratings", ['rating' => 3, 'share_to_feed' => true])->assertCreated();
        (new ExtractBookMetadata($file))->failed(null);
        $activity = FeedActivity::sole();
        $this->assertSame('failed', $activity->status);
        $this->getJson('/api/feed')->assertJsonCount(0, 'data');
        $file->update(['processing_status' => 'completed']);
        (new PublishFeedActivity($activity->id))->failed(null);
        $this->assertSame('failed', $activity->fresh()->status);
        $this->deleteJson("/api/feed-activities/{$activity->id}")->assertNoContent();
        (new PublishFeedActivity($activity->id))->failed(null);
        $this->assertSame('withdrawn', $activity->fresh()->status);
    }

    public function test_publication_request_and_database_job_rollback_together(): void
    {
        $user = User::factory()->create();
        DB::beginTransaction();
        try {
            $this->actingAs($user)->postJson('/api/books', ['title' => 'Rollback', 'share_to_feed' => true])->assertCreated();
            $this->assertDatabaseCount('feed_activities', 1);
            $this->assertDatabaseCount('jobs', 1);
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('feed_activities', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_demo_seeder_is_repeatable_and_uses_real_fixtures_without_sending_mail(): void
    {
        Queue::fake();
        Mail::fake();
        Storage::fake('local');
        $existing = User::factory()->create();
        $book = Book::factory()->for($existing)->create();
        $this->seed(FeedDemoSeeder::class);
        $counts = [User::count(), Book::count(), FeedActivity::count(), DB::table('book_ratings')->count(), DB::table('user_follows')->count()];
        $this->seed(FeedDemoSeeder::class);
        $this->assertSame($counts, [User::count(), Book::count(), FeedActivity::count(), DB::table('book_ratings')->count(), DB::table('user_follows')->count()]);
        $this->assertSame(6, User::count());
        $this->assertTrue($book->fresh()->user->is($existing));
        foreach (BookFile::all() as $file) {
            $this->process($file);
        }
        foreach (FeedActivity::all() as $activity) {
            (new PublishFeedActivity($activity->id))->handle();
        }
        $viewer = User::where('email', 'ana@feed-demo.example.test')->sole();
        $this->assertGreaterThan(40, FeedActivity::visibleTo($viewer)->count());
        $this->actingAs($viewer)->getJson('/api/feed')->assertOk()->assertJsonCount(20, 'data');
        $this->assertGreaterThan(0, FeedActivity::where('status', 'withdrawn')->count());
        Mail::assertNothingSent();
    }

    public function test_demo_seeder_refuses_production_without_creating_accounts(): void
    {
        app()->instance('env', 'production');
        try {
            (new FeedDemoSeeder)->run();
            $this->fail('Seeder should refuse production');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('local ou testing', $error->getMessage());
            $this->assertDatabaseCount('users', 0);
        } finally {
            app()->instance('env', 'testing');
        }
    }

    public function test_publication_copy_failure_is_private_and_does_not_log_source_data(): void
    {
        Queue::fake();
        Storage::fake('local');
        $book = Book::factory()->create();
        $book->file()->create(['original_name' => 'secret.epub', 'disk' => 'local', 'storage_key' => 'secret.epub', 'format' => 'epub', 'mime_type' => 'application/epub+zip', 'size_bytes' => 1, 'processing_status' => 'completed']);
        $book->forceFill(['cover_path' => 'private-missing-cover.jpg'])->save();
        $activity = app(RequestFeedPublication::class)->handle($book);
        try {
            (new PublishFeedActivity($activity->id))->handle();
            $this->fail('Missing cover should fail publication');
        } catch (\RuntimeException $error) {
            $this->assertSame('feed_publication_failed', $error->getMessage());
        }
        (new PublishFeedActivity($activity->id))->failed(null);
        $this->assertSame('failed', $activity->fresh()->status);
        $this->actingAs($book->user)->getJson('/api/feed')->assertJsonCount(0, 'data');
        Queue::assertPushed(DeleteBookObjects::class);
        $this->assertSame([], Storage::disk('local')->allFiles('feed-covers'));
    }
}
