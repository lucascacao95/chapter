<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCoverController;
use App\Http\Controllers\BookRatingController;
use App\Http\Controllers\BookRatingGroupController;
use App\Http\Controllers\BookUploadController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedCoverController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KindleDeliveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (Request $request) => $request->user() ? redirect()->route('dashboard') : Inertia::render('Welcome'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:registration');
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:recovery')->name('password.email');
    Route::get('/reset-password/{token}', fn (Request $request, string $token) => Inertia::render('Auth/ResetPassword', ['token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']))->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:recovery')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::get('/api/feed', [FeedController::class, 'index'])->name('api.feed.index');
    Route::get('/api/feed-activities/{activity}/cover', FeedCoverController::class)->whereNumber('activity')->name('api.feed.cover');
    Route::delete('/api/feed-activities/{activity}', [FeedController::class, 'destroy'])->whereNumber('activity')->name('api.feed.destroy');
    Route::get('/books', fn () => Inertia::render('Books/Index'))->name('books.index');
    Route::get('/books/create', fn () => Inertia::render('Books/Create'))->name('books.create');
    Route::get('/api/book-rating-groups', BookRatingGroupController::class)->name('api.book-rating-groups');
    Route::get('/api/books/{book}/ratings', [BookRatingController::class, 'index'])->whereNumber('book')->name('api.books.ratings.index');
    Route::post('/api/books/{book}/ratings', [BookRatingController::class, 'store'])->whereNumber('book')->name('api.books.ratings.store');
    Route::get('/api/books/{book}/cover', BookCoverController::class)->whereNumber('book')->name('api.books.cover');
    Route::post('/api/books/{book}/kindle', [KindleDeliveryController::class, 'store'])->whereNumber('book')->middleware('throttle:10,1')->name('api.books.kindle');
    Route::post('/api/books/upload', BookUploadController::class)->middleware('throttle:30,1')->name('api.books.upload');
    Route::prefix('api/books')->name('api.books.')->controller(BookController::class)
        ->where(['book' => '[0-9]+'])->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{book}', 'show')->name('show');
            Route::patch('/{book}', 'update')->name('update');
            Route::delete('/{book}', 'destroy')->name('destroy');
        });
    Route::prefix('api/collections')->name('api.collections.')->controller(CollectionController::class)
        ->where(['collection' => '[0-9]+'])->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{collection}', 'show')->name('show');
            Route::patch('/{collection}', 'update')->name('update');
            Route::delete('/{collection}', 'destroy')->name('destroy');
        });
    Route::get('/dashboard', HomeController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
