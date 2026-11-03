<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Stages;

use Ark4ne\JsonApi\Rendering\IncludedRegistry;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Ark4ne\JsonApi\Rendering\ResourceObjectRenderer;
use Ark4ne\JsonApi\Rendering\Stages\LinksStage;
use PHPUnit\Framework\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Rendering\Fixtures\FakeResource;

final class LinksStageTest extends TestCase
{
    public function test_the_links_are_resolved_whatever_the_fieldset(): void
    {
        $resource = new FakeResource('user', 1, links: ['self' => static fn() => '/users/1']);

        $object = (new LinksStage())->render(
            ResourceObject::identify('user', 1),
            $resource,
            Contexts::of('fields[user]=name'),
            new IncludedRegistry(ResourceObjectRenderer::default()),
        );

        self::assertSame(['self' => '/users/1'], $object->links);
    }
}
