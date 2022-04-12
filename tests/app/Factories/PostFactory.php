<?php

namespace Test\app\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Test\app\Models\Post;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition()
    {
        return [
            'title' => $this->faker->title,
            'content' => $this->faker->text,
        ];
    }
}
