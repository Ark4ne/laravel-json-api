<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Illuminate\Http\Request;
use Test\TestCase;

final class ContextBindingTest extends TestCase
{
    public function test_the_context_is_built_from_the_current_request_once_per_request(): void
    {
        app()->instance('request', new Request(['include' => 'posts', 'fields' => ['user' => 'name']]));

        $context = app(JsonApiContext::class);

        self::assertSame(app('request'), $context->request);
        self::assertSame(app(Configuration::class), $context->configuration);
        self::assertSame(['posts'], $context->include->paths());
        self::assertSame(['name'], $context->fields->of('user'));
        self::assertSame($context, app(JsonApiContext::class));
    }

    public function test_a_new_request_gets_a_new_context(): void
    {
        app()->instance('request', new Request(['include' => 'posts']));
        $first = app(JsonApiContext::class);

        app()->instance('request', new Request(['include' => 'comments']));
        $second = app(JsonApiContext::class);

        self::assertNotSame($first, $second);
        self::assertSame(['comments'], $second->include->paths());
    }

    public function test_the_same_request_keeps_its_context(): void
    {
        $request = new Request(['include' => 'posts']);
        app()->instance('request', $request);
        $first = app(JsonApiContext::class);

        app()->instance('request', new Request());
        app()->instance('request', $request);

        self::assertSame($first, app(JsonApiContext::class));
    }
}
