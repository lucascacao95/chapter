<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\BookFile;
use App\Services\Books\PdfReader;
use App\Services\Books\PrepareBookCover;
use App\Services\Books\ReadEpubMetadata;
use App\Services\Feed\RequestFeedPublication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExtractBookMetadata implements ShouldQueue
{
    use Queueable;

    public const FIELDS = ['title', 'authors', 'page_count', 'publisher', 'edition', 'description', 'language', 'published_year', 'isbn'];

    public int $tries = 3;

    public int $timeout = 60;

    public int $fileId;

    public array $initialMetadata;

    public function __construct(BookFile $file)
    {
        // Store identifiers only; deleted files are safely skipped when the worker runs.
        $this->fileId = $file->getKey();
        $this->initialMetadata = $file->book->only(self::FIELDS);
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('book-file:'.$this->fileId))->releaseAfter(10)->expireAfter(75)];
    }

    public function handle(ReadEpubMetadata $epub, PdfReader $pdf, PrepareBookCover $covers): void
    {
        $file = BookFile::find($this->fileId);
        if (! $file) {
            return;
        }
        if ($file->processing_status === 'completed') {
            app(RequestFeedPublication::class)->resume($file->book);

            return;
        }
        $file->update(['processing_status' => 'processing', 'processing_error_code' => null, 'processed_at' => null]);
        $temp = tmpfile();
        if ($temp === false) {
            throw new RuntimeException('metadata_extraction_failed');
        }
        $coverKey = null;
        $coverCommitted = false;
        try {
            $source = Storage::disk($file->disk)->readStream($file->storage_key);
            if ($source === false) {
                throw new RuntimeException('metadata_extraction_failed');
            }
            try {
                $copied = stream_copy_to_stream($source, $temp, $file->size_bytes + 1);
                if ($copied !== $file->size_bytes) {
                    throw new RuntimeException('metadata_extraction_failed');
                }
            } finally {
                fclose($source);
            }
            $path = stream_get_meta_data($temp)['uri'];
            if ($file->format === 'epub') {
                $result = $epub->read($path);
                $metadata = $result['metadata'];
                $cover = $covers->fromImage($result['cover']);
            } else {
                $metadata = $pdf->read($path);
                $cover = $covers->fromPdf($path);
            }
            $rules = [
                'title' => 'required|string|max:255', 'authors' => 'array|list|max:100',
                'authors.*' => 'required|string|max:255', 'page_count' => 'integer|min:1|max:2147483647',
                'publisher' => 'string|max:255', 'edition' => 'string|max:255', 'description' => 'string|max:10000',
                'language' => 'string|max:35', 'published_year' => 'integer|between:1,9999', 'isbn' => 'string|max:32',
            ];
            $valid = [];
            foreach ($metadata as $field => $value) {
                if (! in_array($field, self::FIELDS, true)) {
                    continue;
                }
                $fieldRules = [$field => $rules[$field]];
                if ($field === 'authors') {
                    $fieldRules['authors.*'] = $rules['authors.*'];
                }
                if (Validator::make([$field => $value], $fieldRules)->passes()) {
                    $valid[$field] = $value;
                }
            }
            DB::transaction(function () use ($file, $valid, $cover, &$coverKey) {
                // Lock in the same order as editing/deletion: book, then file.
                $book = Book::whereKey($file->book_id)->lockForUpdate()->first();
                $current = BookFile::whereKey($file->id)->lockForUpdate()->first();
                if (! $book || ! $current || $current->processing_status === 'completed') {
                    return;
                }
                foreach ($valid as $field => $value) {
                    if (! in_array($field, $current->metadata_overrides ?? [], true) && $book->getAttribute($field) === ($this->initialMetadata[$field] ?? null)) {
                        $book->setAttribute($field, $value);
                    }
                }
                if ($cover !== null && $book->cover_path === null) {
                    $coverKey = 'covers/'.$book->user_id.'/'.Str::uuid().'.jpg';
                    if (! Storage::disk($file->disk)->put($coverKey, $cover, ['visibility' => 'private'])) {
                        throw new RuntimeException('cover_storage_failed');
                    }
                    $book->cover_path = $coverKey;
                }
                $book->save();
                $current->update(['processing_status' => 'completed', 'processing_error_code' => null, 'processed_at' => now()]);
                app(RequestFeedPublication::class)->resume($book);
            });
            $coverCommitted = true;
        } catch (Throwable) {
            BookFile::whereKey($file->id)->where('processing_status', '!=', 'completed')->update([
                'processing_status' => $this->attempts() < $this->tries ? 'pending' : 'failed',
                'processing_error_code' => $this->attempts() < $this->tries ? null : 'metadata_extraction_failed',
            ]);
            // Do not serialize parser output, file contents or object keys into failed_jobs.
            throw new RuntimeException('metadata_extraction_failed');
        } finally {
            if ($coverKey !== null && ! $coverCommitted) {
                DeleteBookObjects::dispatch($file->disk, [$coverKey])->onConnection('database');
            }
            fclose($temp);
        }
    }

    public function failed(?Throwable $error): void
    {
        BookFile::whereKey($this->fileId)->where('processing_status', '!=', 'completed')
            ->update(['processing_status' => 'failed', 'processing_error_code' => 'metadata_extraction_failed']);
        $file = BookFile::find($this->fileId);
        if ($file && $file->processing_status === 'failed') {
            $file->book->feedActivities()->where('status', 'pending')->update(['status' => 'failed']);
        }
    }
}
