<?php

namespace Test\app\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Test\app\Models\Comment;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition()
    {
        return [
            'content' => $this->faker->text
        ];
    }
}
