<?php

declare(strict_types=1);

namespace Test\Feature;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\Support\JsonApiSchema;
use Test\Unit\Compilation\Fixtures\MergeWhenResource;

/**
 * The members of `mergeWhen()` are missing from the definition, but rendered as Laravel renders them.
 */
final class MergeWhenTest extends FeatureTestCase
{
    public function test_the_members_merged_when_the_condition_holds_are_rendered(): void
    {
        $post = Post::query()->firstOrFail();

        $response = MergeWhenResource::make($post)->toResponse(new Request());

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        $document = TestResponse::fromBaseResponse($response);
        $document->assertJsonPath('data.attributes', [
            'title' => $post->title,
            'shown' => null,
            'unless' => null,
            'merged' => null,
        ]);
        self::assertSame(['comments'], array_keys((array) $document->json('data.relationships')));
        // The ids are rendered as strings.
        self::assertEquals(
            Comment::query()->where('post_id', $post->id)->pluck('id')->all(),
            array_column((array) $document->json('data.relationships.comments.data'), 'id'),
        );
    }
}
