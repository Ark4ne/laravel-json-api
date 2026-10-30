<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Stages;

use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stages\MetaStage;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class MetaStageTest extends TestCase
{
    public function test_the_meta_of_the_resource_object_are_resolved_whatever_the_fieldset(): void
    {
        $resource = new FakeResource('user', 1, meta: ['created' => static fn() => 'today', 'hidden' => new MissingValue()]);

        $object = (new MetaStage())->render(
            ResourceObject::identify('user', 1),
            $resource,
            Contexts::of('fields[user]=name'),
            new IncludedRegistry(ResourceObjectRenderer::default()),
        );

        self::assertSame(['created' => 'today'], $object->meta);
    }
}
