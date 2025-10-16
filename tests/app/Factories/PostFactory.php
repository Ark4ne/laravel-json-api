<?php

namespace Test\app\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Test\app\Models\Post;
use Test\app\Models\User;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->title(),
            'content' => $this->faker->text(),
            'is_public' => $this->faker->boolean(),
        ];
    }
}
