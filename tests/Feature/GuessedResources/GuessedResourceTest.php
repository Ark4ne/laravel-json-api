<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources;

use Ark4ne\JsonApi\Descriptors\Relations\GuessedResource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\Feature\FeatureTestCase;
use Test\Feature\GuessedResources\Fixtures\AdminGuessedPostResource;
use Test\Feature\GuessedResources\Fixtures\Article;
use Test\Feature\GuessedResources\Fixtures\GuessedPostResource;
use Test\Feature\GuessedResources\Fixtures\NotEloquentResource;
use Test\Feature\GuessedResources\Fixtures\UnguessableResource;
use Test\Feature\GuessedResources\Fixtures\WriterResource;
use Test\Support\JsonApiSchema;

/**
 * `$this->one()` / `$this->one('author')` without resource class: the resource is guessed from the Eloquent model.
 */
final class GuessedResourceTest extends FeatureTestCase
{
    public function test_the_model_of_a_resource_following_the_convention_is_found(): void
    {
        self::assertSame(UserResource::class, GuessedResource::of(PostResource::class, 'user'));
        self::assertSame(CommentResource::class, GuessedResource::of(PostResource::class, 'comments'));
    }

    public function test_the_model_declared_by_attribute_and_the_resource_declared_by_the_related_model_win(): void
    {
        self::assertSame(UserResource::class, GuessedResource::of(GuessedPostResource::class, 'user'));
        self::assertSame(WriterResource::class, GuessedResource::of(GuessedPostResource::class, 'writer'));
    }

    public function test_the_definition_records_the_guessed_resources(): void
    {
        $definition = GuessedPostResource::definition();

        self::assertSame([UserResource::class], $definition->relationship('user')?->related);
        self::assertSame([UserResource::class], $definition->relationship('author')?->related);
        self::assertSame('user', $definition->relationship('author')->source);
        self::assertSame([CommentResource::class], $definition->relationship('comments')?->related);
        self::assertSame([WriterResource::class], $definition->relationship('writer')?->related);
        self::assertSame([], $definition->diagnostics);
    }

    public function test_the_guessed_resources_render_and_are_included(): void
    {
        $article = Article::query()->firstOrFail();

        $response = GuessedPostResource::make($article)
            ->toResponse(Request::create('/', 'GET', ['include' => 'author,writer,comments']));

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        $document = TestResponse::fromBaseResponse($response);
        $user = (string) $article->user->id;
        $document->assertJsonPath('data.relationships.author.data', ['type' => 'user', 'id' => $user]);
        $document->assertJsonPath('data.relationships.writer.data', ['type' => 'writer', 'id' => $user]);
        self::assertContains('writer', array_column((array) $document->json('included'), 'type'));
        self::assertContains('comment', array_column((array) $document->json('included'), 'type'));
    }

    public function test_a_resource_without_model_cannot_guess(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('pass the resource class');

        GuessedResource::of(UnguessableResource::class, 'user');
    }

    public function test_a_model_that_is_not_an_eloquent_model_cannot_guess(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('not an Eloquent model');

        GuessedResource::of(NotEloquentResource::class, 'user');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unguessable(): iterable
    {
        yield 'no such method' => ['missing', 'no relation'];
        yield 'not a relation' => ['notARelation', 'no relation'];
        yield 'polymorphic' => ['commentable', 'polymorphic'];
        yield 'model without resource' => ['unrendered', 'no resource'];
        yield 'method of Eloquent, never called' => ['push', 'no relation'];
        yield '#[UseResource] that is not a JSON:API resource' => ['plain', 'not a JSON:API resource'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unguessable')]
    public function test_a_relation_that_does_not_give_a_resource_cannot_guess(string $relation, string $reason): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage($reason);

        GuessedResource::of(GuessedPostResource::class, $relation);
    }

    public function test_an_unguessable_relationship_is_an_error_of_the_definition(): void
    {
        $diagnostics = UnguessableResource::definition()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertStringContainsString('pass the resource class', $diagnostics[0]->message);
    }

    public function test_a_subclass_keeps_the_model_of_its_parent(): void
    {
        self::assertSame(WriterResource::class, GuessedResource::of(AdminGuessedPostResource::class, 'writer'));
        self::assertSame([UserResource::class], AdminGuessedPostResource::definition()->relationship('author')?->related);
    }
}
