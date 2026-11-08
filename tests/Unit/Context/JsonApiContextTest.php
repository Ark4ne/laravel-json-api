<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\IncludeTree;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;

final class JsonApiContextTest extends TestCase
{
    public function test_the_include_and_the_fields_of_the_request_are_parsed(): void
    {
        $request = new Request(['include' => 'posts.comments', 'fields' => ['user' => 'name']]);
        $configuration = Configuration::defaults();

        $context = JsonApiContext::fromRequest($request, $configuration);

        self::assertSame($request, $context->request);
        self::assertSame($configuration, $context->configuration);
        self::assertSame(['posts', 'posts.comments'], $context->include->paths());
        self::assertSame(['name'], $context->fields->of('user'));
        self::assertSame('', $context->path);
        self::assertCount(0, $context->diagnostics);
    }

    public function test_the_relationships_included_depend_on_the_path(): void
    {
        $context = JsonApiContext::fromRequest(new Request(['include' => 'posts.comments']), Configuration::defaults());

        self::assertTrue($context->includes('posts'));
        self::assertFalse($context->includes('comments'));

        $posts = $context->withPath('posts');

        self::assertSame('posts', $posts->path);
        self::assertTrue($posts->includes('comments'));
        self::assertFalse($posts->includes('posts'));
        self::assertSame('posts.comments', $posts->withPath('comments')->path);
    }

    public function test_entering_a_relationship_leaves_the_context_unchanged_and_shares_the_diagnostics(): void
    {
        $context = JsonApiContext::fromRequest(new Request(), Configuration::defaults());

        $posts = $context->withPath('posts');
        $posts->diagnostics->warning('code', 'message');

        self::assertNotSame($context, $posts);
        self::assertSame('', $context->path);
        self::assertSame($context->diagnostics, $posts->diagnostics);
        self::assertCount(1, $context->diagnostics);
    }

    public function test_the_include_and_the_fields_are_read_from_the_query_only(): void
    {
        $request = Request::create('/posts?include=author', 'POST', ['include' => 'comments', 'fields' => ['post' => 'title']]);

        $context = JsonApiContext::fromRequest($request, Configuration::defaults());

        self::assertSame(['author'], $context->include->paths());
        self::assertFalse($context->fields->restricts('post'));
    }

    public function test_the_include_is_cut_at_the_configured_maximum_depth(): void
    {
        $context = JsonApiContext::fromRequest(
            new Request(['include' => 'posts.comments.user']),
            Configuration::fromArray(['include' => ['max-depth' => 1]]),
        );

        self::assertSame(['posts'], $context->include->paths());
    }

    public function test_a_context_with_an_include_keeps_the_requested_one(): void
    {
        $context = JsonApiContext::fromRequest(
            new Request(['include' => 'posts.comments.user']),
            Configuration::fromArray(['include' => ['max-depth' => 1]]),
        )->withPath('author');

        $included = $context->withInclude(IncludeTree::parse('author.comments.user'));

        self::assertSame(['posts'], $context->include->paths());
        self::assertSame(['posts', 'author', 'author.comments', 'author.comments.user'], $included->include->paths());
        self::assertSame('author', $included->path);
        self::assertTrue($included->includes('comments'));
        self::assertSame($context->diagnostics, $included->diagnostics);
    }

    public function test_the_context_reads_the_revision_of_the_request(): void
    {
        $request = new Request();
        $request->attributes->set(JsonApiContext::REVISION, ApiRevision::Labels);

        self::assertSame(ApiRevision::Labels, JsonApiContext::revisionOf($request));
        self::assertSame(ApiRevision::Labels, JsonApiContext::fromRequest($request, Configuration::defaults())->revision);
    }

    public function test_a_request_without_revision_renders_the_head(): void
    {
        $context = JsonApiContext::fromRequest(new Request(), Configuration::defaults());

        self::assertNull($context->revision);
    }

    public function test_entering_a_relationship_or_adding_includes_keeps_the_revision(): void
    {
        $request = new Request();
        $request->attributes->set(JsonApiContext::REVISION, ApiRevision::Initial);
        $context = JsonApiContext::fromRequest($request, Configuration::defaults());

        self::assertSame(ApiRevision::Initial, $context->withPath('posts')->revision);
        self::assertSame(ApiRevision::Initial, $context->withInclude(IncludeTree::parse('posts'))->revision);
    }

    public function test_a_context_at_another_revision_keeps_the_rest_and_shares_the_diagnostics(): void
    {
        $context = JsonApiContext::fromRequest(new Request(['include' => 'posts']), Configuration::defaults())->withPath('posts');

        $labels = $context->withRevision(ApiRevision::Labels);

        self::assertNull($context->revision);
        self::assertSame(ApiRevision::Labels, $labels->revision);
        self::assertSame($context->diagnostics, $labels->diagnostics);
        self::assertSame($context->include, $labels->include);
        self::assertSame('posts', $labels->path);
    }

    public function test_a_revision_attribute_that_is_not_a_revision_throws(): void
    {
        $request = new Request();
        $request->attributes->set(JsonApiContext::REVISION, '2026-12-01');

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('JsonApi::useRevision()');

        JsonApiContext::revisionOf($request);
    }
}
