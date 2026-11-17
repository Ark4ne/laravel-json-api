<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Context\ResponseCache;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Revisions\ApiRevision;

final class ResponseCacheTest extends TestCase
{
    public function test_an_entry_is_built_once_even_when_it_is_null(): void
    {
        $cache = new ResponseCache();
        $builds = 0;
        $build = static function () use (&$builds): ?object {
            $builds++;

            return $builds > 1 ? new stdClass() : null;
        };

        $first = $cache->remember('plan', $build);
        $second = $cache->remember('plan', $build);

        self::assertSame(1, $builds);
        self::assertSame($first, $second);
    }

    public function test_each_key_has_its_entry(): void
    {
        $cache = new ResponseCache();
        $one = new stdClass();
        $other = new stdClass();

        self::assertSame($one, $cache->remember('one', static fn(): stdClass => $one));
        self::assertSame($other, $cache->remember('other', static fn(): stdClass => $other));
        self::assertSame($one, $cache->remember('one', static fn(): stdClass => $other));
    }

    public function test_the_contexts_derived_from_one_share_its_cache_and_its_projection(): void
    {
        $context = JsonApiContext::fromRequest(new Request(), Configuration::defaults());

        foreach ([$context->withPath('posts'), $context->withInclude(IncludeTree::parse('posts')), $context->withRevision(ApiRevision::Labels)] as $derived) {
            self::assertSame($context->cache, $derived->cache);
            self::assertSame($context->projection, $derived->projection);
        }
    }

    public function test_two_responses_do_not_share_their_cache(): void
    {
        $first = JsonApiContext::fromRequest(new Request(), Configuration::defaults());
        $second = JsonApiContext::fromRequest(new Request(), Configuration::defaults());

        self::assertNotSame($first->cache, $second->cache);
    }
}
