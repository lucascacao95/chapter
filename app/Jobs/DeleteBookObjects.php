<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteBookObjects implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $disk, public array $keys) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        if (! Storage::disk($this->disk)->delete($this->keys)) {
            throw new RuntimeException('object_cleanup_failed');
        }
    }
}
