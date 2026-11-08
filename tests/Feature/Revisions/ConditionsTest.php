<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Test\app\Http\Resources\Revisions\CommentResource;
use Test\app\Http\Resources\Revisions\PostResource;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;

/**
 * The conditions of the fields and the helpers of a resource at a revision (docs/rfc/versioned-resources-epics.md
 * US 4.6): what reads the model follows the head, what reads the request follows the revision.
 */
final class ConditionsTest extends RevisionsTestCase
{
    public function test_when_has_on_a_renamed_field_reads_the_head_attribute(): void
    {
        $this->renderAt(ApiRevision::Labels)->assertJsonPath('data.attributes.label', 'User 1');
    }

    public function test_a_sparse_field_is_rendered_when_an_old_client_requests_its_old_name(): void
    {
        self::assertSame(['label' => 'User 1'], $this->renderAt(ApiRevision::Labels, 'fields[helpers]=label')->json('data.attributes'));
        self::assertNull($this->renderAt(ApiRevision::Labels, 'fields[helpers]=name')->json('data.attributes'));
    }

    public function test_the_when_in_fields_helper_translates_a_head_name_for_an_old_client(): void
    {
        $this->renderAt(ApiRevision::Labels, 'fields[helpers]=label,name_requested')
            ->assertJsonPath('data.attributes.name_requested', true);
        $this->renderAt(ApiRevision::Labels, 'fields[helpers]=name,name_requested')
            ->assertJsonMissingPath('data.attributes.name_requested');
        // The head: the names of the code.
        $this->renderAt(null, 'fields[helpers]=name,name_requested')
            ->assertJsonPath('data.attributes.name_requested', true);
    }

    public function test_the_when_in_includes_helper_translates_a_head_relationship_for_an_old_client(): void
    {
        $this->renderAt(ApiRevision::Labels, 'include=articles')->assertJsonPath('data.attributes.posts_included', true);
        $this->renderAt(ApiRevision::Labels, 'include=posts')->assertJsonMissingPath('data.attributes.posts_included');
        $this->renderAt(null, 'include=posts')->assertJsonPath('data.attributes.posts_included', true);
    }

    public function test_a_helper_naming_a_field_absent_from_the_revision_fails(): void
    {
        $this->renderAt(ApiRevision::Labels, 'include=comments')->assertJsonMissingPath('data.attributes.comments_included');
        $this->renderAt(null, 'include=comments')->assertJsonPath('data.attributes.comments_included', true);
    }

    public function test_a_helper_naming_a_field_until_a_revision_fails_at_the_head(): void
    {
        $this->renderAt(ApiRevision::Labels, 'fields[helpers]=legacy,legacy_requested')
            ->assertJsonPath('data.attributes.legacy_requested', true);
        $this->renderAt(null, 'fields[helpers]=legacy,legacy_requested')
            ->assertJsonMissingPath('data.attributes.legacy_requested');
        $this->renderAt(ApiRevision::UsersReplaceTeachers, 'fields[helpers]=legacy,legacy_requested')
            ->assertJsonMissingPath('data.attributes.legacy_requested');
    }

    public function test_a_helper_naming_a_struct_path_translates_its_first_segment(): void
    {
        $this->renderAt(null, 'fields[helpers]=address.city,city_requested')
            ->assertJsonPath('data.attributes.city_requested', true);
        $this->renderAt(ApiRevision::Labels, 'fields[helpers]=location.city,city_requested')
            ->assertJsonPath('data.attributes.city_requested', true);
        $this->renderAt(null, 'include=posts.comments')->assertJsonPath('data.attributes.post_comments_included', true);
        $this->renderAt(ApiRevision::Labels, 'include=articles.comments')
            ->assertJsonPath('data.attributes.post_comments_included', true);
    }

    public function test_a_helper_naming_a_name_no_revision_declares_keeps_it(): void
    {
        $this->renderAt(null, 'fields[helpers]=undeclared,undeclared_requested')
            ->assertJsonPath('data.attributes.undeclared_requested', true);
        $this->renderAt(ApiRevision::Labels, 'fields[helpers]=undeclared,undeclared_requested')
            ->assertJsonPath('data.attributes.undeclared_requested', true);
    }

    /**
     * @return TestResponse<\Illuminate\Http\JsonResponse>
     */
    private function renderAt(?ApiRevision $revision, string $query = ''): TestResponse
    {
        $resource = new #[JsonApiType('helpers')] class (User::query()->findOrFail(1)) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'name' => $this->string()->whenHas()->was(ApiRevision::Labels, name: 'label'),
                    'name_requested' => $this->whenInFields($request, 'name', true),
                    'posts_included' => $this->whenInIncludes($request, 'posts', true),
                    'comments_included' => $this->whenInIncludes($request, 'comments', true),
                    'legacy_requested' => $this->whenInFields($request, 'legacy', true),
                    'legacy' => $this->string(fn() => 'legacy')->until(ApiRevision::Labels),
                    'address' => $this->struct(fn() => ['city' => $this->string(fn() => 'Paris')])
                        ->was(ApiRevision::Labels, name: 'location'),
                    'city_requested' => $this->whenInFields($request, 'address.city', true),
                    'undeclared_requested' => $this->whenInFields($request, 'undeclared', true),
                    'post_comments_included' => $this->whenInIncludes($request, 'posts.comments', true),
                ];
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(PostResource::class)->whenIncluded()->was(ApiRevision::Labels, name: 'articles'),
                    'comments' => $this->many(CommentResource::class)->whenIncluded()->since(ApiRevision::UsersReplaceTeachers),
                ];
            }
        };

        $request = Request::create("/?{$query}");
        $request->attributes->set(JsonApiContext::REVISION, $revision);

        return TestResponse::fromBaseResponse($resource->toResponse($request));
    }
}
