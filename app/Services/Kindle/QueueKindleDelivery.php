<?php

namespace App\Services\Kindle;

use App\Jobs\SendBookToKindle;
use App\Models\Book;
use App\Models\KindleDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QueueKindleDelivery
{
    public function handle(Book $book, string $key): KindleDelivery
    {
        return DB::transaction(function () use ($book, $key) {
            $book = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();
            $file = $book->file()->lockForUpdate()->first();
            if (! $file) {
                $this->invalid('Este livro não possui um arquivo para envio.');
            }
            $previous = KindleDelivery::where('idempotency_key', $key)->first();
            if ($previous) {
                if ($previous->book_file_id !== $file->id) {
                    $this->invalid('Reabra a opção de envio e tente novamente.');
                }

                return $previous;
            }
            $active = $file->kindleDeliveries()->whereIn('status', ['queued', 'processing'])->latest('id')->first();
            if ($active) {
                return $active;
            }
            if (! app(KindleConfiguration::class)->ready()) {
                $this->invalid('O envio ao Kindle ainda não foi configurado. Preencha as credenciais do Gmail e o destinatário no ambiente.');
            }
            if ($file->processing_status !== 'completed') {
                $this->invalid('Aguarde a conclusão do processamento do arquivo antes de enviar.');
            }
            if (! in_array($file->format, ['epub', 'pdf'], true)) {
                $this->invalid('Somente EPUB e PDF são aceitos nesta etapa.');
            }
            if ($file->size_bytes > config('kindle.max_attachment_bytes')) {
                $this->invalid('Nesta POC, o limite de envio pelo Gmail é de 18 MB por arquivo.');
            }
            if (! Storage::disk($file->disk)->exists($file->storage_key)) {
                $this->invalid('O arquivo original não está disponível para envio.');
            }
            $delivery = $file->kindleDeliveries()->create(['idempotency_key' => $key, 'recipient' => config('kindle.recipient'), 'status' => 'queued']);
            SendBookToKindle::dispatch($delivery->id)->onConnection('database');

            return $delivery;
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['kindle' => $message]);
    }
}
