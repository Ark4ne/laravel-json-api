<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Test\app\Http\Resources\PostResource;
use Test\TestCase;
use Test\Unit\Http\Fixtures\PostFormRequest;
use Test\Unit\Http\Fixtures\PostUpdateFormRequest;
use Test\Unit\Http\Fixtures\PostWriteFormRequest;
use Test\Unit\Http\Fixtures\ResourcelessFormRequest;

final class JsonApiFormRequestTest extends TestCase
{
    public function test_a_valid_include_and_fieldset_pass_and_are_validated(): void
    {
        $request = $this->resolve(PostFormRequest::class, '/posts?include=user&fields[post]=title');

        self::assertSame(['include' => 'user', 'fields' => ['post' => 'title']], $request->validated());
    }

    public function test_the_include_and_the_fieldset_are_validated_against_the_resource(): void
    {
        $errors = $this->errors(PostFormRequest::class, '/posts?include=unknown&fields[post]=unknown');

        self::assertSame(['include', 'fields'], array_keys($errors));
        self::assertContains('"post" doesn\'t have relationship "unknown".', $errors['include']);
        self::assertContains('"post" doesn\'t have fields "unknown".', $errors['fields']);
    }

    public function test_a_request_without_include_nor_fieldset_passes(): void
    {
        self::assertSame([], $this->resolve(PostFormRequest::class, '/posts')->validated());
    }

    public function test_an_empty_include_passes(): void
    {
        self::assertSame([], $this->errors(PostFormRequest::class, '/posts?include='));
    }

    public function test_an_include_emptied_to_null_passes(): void
    {
        // ConvertEmptyStringsToNull turns `?include=` into `null`.
        $request = PostFormRequest::create('/posts');
        $request->query->set('include', null);

        self::assertSame([], $this->errors($request));
    }

    public function test_the_body_members_named_include_or_fields_are_neither_validated_nor_returned(): void
    {
        $request = $this->resolve(PostFormRequest::class, '/posts', 'POST', [
            'include' => 'unknown',
            'fields' => 'unknown',
        ]);

        self::assertSame([], $request->validated());
    }

    public function test_the_include_of_the_query_is_validated_even_when_the_body_has_a_valid_one(): void
    {
        $errors = $this->errors(PostFormRequest::class, '/posts?include=unknown', 'POST', ['include' => 'user']);

        self::assertSame(['include'], array_keys($errors));
    }

    public function test_the_rules_of_the_request_are_added(): void
    {
        $errors = $this->errors(PostWriteFormRequest::class, '/posts?fields[post]=unknown', 'POST');

        self::assertSame(['fields', 'data.attributes.title'], array_keys($errors));
    }

    public function test_a_rule_of_the_request_replaces_the_preconfigured_one(): void
    {
        $errors = $this->errors(PostWriteFormRequest::class, '/posts?include=user.posts', 'POST', [
            'data' => ['attributes' => ['title' => 'Title']],
        ]);

        self::assertSame(['include'], array_keys($errors));
        self::assertContains('"user.posts" is deeper than the maximum include depth of 1.', $errors['include']);
    }

    public function test_a_replaced_rule_stays_nullable(): void
    {
        $request = PostWriteFormRequest::create('/posts', 'POST', ['data' => ['attributes' => ['title' => 'Title']]]);
        $request->query->set('include', null);

        self::assertSame([], $this->errors($request));
    }

    public function test_a_rule_replacing_the_preconfigured_one_may_be_a_string(): void
    {
        $class = get_class(new class extends JsonApiFormRequest {
            protected string $resource = PostResource::class;

            /**
             * @return array<string, string>
             */
            public function rules(): array
            {
                return ['fields' => 'array'];
            }
        });
        $request = $class::create('/posts?fields[post]=unknown&fields[user]=');
        $request->query->set('include', null);
        $request->query->set('fields', null);

        self::assertSame([], $this->errors($class::create('/posts?fields[post]=unknown')));
        self::assertSame([], $this->errors($request));
    }

    public function test_the_resource_object_of_the_body_holds_only_the_validated_members(): void
    {
        $request = $this->resolve(PostWriteFormRequest::class, '/posts', 'POST', ['data' => [
            'type' => 'post',
            'attributes' => ['title' => 'Title', 'content' => 'Not validated'],
        ]]);

        $document = $request->jsonApi();

        self::assertNull($document->type());
        self::assertSame(['title' => 'Title'], $document->attributes());
    }

    public function test_the_linkage_of_the_relationships_is_read_from_the_validated_data(): void
    {
        $document = $this->resolve(PostUpdateFormRequest::class, '/posts/1', 'PATCH', ['data' => ['relationships' => [
            'user' => ['data' => ['type' => 'user', 'id' => 1]],
            'comments' => ['data' => [['type' => 'comment', 'id' => '2'], ['type' => 'comment', 'id' => 3]]],
        ]]])->jsonApi();

        self::assertSame(['id' => 1], $document->relationship('user'));
        self::assertSame(['1'], $document->ids('user'));
        self::assertSame(['2', '3'], $document->ids('comments'));
    }

    public function test_a_relationship_cleared_is_read_when_its_linkage_has_a_rule(): void
    {
        $document = $this->resolve(PostUpdateFormRequest::class, '/posts/1', 'PATCH', ['data' => ['relationships' => [
            'user' => ['data' => null],
            'comments' => ['data' => []],
        ]]])->jsonApi();

        self::assertTrue($document->hasRelationship('user'));
        self::assertNull($document->relationship('user'));
        self::assertTrue($document->hasRelationship('comments'));
        self::assertSame([], $document->ids('comments'));
    }

    public function test_a_relationship_cleared_is_missing_when_only_its_identifiers_have_rules(): void
    {
        $document = $this->resolve(PostUpdateFormRequest::class, '/posts/1', 'PATCH', ['data' => ['relationships' => [
            'editor' => ['data' => null],
        ]]])->jsonApi();

        self::assertFalse($document->hasRelationship('editor'));
    }

    public function test_the_errors_of_the_body_are_keyed_by_the_path_of_the_member(): void
    {
        $errors = $this->errors(PostWriteFormRequest::class, '/posts', 'POST', ['data' => ['attributes' => []]]);

        self::assertSame(['data.attributes.title'], array_keys($errors));
    }

    public function test_a_form_request_without_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage(ResourcelessFormRequest::class);

        $this->resolve(ResourcelessFormRequest::class, '/posts');
    }

    /**
     * @template T of JsonApiFormRequest
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $body
     *
     * @return T
     */
    private function resolve(string $class, string $uri, string $method = 'GET', array $body = []): JsonApiFormRequest
    {
        return $this->validate($class::create($uri, $method, $body));
    }

    /**
     * @template T of JsonApiFormRequest
     *
     * @param T $request
     *
     * @return T
     */
    private function validate(JsonApiFormRequest $request): JsonApiFormRequest
    {
        $app = $this->app;
        self::assertNotNull($app);

        $request->setContainer($app)->setRedirector($app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    /**
     * The messages by attribute; none when the request passes.
     *
     * @param class-string<JsonApiFormRequest>|JsonApiFormRequest $request
     * @param array<string, mixed> $body
     *
     * @return array<string, list<string>>
     */
    private function errors(string|JsonApiFormRequest $request, string $uri = '', string $method = 'GET', array $body = []): array
    {
        try {
            $this->validate(is_string($request) ? $request::create($uri, $method, $body) : $request);
        } catch (ValidationException $e) {
            /** @var array<string, list<string>> */
            return $e->errors();
        }

        return [];
    }
}
