<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\Revisions\UserResource;
use Test\app\Http\Resources\Revisions\WriterResource;
use Test\app\Models\Post;
use Test\app\Revisions\ApiRevision;
use Test\app\Schemas\PostResource;
use Test\Feature\Revisions\RevisionsTestCase;

/**
 * The relationships of a resource declared with the descriptors of `ark4ne/laravel-api-revisions`
 * (docs/rfc/api-revisions-integration.md D3, D6): the documents of the revisions scenario, unchanged.
 */
final class RelationshipsTest extends RevisionsTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        /** @var Router $router */
        $router->get('schemas/posts', static fn() => PostResource::collection(Post::query()->whereKey([1, 4])->get()))
            ->middleware('revision');
    }

    /**
     * @return iterable<string, array{ApiRevision|null, string}>
     */
    public static function documents(): iterable
    {
        yield 'head' => [null, 'include=user,comments.user'];
        yield 'head, nothing included' => [null, ''];
        yield 'Labels' => [ApiRevision::Labels, 'include=writer,comments.commenter'];
        yield 'Labels, a head path' => [ApiRevision::Labels, 'include=user'];
        yield 'Initial' => [ApiRevision::Initial, 'include=writer'];
        yield 'Labels, sparse' => [ApiRevision::Labels, 'include=writer&fields[posts]=writer&fields[writers]=email'];
    }

    #[DataProvider('documents')]
    public function test_the_document_and_its_queries_are_those_of_the_revisions_scenario(?ApiRevision $revision, string $query): void
    {
        [$expected, $expectedQueries] = $this->fetch("revisions/posts?{$query}", $revision);
        [$actual, $queries] = $this->fetch("schemas/posts?{$query}", $revision);

        self::assertSame(
            str_replace('revisions\/posts', 'schemas\/posts', (string) $expected->baseResponse->getContent()),
            (string) $actual->baseResponse->getContent(),
        );
        self::assertSame($expectedQueries, $queries);
    }

    public function test_the_relationships_of_the_definition_are_those_of_the_revision(): void
    {
        $labels = Definitions::for(PostResource::class, ApiRevision::Labels);
        $head = Definitions::for(PostResource::class, ApiRevision::UsersReplaceTeachers);

        self::assertSame(['writer', 'comments'], array_keys($labels->relationships));
        self::assertSame([WriterResource::class], $labels->relationships['writer']->related);
        self::assertSame(Cardinality::One, $labels->relationships['writer']->cardinality);
        self::assertSame(['title' => 'title', 'user' => 'writer', 'comments' => 'comments'], $labels->fieldNames);

        self::assertSame(['user', 'comments'], array_keys($head->relationships));
        self::assertSame([UserResource::class], $head->relationships['user']->related);
        self::assertSame(Cardinality::Many, $head->relationships['comments']->cardinality);
    }

    public function test_an_include_is_validated_in_the_names_of_the_revision(): void
    {
        self::assertSame([], self::ruleErrors('include', new Includes(PostResource::class, revision: ApiRevision::Labels), 'writer'));
        self::assertNotSame([], self::ruleErrors('include', new Includes(PostResource::class, revision: ApiRevision::Labels), 'user'));
        self::assertSame([], self::ruleErrors('include', new Includes(PostResource::class, revision: ApiRevision::UsersReplaceTeachers), 'user'));
    }
}
