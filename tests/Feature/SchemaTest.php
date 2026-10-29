<?php

declare(strict_types=1);

namespace Test\Feature;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\Skeleton;
use Closure;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;

class SchemaTest extends FeatureTestCase
{
    public function testSchema(): void
    {
        $user = new Skeleton(UserResource::class, 'user', [
            'name',
            'email',
            'only-with-fields',
            'with-apply-conditional-raw',
            'with-apply-conditional-closure',
            'with-apply-conditional-value',
            'struct-set',
            'struct-set.name',
            'struct-set.email',
            'struct-set.casted',
            'struct-set.with-apply-conditional-raw',
            'struct-set.closure',
            'struct-set.missing',
            'struct-set.sub-struct.int',
            'struct-set.sub-struct.float',
            'struct-set.third-struct.int',
            'struct-set.third-struct.float',
        ]);
        $post = new Skeleton(PostResource::class, 'post', ['state', 'title', 'content']);
        $comment = new Skeleton(CommentResource::class, 'comment', ['content']);

        $user->relationships['main-post'] = $post;
        $user->relationships['posts'] = $post;
        $user->relationships['comments'] = $comment;
        $user->loads['main-post'] = 'post';
        $user->loads['posts'] = 'posts';
        $user->loads['comments'] = [
            'comments' => UserResource::schema()->loads['comments']['comments'],
        ];

        $post->relationships['user'] = $user;
        $post->relationships['comments'] = $comment;
        $post->loads['user'] = 'user';
        $post->loads['comments'] = 'comments';

        $comment->relationships['user'] = $user;
        $comment->relationships['post'] = $post;
        $comment->loads['user'] = 'user';
        $comment->loads['post'] = 'post';

        $this->assertEquals(self::normalize($user), self::normalize(UserResource::schema()));
        $this->assertEquals(self::normalize($post), self::normalize(PostResource::schema()));
        $this->assertEquals(self::normalize($comment), self::normalize(CommentResource::schema()));

        $userCollection = new class (collect()) extends JsonApiCollection {
            public $collects = UserResource::class;
        };

        $this->assertEquals(self::normalize($user), self::normalize($userCollection::schema()));
    }

    /**
     * The skeleton as an array, closures replaced by their identity: PHPUnit cannot compare closures by value.
     *
     * @param array<int, true> $seen
     * @return array<string, mixed>
     */
    private static function normalize(Skeleton $skeleton, array &$seen = []): array
    {
        if (isset($seen[spl_object_id($skeleton)])) {
            return ['ref' => $skeleton->for];
        }

        $seen[spl_object_id($skeleton)] = true;

        $loads = $skeleton->loads;
        array_walk_recursive($loads, static function (mixed &$value): void {
            if ($value instanceof Closure) {
                $value = 'closure#' . spl_object_id($value);
            }
        });

        return [
            'for' => $skeleton->for,
            'type' => $skeleton->type,
            'fields' => $skeleton->fields,
            'relationships' => array_map(static fn(Skeleton $relationship) => self::normalize($relationship, $seen), $skeleton->relationships),
            'loads' => $loads,
        ];
    }
}
