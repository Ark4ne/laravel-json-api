<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\MergeWhenValue;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Generator;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;
use Test\TestCase;

/**
 * The members of the hooks as the renderer reads them at a revision (docs/rfc/versioned-resources-epics.md US 4.1).
 */
final class RenderableTest extends TestCase
{
    protected function tearDown(): void
    {
        Definitions::flush();
        parent::tearDown();
    }

    public function test_the_members_of_a_resource_declaring_no_revision_are_given_as_they_are(): void
    {
        $members = (static function (): Generator {
            yield 'title' => 'value';
        })();
        $resource = new class ($members) extends JsonApiResource {
            public function __construct(private readonly Generator $members)
            {
                parent::__construct(null);
            }

            public function toAttributes(Request $request): iterable
            {
                return $this->members;
            }
        };

        // Not transformed, not even read: the same generator.
        self::assertSame($members, $resource->renderAttributes($this->context(ApiRevision::Initial)));
    }

    public function test_a_revised_resource_at_the_head_only_loses_its_fields_until_a_revision(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'display_name' => $this->string()->was(ApiRevision::Initial, name: 'name'),
                    'full_name' => $this->string()->until(ApiRevision::Labels),
                ];
            }
        };

        self::assertSame(['display_name'], array_keys([...$resource->renderAttributes($this->context(null))]));
        self::assertSame(['name', 'full_name'], array_keys([...$resource->renderAttributes($this->context(ApiRevision::Initial))]));
    }

    public function test_the_members_of_merge_when_stay_hidden_from_the_definition_once_revised(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()->was(ApiRevision::Initial, name: 'label'),
                    $this->mergeWhen(true, ['body' => $this->string()->was(ApiRevision::Initial, name: 'text')]),
                ];
            }
        };

        $members = [...$resource->renderAttributes($this->context(ApiRevision::Initial))];

        self::assertInstanceOf(MergeWhenValue::class, $members[0]);
        self::assertSame(['text'], array_keys($members[0]->data));
        self::assertSame(['label'], array_keys(Definitions::for($resource::class, ApiRevision::Initial)->attributes));
    }

    public function test_a_resource_mixing_two_enums_renders_the_head_of_its_definition(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()->was(ApiRevision::Labels, name: 'label'),
                    'body' => $this->string()->until(OtherRevision::First),
                ];
            }
        };

        self::assertSame(['title'], array_keys(Definitions::for($resource::class)->attributes));
        self::assertSame(['title'], array_keys([...$resource->renderAttributes($this->context(null))]));
    }

    public function test_the_links_are_those_of_the_revision(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toLinks(Request $request): iterable
            {
                return [
                    'self' => $this->string(fn() => 'https://example.com')->was(ApiRevision::Initial, name: 'canonical'),
                    'legacy' => $this->string(fn() => 'https://example.com/old')->until(ApiRevision::Labels),
                ];
            }
        };

        self::assertSame(['self'], array_keys([...$resource->renderLinks($this->context(null))]));
        self::assertSame(['canonical', 'legacy'], array_keys([...$resource->renderLinks($this->context(ApiRevision::Initial))]));
        self::assertSame(['canonical', 'legacy'], array_keys(Definitions::for($resource::class, ApiRevision::Initial)->links));
    }

    private function context(?ApiRevision $revision): JsonApiContext
    {
        $request = new Request();
        JsonApi::useRevision($request, $revision);

        return JsonApiContext::fromRequest($request, Configuration::defaults());
    }
}
