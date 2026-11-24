<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookCoverController extends Controller
{
    public function __invoke(Book $book): StreamedResponse
    {
        Gate::authorize('view', $book);
        $file = $book->file;
        abort_unless($file && $book->cover_path, 404);
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($book->cover_path), 404);

        return $disk->response($book->cover_path, 'capa.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
