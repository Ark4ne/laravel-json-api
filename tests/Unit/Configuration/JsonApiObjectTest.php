<?php

declare(strict_types=1);

namespace Test\Unit\Configuration;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use PHPUnit\Framework\TestCase;

final class JsonApiObjectTest extends TestCase
{
    public function test_the_version_alone_is_rendered_by_default(): void
    {
        self::assertSame(['version' => '1.1'], (new JsonApiObject())->toArray());
    }

    public function test_extensions_profiles_and_meta_are_rendered_when_given(): void
    {
        $jsonapi = new JsonApiObject(
            version: '1.1',
            ext: ['https://jsonapi.org/ext/atomic'],
            profile: ['https://example.com/profiles/timestamps'],
            meta: ['copyright' => 'ACME'],
        );

        self::assertSame([
            'version' => '1.1',
            'ext' => ['https://jsonapi.org/ext/atomic'],
            'profile' => ['https://example.com/profiles/timestamps'],
            'meta' => ['copyright' => 'ACME'],
        ], $jsonapi->toArray());
    }
}
