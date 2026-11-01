<?php

declare(strict_types=1);

namespace Test\Feature\Loading;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Models\Comment;
use Test\app\Models\Like;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;
use Test\Feature\Loading\Fixtures\EmptyMorphResource;
use Test\Feature\Loading\Fixtures\LinkageFeedResource;
use Test\Feature\Loading\Fixtures\LinkageLikeResource;
use Test\Feature\Loading\Fixtures\LinkagePostResource;
use Test\Support\JsonApiSchema;

/**
 * A polymorphic relationship (`morph()`) renders each related model with the resource of its class, and loads them
 * class by class.
 */
class MorphTest extends FeatureTestCase
{
    public function test_the_definition_relates_every_resource_of_the_map(): void
    {
        $likeable = LinkageLikeResource::definition()->relationships['likeable'];

        self::assertTrue($likeable->isPolymorphic());
        self::assertSame(
            [Post::class => LinkagePostResource::class, Comment::class => Fixtures\LinkageCommentResource::class],
            $likeable->morphs,
        );
        self::assertSame([LinkagePostResource::class, Fixtures\LinkageCommentResource::class], $likeable->related);
    }

    public function test_each_related_model_is_identified_by_the_resource_of_its_class(): void
    {
        $this->like(Post::query()->firstOrFail());
        $this->like(Comment::query()->firstOrFail());

        [$response, $queries] = $this->render(Like::query()->get());

        // The users, then the likeables: one query per class.
        self::assertSame(3, $queries);
        $response->assertJsonPath('data.0.relationships.likeable.data.type', 'post');
        $response->assertJsonPath('data.1.relationships.likeable.data.type', 'comment');
    }

    public function test_the_related_resources_are_planned_with_the_resource_of_their_class(): void
    {
        foreach (Post::query()->whereKey([1, 2, 3])->get() as $post) {
            $this->like($post);
        }
        foreach (Comment::query()->whereKey([1, 2, 3])->get() as $comment) {
            $this->like($comment);
        }

        [$response, $queries] = $this->render(Like::query()->get(), 'include=likeable.user');

        // users, posts, comments; then, class by class, the included users of the posts and their comments (linkage),
        // the included users of the comments; then the posts of the included users (linkage), for each class.
        self::assertSame(8, $queries);
        $types = $response->json('included.*.type');
        self::assertIsArray($types);
        self::assertEquals(['post' => 3, 'comment' => 3], array_intersect_key(
            array_count_values(array_filter($types, is_string(...))),
            ['post' => true, 'comment' => true],
        ));
    }

    public function test_a_related_model_of_a_class_out_of_the_map_throws(): void
    {
        $this->like(User::query()->firstOrFail());

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage(User::class);

        LinkageLikeResource::collection(Like::query()->get())->toResponse(new Request());
    }

    public function test_a_to_many_relationship_identifies_each_item_with_the_resource_of_its_class(): void
    {
        $feed = (object) ['id' => 1, 'items' => [
            Post::query()->firstOrFail(),
            null,
            Comment::query()->firstOrFail(),
        ]];

        $response = TestResponse::fromBaseResponse(LinkageFeedResource::make($feed)->toResponse(new Request()));

        // The `null` item is not a resource: it is left out, as the meta closure sees it.
        self::assertSame(['post', 'comment'], array_column((array) $response->json('data.relationships.items.data'), 'type'));
        $response->assertJsonPath('data.relationships.items.meta', ['collection' => true, 'count' => 2]);
    }

    public function test_an_empty_map_is_an_error_of_the_definition(): void
    {
        $definition = EmptyMorphResource::definition();

        self::assertSame([], $definition->relationships);
        self::assertCount(1, $definition->diagnostics);
        self::assertSame(DiagnosticLevel::Error, $definition->diagnostics[0]->level);
        self::assertStringContainsString(
            DefinitionException::emptyMorphMap()->getMessage(),
            $definition->diagnostics[0]->message,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('queries')]
    public function test_the_rules_accept_what_one_of_the_related_resources_has(array $query, bool $passes): void
    {
        $validator = Validator::make($query, [
            'include' => [new Includes(LinkageLikeResource::class)],
            'fields' => [new Fields(LinkageLikeResource::class)],
        ]);

        self::assertSame($passes, $validator->passes());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function queries(): iterable
    {
        yield 'a relationship of both' => [['include' => 'likeable.user'], true];
        yield 'a relationship of the post only' => [['include' => 'likeable.comments'], true];
        yield 'a relationship of none' => [['include' => 'likeable.nothing'], false];
        yield 'a relationship of each, path by path' => [['include' => 'likeable.comments,likeable.post'], true];
        yield 'a relationship of the post, then of the comment' => [['include' => 'likeable.comments.post'], true];
        yield 'the fields of a comment' => [['fields' => ['comment' => 'content']], true];
        yield 'the fields of a post' => [['fields' => ['post' => 'title']], true];
    }

    private function like(Post|Comment|User $likeable): void
    {
        $like = new Like(['user_id' => User::query()->firstOrFail()->id]);
        $like->likeable()->associate($likeable);
        $like->save();
    }

    /**
     * @param iterable<Like> $likes
     *
     * @return array{TestResponse<\Illuminate\Http\JsonResponse>, int}
     */
    private function render(iterable $likes, string $query = ''): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = LinkageLikeResource::collection($likes)->toResponse(Request::create("/?{$query}"));
        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        return [TestResponse::fromBaseResponse($response), $queries];
    }
}
