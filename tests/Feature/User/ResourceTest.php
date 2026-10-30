<?php

declare(strict_types=1);

namespace Test\Feature\User;

use Ark4ne\JsonApi\Support\Arr;
use DateTimeInterface;
use Illuminate\Http\Request;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\app\Rendering\ServedStage;
use Test\Feature\FeatureTestCase;

class ResourceTest extends FeatureTestCase
{
    public function testShowBasic(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $response->assertExactJson($this->getJsonResult($user));
    }

    public function testShowWithAttributes(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?fields[user]=name");

        $response->assertExactJson($this->getJsonResult($user, ['name']));
    }


    public function testShowWithStructAttributes(): void
    {
        $user = $this->dataSeed();

        $attrs = [
            'name',
            'struct-set.name',
            'struct-set.sub-struct.int',
        ];

        $response = $this->get("user/{$user->id}?fields[user]=" . implode(',', $attrs));

        $response->assertExactJson($this->getJsonResult($user, $attrs));
    }

    public function testShowWithRelationshipsPosts(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts");

        $response->assertExactJson($this->getJsonResult($user, null, ['posts']));
    }

    public function testShowWithRelationshipsComments(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=comments");

        $response->assertExactJson($this->getJsonResult($user, null, ['comments']));
    }

    public function testShowWithRelationshipsPostsComments(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts,comments");

        $response->assertExactJson($this->getJsonResult($user, null, ['posts', 'comments']));
    }

    public function testShowWithRelationshipsDeepInclude(): void
    {
        $user = $this->dataSeed();

        // The primary resource reached through posts.user is never repeated in included.
        $expected = $this->getJsonResult($user, null, ['posts']);

        $response = $this->get("user/{$user->id}?include=posts.user");

        $response->assertExactJson($expected);

        $response = $this->get("user/{$user->id}?include=posts.user.posts.user.posts.user");

        $response->assertExactJson($expected);
    }

    public function test_the_fieldset_of_a_type_restricts_its_included_resources_only(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts&fields[post]=title,content");

        $included = $response->json('included');
        self::assertIsArray($included);
        self::assertNotEmpty($included);

        foreach ($included as $post) {
            self::assertIsArray($post);
            self::assertSame('post', $post['type'] ?? null);
            self::assertSame(['title', 'content'], array_keys($post['attributes'] ?? []));
            self::assertArrayNotHasKey('relationships', $post);
        }

        self::assertSame($user->name, $response->json('data.attributes.name'));
        self::assertNotNull($response->json('data.relationships.posts'));
    }

    public function testShowFailWithAttributes(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?fields[user]=name,unknown&fields[foo]=bar");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'fields' => [
                'The selected fields is invalid.',
                '"user" doesn\'t have fields "unknown".',
                '"foo" doesn\'t exists.',
            ],
        ]);
    }

    public function testShowFailWithIncludes(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"user" doesn\'t have relationship "unknown".',
        ]]);
    }

    public function testShowFailWithIncludesSub(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=posts.unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"posts" doesn\'t have relationship "unknown"',
        ]]);
    }

    public function testShowMultipleFailures(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=posts.one,two&fields[user]=name,one_field&fields[unknown]=some");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"posts" doesn\'t have relationship "one".',
            '"user" doesn\'t have relationship "two".',
        ], 'fields' => [
            'The selected fields is invalid.',
            '"user" doesn\'t have fields "one_field".',
            '"unknown" doesn\'t exists.',
        ]]);
    }

    #[DefineEnvironment('resolveRelationshipsWhenIncluded')]
    public function testShowBasicAutoWhenIncluded(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $response->assertExactJson($this->getJsonResult($user, null, null, false));
    }

    #[DefineEnvironment('registerServedStage')]
    public function test_a_stage_of_the_configuration_renders_the_primary_and_included_resources(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts");

        self::assertTrue($response->json('data.meta.served'));
        self::assertTrue($response->json('included.0.meta.served'));
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function registerServedStage($app): void
    {
        $app['config']->set('jsonapi.rendering.stages', [ServedStage::class]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function resolveRelationshipsWhenIncluded($app): void
    {
        $app['config']->set('jsonapi.relationship.when-included', true);
    }

    private function dataSeed(): User
    {
        $user = User::first();
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    /**
     * @param list<string>|null $attributes
     * @param list<string>|null $relationships
     *
     * @return array<string, mixed>
     */
    private function getJsonResult(User $user, ?array $attributes = null, ?array $relationships = null, bool $withIncluded = true): array
    {
        $request = new Request(array_merge(
            ($attributes !== null ? ['fields' => ['user' => implode(',', $attributes)]] : []),
            ($relationships !== null ? ['include' => implode(',', $relationships)] : []),
        ));

        // fields[user] restricts the relationships too (fields.relationships = filter).
        $relationshipsOf = static fn(array $relationships) => $attributes === null
            ? $relationships
            : array_intersect_key($relationships, array_flip($attributes));

        return array_filter([
            'data' => array_filter([
                'id' => (string) $user->id,
                'type' => 'user',
                'attributes' => Arr::intersectKeyStruct([
                    'name' => $user->name,
                    'email' => $user->email,
                    "with-apply-conditional-closure" => "huge-data-set",
                    "with-apply-conditional-raw" => "huge-data-set",
                    "with-apply-conditional-value" => "huge-data-set",
                    "struct-set" => [
                        "name" => $user->name,
                        "closure" => "closure",
                        "email" => $user->email,
                        "casted" => "string",
                        "with-apply-conditional-raw" => "huge-data-set",
                        "sub-struct" => [
                            "int" => 200,
                            "float" => 1.1,
                        ],
                    ],
                ], Arr::undot(array_fill_keys($attributes ?? [
                    'name',
                    'email',
                    "with-apply-conditional-closure",
                    "with-apply-conditional-raw",
                    "with-apply-conditional-value",
                    "struct-set",
                ], true))),
                'relationships' => $relationshipsOf(array_filter([
                    // Resolved only when included: without data nor links, it is left out.
                    'main-post' => $withIncluded ? ['data' => null] : null,
                    'posts' => array_filter([
                        'data' => $withIncluded ? $user->posts->map(fn(Post $post) => ['type' => 'post', 'id' => (string) $post->id])->all() : null,
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/posts",
                            'related' => "https://api.example.com/user/{$user->id}/posts",
                        ],
                        'meta' => [
                            'total' => $user->posts->count(),
                        ],
                    ]),
                    'comments' => array_filter([
                        // when loaded only
                        'data' => $withIncluded && in_array('comments', $relationships ?? [], true)
                            ? $user->comments->map(fn(Comment $comment) => [
                                'type' => 'comment',
                                'id' => (string) $comment->id,
                            ])->all()
                            : null,
                        'links' => [
                            'self' => "https://api.example.com/user/{$user->id}/relationships/comments",
                            'related' => "https://api.example.com/user/{$user->id}/comments",
                        ],
                        'meta' => [
                            'total' => $user->comments->count(),
                        ],
                    ]),
                ], static fn($relationship) => $relationship !== null)),
                'meta' => [
                    'created_at' => $user->created_at->format(DateTimeInterface::ATOM),
                    'updated_at' => $user->updated_at->format(DateTimeInterface::ATOM),
                ],
            ], static fn($member) => $member !== []),
            'included' => collect()
                ->merge(
                    in_array('posts', $relationships ?? [], true)
                        ? $user->posts->mapInto(PostResource::class)->map->toArray($request)
                        : [],
                )
                ->merge(
                    in_array('comments', $relationships ?? [], true)
                        ? $user->comments->mapInto(CommentResource::class)->map->toArray($request)
                        : [],
                )
                ->toArray(),
        ]);
    }
}
