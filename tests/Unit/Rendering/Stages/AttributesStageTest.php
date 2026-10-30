<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Stages;

use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stages\AttributesStage;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class AttributesStageTest extends TestCase
{
    public function test_the_attributes_are_restricted_by_the_fieldset_of_the_type(): void
    {
        $resource = new FakeResource('user', 1, [
            'name' => new ValueString(null),
            'email' => static fn() => 'ada@example.com',
        ], model: ['name' => 'Ada']);
        $context = Contexts::of('fields[user]=name');

        $object = (new AttributesStage())->render(
            ResourceObject::identify('user', 1),
            $resource,
            $context,
            new IncludedRegistry(ResourceObjectRenderer::default()),
        );

        self::assertSame(['name' => 'Ada'], $object->attributes);
    }
}
