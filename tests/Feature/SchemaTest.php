<?php

namespace Test\Feature;

use Ark4ne\JsonApi\Resource\JsonApiCollection;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;

class SchemaTest extends FeatureTestCase
{
    public function testSchema()
    {
        $user = (object)[
            'type' => 'user',
            'fields' => ['name', 'email'],
            'relationships' => [
                'posts' => null,
                'comments' => null,
            ]
        ];

        $post = (object)[
            'type' => 'post',
            'fields' => ['title', 'content'],
            'relationships' => [
                'user' => null,
                'comments' => null,
            ]
        ];

        $comment = (object)[
            'type' => 'comment',
            'fields' => ['content'],
            'relationships' => [
                'user' => null,
                'post' => null,
            ]
        ];

        $user->relationships['posts'] = $post;
        $user->relationships['comments'] = $comment;

        $post->relationships['user'] = $user;
        $post->relationships['comments'] = $comment;

        $comment->relationships['user'] = $user;
        $comment->relationships['post'] = $post;

        $this->assertEquals($user, UserResource::schema());
        $this->assertEquals($post, PostResource::schema());
        $this->assertEquals($comment, CommentResource::schema());

        $userCollection = new class(collect()) extends JsonApiCollection {
            public $collects = UserResource::class;
        };

        $this->assertEquals($user, $userCollection::schema());
    }
}
