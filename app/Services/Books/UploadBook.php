<?php

namespace App\Services\Books;

use App\Jobs\DeleteBookObjects;
use App\Jobs\ExtractBookMetadata;
use App\Models\Book;
use App\Models\User;
use App\Services\Feed\RequestFeedPublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadBook
{
    public function handle(User $user, UploadedFile $upload, ?int $collectionId, bool $shareToFeed = false): Book
    {
        $disk = config('books.disk');
        $format = strtolower($upload->getClientOriginalExtension());
        $key = 'originals/'.$user->id.'/'.Str::uuid().'.'.$format;
        $stream = fopen($upload->getRealPath(), 'rb');
        try {
            if ($stream === false) {
                throw new RuntimeException('storage_failed');
            }
            if (! Storage::disk($disk)->put($key, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('storage_failed');
            }
        } catch (Throwable $error) {
            $this->cleanup($disk, $key);
            throw $error;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        try {
            return DB::transaction(function () use ($user, $upload, $collectionId, $disk, $format, $key, $shareToFeed) {
                $name = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
                $title = trim(pathinfo($name, PATHINFO_FILENAME));
                $book = $user->books()->create(['title' => mb_substr($title !== '' ? $title : 'Livro sem título', 0, 255), 'collection_id' => $collectionId]);
                $book->file()->create([
                    'original_name' => $name, 'disk' => $disk, 'storage_key' => $key,
                    'format' => $format, 'mime_type' => $upload->getMimeType(), 'size_bytes' => $upload->getSize(),
                    'processing_status' => 'pending',
                ]);
                // Database queue insertion shares this transaction: either both records and the work exist, or none do.
                if ($shareToFeed) {
                    app(RequestFeedPublication::class)->handle($book);
                }
                ExtractBookMetadata::dispatch($book->file)->onConnection('database');

                return $book->refresh()->load(['file.latestKindleDelivery', 'latestRating.feedActivity', 'feedActivity']);
            });
        } catch (Throwable $error) {
            $this->cleanup($disk, $key);
            throw $error;
        }
    }

    private function cleanup(string $disk, string $key): void
    {
        try {
            if (Storage::disk($disk)->delete($key)) {
                return;
            }
        } catch (Throwable) {
            // Retry cleanup independently from the rejected upload.
        }
        DeleteBookObjects::dispatch($disk, [$key])->onConnection('database');
    }
}
