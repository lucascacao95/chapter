<?php

namespace App\Http\Controllers;

use App\Models\FeedActivity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedCoverController extends Controller
{
    public function __invoke(FeedActivity $activity): StreamedResponse
    {
        Gate::authorize('view', $activity);
        abort_unless($activity->cover_disk && $activity->cover_path, 404);
        $disk = Storage::disk($activity->cover_disk);
        abort_unless($disk->exists($activity->cover_path), 404);

        return $disk->response($activity->cover_path, 'capa.jpg', [
            'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
