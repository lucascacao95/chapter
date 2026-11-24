<?php

namespace App\Jobs;

use App\Mail\KindleBookMail;
use App\Models\KindleDelivery;
use App\Services\Kindle\KindleConfiguration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SendBookToKindle implements ShouldQueue
{
    use Queueable;

    // SMTP offers no idempotency guarantee. Never repeat an ambiguous submission automatically.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $claimed = KindleDelivery::whereKey($this->deliveryId)->where('status', 'queued')->update(['status' => 'processing', 'error_code' => null, 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $attempted = false;
        $temp = null;
        try {
            $delivery = KindleDelivery::with('bookFile.book')->find($this->deliveryId);
            if (! $delivery) {
                return;
            }
            $file = $delivery->bookFile;
            if (! $file || ! $file->book || $file->processing_status !== 'completed'
                || ! in_array($file->format, ['epub', 'pdf'], true)
                || $file->size_bytes > config('kindle.max_attachment_bytes')) {
                throw new RuntimeException('file_unavailable');
            }
            if (! app(KindleConfiguration::class)->ready()) {
                throw new RuntimeException('configuration_missing');
            }
            $temp = tmpfile();
            if ($temp === false) {
                throw new RuntimeException('file_unavailable');
            }
            $source = Storage::disk($file->disk)->readStream($file->storage_key);
            if ($source === false) {
                throw new RuntimeException('file_unavailable');
            }
            try {
                if (stream_copy_to_stream($source, $temp, $file->size_bytes + 1) !== $file->size_bytes) {
                    throw new RuntimeException('file_unavailable');
                }
            } finally {
                fclose($source);
            }
            $path = stream_get_meta_data($temp)['uri'];
            $mail = new KindleBookMail($path, 'livro-'.$file->book_id.'.'.$file->format, $file->format, $file->book->title);
            $attempted = true;
            $sent = Mail::mailer('kindle')->to($delivery->recipient)->send($mail);
            if ($sent === null) {
                throw new RuntimeException('smtp_not_accepted');
            }
            KindleDelivery::whereKey($delivery->id)->update(['status' => 'submitted', 'submitted_at' => now(), 'error_code' => null, 'updated_at' => now()]);
        } catch (Throwable) {
            KindleDelivery::whereKey($this->deliveryId)->update(['status' => 'failed', 'error_code' => $attempted ? 'submission_unknown' : 'send_unavailable', 'updated_at' => now()]);
            // Do not persist SMTP transcripts, credentials, recipient addresses or file content in exception logs.
            throw new RuntimeException('kindle_submission_failed');
        } finally {
            if (is_resource($temp)) {
                fclose($temp);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        KindleDelivery::whereKey($this->deliveryId)->whereIn('status', ['queued', 'processing'])
            ->update(['status' => 'failed', 'error_code' => 'submission_unknown', 'updated_at' => now()]);
    }
}
