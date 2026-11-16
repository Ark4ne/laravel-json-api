<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;
use Test\TestCase;

/**
 * The members of the hooks as the renderer reads them at a revision (docs/rfc/versioned-resources-epics.md US 4.1).
 */
final class RenderableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Definitions::configure(Configuration::defaults()->withRevisions(ApiRevision::class));
    }

    protected function tearDown(): void
    {
        Definitions::configure(Configuration::defaults());
        parent::tearDown();
    }

    public function test_the_default_attributes_are_given_as_they_are(): void
    {
        $resource = new class (['id' => 1, 'title' => 'value']) extends JsonApiResource {};

        self::assertSame(['title' => 'value'], $resource->renderAttributes($this->context(null)));
    }

    public function test_the_attributes_are_those_of_the_revision_of_the_rendering(): void
    {
        $resource = new class (['display_name' => 'Ada', 'full_name' => 'Ada Lovelace']) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'display_name' => $this->string()->was(ApiRevision::Initial, name: 'name'),
                    'full_name' => $this->string()->until(ApiRevision::Labels),
                ];
            }
        };

        self::assertSame(['display_name' => 'Ada'], [...$resource->renderAttributes($this->context(null))]);
        self::assertSame(
            ['name' => 'Ada', 'full_name' => 'Ada Lovelace'],
            [...$resource->renderAttributes($this->context(ApiRevision::Initial))],
        );
    }

    public function test_a_context_without_revision_renders_the_head_of_the_application(): void
    {
        $resource = new class (['display_name' => 'Ada', 'full_name' => 'Ada Lovelace']) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'display_name' => $this->string()->was(ApiRevision::Initial, name: 'name'),
                    'full_name' => $this->string()->until(ApiRevision::Labels),
                ];
            }
        };

        // Built by hand: no revision was read for it.
        $context = $this->context(ApiRevision::Initial)->withRevision(null);

        self::assertNull($context->revision);
        self::assertSame(['display_name' => 'Ada'], [...$resource->renderAttributes($context)]);
    }

    public function test_the_meta_are_rendered_by_the_base(): void
    {
        $resource = new class (['rank' => '3']) extends JsonApiResource {
            protected function toResourceMeta(Request $request): iterable
            {
                return ['rank' => $this->integer()];
            }

            protected function toDocumentMeta(Request $request): iterable
            {
                return ['total' => $this->integer(static fn(): int => 10)];
            }
        };

        self::assertSame(['rank' => 3], [...$resource->renderResourceMeta($this->context(null))]);
        self::assertSame(['total' => 10], [...$resource->renderDocumentMeta($this->context(null))]);
    }

    public function test_the_links_are_given_as_they_are(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toLinks(Request $request): iterable
            {
                return ['self' => 'https://example.com', 'related' => static fn(): string => 'https://example.com/related'];
            }
        };

        $links = [...$resource->renderLinks($this->context(ApiRevision::Initial))];

        self::assertSame(['self', 'related'], array_keys($links));
        self::assertSame('https://example.com', $links['self']);
        self::assertSame(['self', 'related'], array_keys(Definitions::for($resource::class, ApiRevision::Initial)->links));
    }

    private function context(?ApiRevision $revision): JsonApiContext
    {
        return JsonApiContext::fromRequest(new Request(), Definitions::configuration(), $revision);
    }
}
