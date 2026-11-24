<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_home_page_renders_the_welcome_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_login_page_renders_the_login_screen(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }
}
