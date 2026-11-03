<?php

declare(strict_types=1);

namespace Test\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Test\app\Http\Resources\NativeCommentResource;
use Test\app\Http\Resources\NativePostResource;
use Test\app\Models\Post;
use Test\Support\JsonApiSchema;

/**
 * Interoperability with Laravel's native JSON:API at the boundaries (decision Q1): a relationship to native resources
 * renders them as leaves, and the request may be a native `JsonApiRequest`.
 */
final class NativeResourceTest extends FeatureTestCase
{
    public function test_a_relationship_to_native_resources_renders_their_linkage_loaded_in_batch(): void
    {
        $posts = Post::query()->get();

        [$response, $queries] = $this->render(NativePostResource::collection($posts));

        self::assertSame(2, $queries);
        foreach ($posts as $index => $post) {
            $response->assertJsonPath("data.{$index}.relationships.user.data", ['type' => 'users', 'id' => (string) $post->user->id]);
            $response->assertJsonCount(count($post->comments), "data.{$index}.relationships.comments.data");
        }
        $response->assertJsonPath('data.0.relationships.comments.data.0.type', 'comments');
        $response->assertJsonMissingPath('included');
    }

    public function test_an_included_native_resource_is_its_resource_object_without_its_relationships(): void
    {
        $post = Post::query()->firstOrFail();

        [$response] = $this->render(NativePostResource::make($post), 'include=user,comments');

        $response->assertJsonPath('included.0', [
            'type' => 'users',
            'id' => (string) $post->user->id,
            'attributes' => ['name' => $post->user->name, 'email' => $post->user->email],
            'links' => ['self' => "https://api.example.com/users/{$post->user->id}"],
            'meta' => ['native' => true],
        ]);
        self::assertSame(
            ['users' => 1, 'comments' => count($post->comments)],
            array_count_values($this->includedTypes($response)),
        );
    }

    public function test_the_meta_of_the_identifiers_is_read_from_the_native_resources(): void
    {
        $post = Post::query()->firstOrFail();

        [$response] = $this->render(NativePostResource::make($post), 'include=notes');

        $response->assertJsonPath('data.relationships.writer.data.meta', ['name' => $post->user->name]);
        $response->assertJsonPath('data.relationships.notes.data.0.meta', ['resource' => NativeCommentResource::class]);
        $response->assertJsonPath('included.0.type', 'comments');
    }

    public function test_the_sparse_fieldset_of_a_native_type_applies(): void
    {
        [$response] = $this->render(
            NativePostResource::make(Post::query()->firstOrFail()),
            'include=user&fields[users]=name',
        );

        $response->assertJsonPath('included.0.attributes', ['name' => Post::query()->firstOrFail()->user->name]);
    }

    public function test_nothing_is_loaded_nor_included_under_a_native_resource(): void
    {
        [$response, $queries] = $this->render(
            NativePostResource::make(Post::query()->firstOrFail()),
            'include=user.posts,posts',
        );

        // The user and the comments of the post: not the posts of the user, though `posts` is a relationship of the
        // native resource, which would read it from the root of the include.
        self::assertSame(2, $queries);
        self::assertSame(['users'], $this->includedTypes($response));
        $response->assertJsonMissingPath('included.0.relationships');
    }

    public function test_the_request_is_left_unchanged_for_the_native_resources(): void
    {
        $request = Request::create('/?include=user');

        NativePostResource::make(Post::query()->firstOrFail())->toResponse($request);

        self::assertSame('user', $request->query('include'));
    }

    public function test_a_native_json_api_request_renders_as_any_request(): void
    {
        $post = Post::query()->firstOrFail();
        $query = 'include=user&fields[post]=title,user';

        $native = NativePostResource::make($post)->toResponse(JsonApiRequest::create("/?{$query}"));
        $plain = NativePostResource::make($post)->toResponse(Request::create("/?{$query}"));

        self::assertSame($plain->getContent(), $native->getContent());
        self::assertSame([], JsonApiSchema::errors($native->getContent()));
    }


}
