<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stage;
use Ark4ne\JsonApi\Rendering\Stages\AttributesStage;
use Ark4ne\JsonApi\Rendering\Stages\LinksStage;
use Ark4ne\JsonApi\Rendering\Stages\MetaStage;
use Ark4ne\JsonApi\Rendering\Stages\RelationshipsStage;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class ResourceObjectRendererTest extends TestCase
{
    public function test_the_default_stages_run_in_the_documented_order(): void
    {
        self::assertSame(
            [AttributesStage::class, RelationshipsStage::class, LinksStage::class, MetaStage::class],
            array_map(get_class(...), ResourceObjectRenderer::default()->stages()),
        );
    }

    public function test_the_resource_is_identified_then_rendered_by_each_stage(): void
    {
        $renderer = ResourceObjectRenderer::default();
        $context = Contexts::of();

        $object = $renderer->render(
            new FakeResource('user', 1, ['name' => 'Ada'], links: ['self' => '/users/1'], meta: ['new' => true]),
            $context,
            new IncludedRegistry($renderer),
        );

        self::assertSame([
            'type' => 'user',
            'id' => '1',
            'attributes' => ['name' => 'Ada'],
            'links' => ['self' => '/users/1'],
            'meta' => ['new' => true],
        ], $object->toArray());
    }

    public function test_an_application_adds_its_own_stages_after_those_of_the_package(): void
    {
        $stage = new class implements Stage {
            public function render(
                ResourceObject $object,
                RenderableResource $resource,
                JsonApiContext $context,
                IncludedRegistry $included,
            ): ResourceObject {
                return $object->withMeta([...$object->meta, 'stage' => true]);
            }
        };

        $renderer = ResourceObjectRenderer::default()->with($stage);
        $object = $renderer->render(new FakeResource('user', 1, meta: ['new' => true]), Contexts::of(), new IncludedRegistry($renderer));

        self::assertSame($stage, $renderer->stages()[4]);
        self::assertSame(
            [AttributesStage::class, RelationshipsStage::class, LinksStage::class, MetaStage::class],
            array_map(get_class(...), array_slice($renderer->stages(), 0, 4)),
        );
        self::assertSame(['new' => true, 'stage' => true], $object->meta);
    }

    public function test_the_stages_of_the_configuration_run_after_those_of_the_package(): void
    {
        $renderer = ResourceObjectRenderer::configured(Configuration::fromArray([
            'rendering' => ['stages' => [ConfiguredStage::class]],
        ]));

        self::assertSame(
            [AttributesStage::class, RelationshipsStage::class, LinksStage::class, MetaStage::class, ConfiguredStage::class],
            array_map(get_class(...), $renderer->stages()),
        );
    }

    public function test_without_configured_stage_the_renderer_is_the_default_one(): void
    {
        self::assertEquals(ResourceObjectRenderer::default(), ResourceObjectRenderer::configured(Configuration::defaults()));
    }

    public function test_a_configured_class_that_is_not_a_stage_throws_naming_the_key(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('rendering.stages');

        ResourceObjectRenderer::configured(Configuration::fromArray(['rendering' => ['stages' => [self::class]]]));
    }
}

final class ConfiguredStage implements Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        return $object->withMeta([...$object->meta, 'configured' => true]);
    }
}
