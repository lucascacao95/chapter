<?php

namespace App\Http\Controllers;

use App\Services\Feed\RecentBooks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, RecentBooks $books): Response
    {
        return Inertia::render('Dashboard', ['recent_books' => $books->forUser($request->user())]);
    }
}
