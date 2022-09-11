<?php

namespace Test\Feature;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\Skeleton;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;

class SchemaTest extends FeatureTestCase
{
    public function testSchema()
    {
        $user = new Skeleton(UserResource::class, 'user', ['name', 'email', 'only-with-fields']);
        $post = new Skeleton(PostResource::class, 'post', ['title', 'content']);
        $comment = new Skeleton(CommentResource::class, 'comment', ['content']);

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
