<?php

declare(strict_types=1);

namespace Test\Feature\User;

use Illuminate\Http\Request;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\User;
use Test\app\Rendering\ServedStage;
use Test\Feature\FeatureTestCase;

class ResourceTest extends FeatureTestCase
{
    public function test_show_renders_the_user(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $this->assertDocument('show', $response);
    }

    public function test_rendering_a_resource_twice_gives_the_same_document_and_leaves_it_unchanged(): void
    {
        $resource = UserResource::make($this->dataSeed());
        $request = Request::create('/?include=posts.comments,comments.post');

        $first = $resource->toResponse($request)->getContent();
        $second = $resource->toResponse($request)->getContent();

        self::assertSame($first, $second);
        self::assertSame([], $resource->with);
    }

    public function test_show_renders_only_the_requested_fields(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?fields[user]=name");

        $this->assertDocument('show-fields-name', $response);
    }


    public function test_show_renders_the_requested_struct_members(): void
    {
        $user = $this->dataSeed();

        $attrs = [
            'name',
            'struct-set.name',
            'struct-set.sub-struct.int',
        ];

        $response = $this->get("user/{$user->id}?fields[user]=" . implode(',', $attrs));

        $this->assertDocument('show-fields-struct', $response);
    }

    public function test_show_includes_the_posts(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts");

        $this->assertDocument('show-include-posts', $response);
    }

    public function test_show_includes_the_comments(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=comments");

        $this->assertDocument('show-include-comments', $response);
    }

    public function test_show_includes_the_posts_and_the_comments(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}?include=posts,comments");

        $this->assertDocument('show-include-posts-comments', $response);
    }

    public function test_show_never_includes_the_primary_resource_reached_through_a_deep_include(): void
    {
        $user = $this->dataSeed();

        // The primary resource reached through posts.user is never repeated in included.
        $this->assertDocument('show-include-posts', $this->get("user/{$user->id}?include=posts.user"));
        $this->assertDocument('show-include-posts', $this->get("user/{$user->id}?include=posts.user.posts.user.posts.user"));
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

    public function test_show_rejects_unknown_fields_and_types(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?fields[user]=name,unknown&fields[foo]=bar");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'fields' => [
                'The selected fields is invalid.',
                '"user" doesn\'t have fields "unknown".',
                '"foo" doesn\'t exist.',
            ],
        ]);
    }

    public function test_show_rejects_an_unknown_include(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"user" doesn\'t have relationship "unknown".',
        ]]);
    }

    public function test_show_rejects_an_unknown_nested_include(): void
    {
        $user = $this->dataSeed();

        $response = $this->getJson("user/{$user->id}?include=posts.unknown");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['include' => [
            'The selected include is invalid.',
            '"posts" doesn\'t have relationship "unknown"',
        ]]);
    }

    public function test_show_reports_every_invalid_include_and_field(): void
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
            '"unknown" doesn\'t exist.',
        ]]);
    }

    #[DefineEnvironment('resolveRelationshipsWhenIncluded')]
    public function test_show_leaves_out_the_linkage_of_relationships_not_included_when_configured_so(): void
    {
        $user = $this->dataSeed();

        $response = $this->get("user/{$user->id}");

        $this->assertDocument('show-linkage-when-included', $response);
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
        $app['config']->set('jsonapi.relationship.linkage', 'included');
    }

    private function dataSeed(): User
    {
        $user = User::first();
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

}
