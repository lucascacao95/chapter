<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Book> */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'user_id' => User::factory(),
        ];
    }
}
