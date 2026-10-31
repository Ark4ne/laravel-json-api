<?php

declare(strict_types=1);

namespace Test\Feature\Links;

use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\Feature\FeatureTestCase;
use Test\Feature\Links\Fixtures\LinkedPostResource;
use Test\Support\JsonApiSchema;

/**
 * `->links(self: …, related: …)` declares the links of a relationship object by name.
 */
final class RelationshipLinksTest extends FeatureTestCase
{
    public function test_the_links_declared_by_name_are_rendered(): void
    {
        $post = Post::query()->firstOrFail();

        $response = LinkedPostResource::make($post)->toResponse(new Request());

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        $document = TestResponse::fromBaseResponse($response);
        $document->assertJsonPath('data.relationships.user.links', [
            'self' => "https://api.example.com/posts/{$post->id}/relationships/user",
            'related' => 'https://api.example.com/users',
        ]);
        $document->assertJsonPath('data.relationships.replies.links', [
            'related' => "https://api.example.com/posts/{$post->id}/comments",
        ]);
    }

    public function test_the_links_declared_by_name_win_over_those_of_the_closure_but_a_null_one_does_not(): void
    {
        $post = Post::query()->firstOrFail();

        $response = LinkedPostResource::make($post)->toResponse(new Request());

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        $document = TestResponse::fromBaseResponse($response);
        $document->assertJsonPath('data.relationships.comments.links', [
            'self' => "https://api.example.com/posts/{$post->id}/relationships/comments",
            'related' => 'https://api.example.com/comments',
        ]);
    }

    public function test_the_definition_records_the_links_declared_by_name(): void
    {
        $relationships = LinkedPostResource::definition()->relationships;

        self::assertTrue($relationships['user']->hasLinks);
        self::assertSame(['self', 'related'], $relationships['user']->namedLinks);
        // A link declared by name is recorded even when its closure may give `null`.
        self::assertSame(['self', 'related'], $relationships['comments']->namedLinks);
        self::assertSame(['related'], $relationships['replies']->namedLinks);
    }

    public function test_links_without_any_link_throw(): void
    {
        $this->expectException(DefinitionException::class);

        (new RelationOne(UserResource::class, null))->links();
    }

    public function test_the_links_of_a_relationship_without_any_link_throw(): void
    {
        $this->expectException(DefinitionException::class);

        (new Relationship(CommentResource::class, static fn() => null))->links();
    }
}
