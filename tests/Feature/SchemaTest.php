<?php

namespace Test\Feature;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\Skeleton;
use Illuminate\Database\Eloquent\Builder;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;

class SchemaTest extends FeatureTestCase
{
    public function testSchema()
    {
        $user = new Skeleton(UserResource::class, 'user', ['name', 'email', 'only-with-fields']);
        $post = new Skeleton(PostResource::class, 'post', ['state', 'title', 'content']);
        $comment = new Skeleton(CommentResource::class, 'comment', ['content']);

        $user->relationships['posts'] = $post;
        $user->relationships['comments'] = $comment;
        $user->loads['posts'] = 'posts';
        $user->loads['comments'] = [
            'comments' => fn(Builder $q) => $q->where('content', 'like', '%e%')
        ];

        $post->relationships['user'] = $user;
        $post->relationships['comments'] = $comment;
        $post->loads['user'] = 'user';
        $post->loads['comments'] = 'comments';

        $comment->relationships['user'] = $user;
        $comment->relationships['post'] = $post;
        $comment->loads['user'] = 'user';
        $comment->loads['post'] = 'post';

        $this->assertEquals($user, UserResource::schema());
        $this->assertEquals($post, PostResource::schema());
        $this->assertEquals($comment, CommentResource::schema());

        $userCollection = new class(collect()) extends JsonApiCollection {
            public $collects = UserResource::class;
        };

        $this->assertEquals($user, $userCollection::schema());
    }
}
