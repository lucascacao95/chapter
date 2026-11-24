<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_and_creation_page_require_authentication(): void
    {
        $this->get('/books')->assertRedirect('/login');
        $this->get('/books/create')->assertRedirect('/login');
    }

    public function test_user_can_open_the_library(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/books?page=2')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Books/Index')
                ->where('auth.user.id', $user->id));
    }

    public function test_user_can_open_the_creation_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/books/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Books/Create'));
    }
}
