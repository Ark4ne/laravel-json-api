<?php

namespace Test\app\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'content' => $this->faker->text()
        ];
    }
}
