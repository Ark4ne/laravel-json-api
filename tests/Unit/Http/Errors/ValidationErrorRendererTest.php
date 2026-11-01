<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Http\Errors\ValidationErrorRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Test\Support\JsonApiSchema;
use Test\TestCase;

final class ValidationErrorRendererTest extends TestCase
{
    public function test_it_supports_validation_exceptions_only(): void
    {
        $renderer = new ValidationErrorRenderer();

        self::assertTrue($renderer->supports(ValidationException::withMessages([])));
        self::assertFalse($renderer->supports(new RuntimeException()));
    }

    public function test_a_validation_exception_with_a_response_of_its_own_is_left_to_laravel(): void
    {
        $exception = new ValidationException(Validator::make([], []), new Response('', 400));

        self::assertFalse((new ValidationErrorRenderer())->supports($exception));
    }

    public function test_the_status_of_the_exception_is_kept(): void
    {
        $exception = ValidationException::withMessages(['data.id' => ['Taken.']])->status(409);

        $document = (new ValidationErrorRenderer())->render($exception, Request::create('/', 'POST'));

        self::assertSame(409, $document->status);
        self::assertSame(['status' => '409', 'title' => 'Conflict', 'detail' => 'Taken.', 'source' => ['pointer' => '/data/id']], $document->errors[0]->toArray());
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_an_integer_key_points_to_an_item_of_the_body(): void
    {
        $exception = ValidationException::withMessages([0 => ['Invalid.']]);
        $request = Request::create('/', 'POST', [['a' => 1]]);

        $error = (new ValidationErrorRenderer())->render($exception, $request)->errors[0];

        self::assertSame(['pointer' => '/0'], $error->source);
    }

    public function test_a_missing_parameter_of_a_request_without_body_is_named_as_a_parameter(): void
    {
        $exception = ValidationException::withMessages(['filter.status' => ['Required.']]);

        $errors = (new ValidationErrorRenderer())->render($exception, Request::create('/posts'))->errors;

        self::assertSame(['parameter' => 'filter[status]'], $errors[0]->source);
    }

    public function test_a_missing_member_of_a_request_with_a_body_points_to_it(): void
    {
        $exception = ValidationException::withMessages(['data.type' => ['Required.'], 'filter.status' => ['Required.']]);

        $errors = (new ValidationErrorRenderer())->render($exception, Request::create('/posts?filter[status]=', 'PATCH'))->errors;

        self::assertSame(['pointer' => '/data/type'], $errors[0]->source);
        self::assertSame(['parameter' => 'filter[status]'], $errors[1]->source);
    }

    public function test_each_message_is_an_error_pointing_to_the_member_of_the_body(): void
    {
        $exception = ValidationException::withMessages([
            'data.attributes.title' => ['The title field is required.', 'The title must be a string.'],
            'data.relationships.comments.data.0.id' => ['The id is invalid.'],
        ]);

        $document = (new ValidationErrorRenderer())->render($exception, Request::create('/posts', 'POST'));

        self::assertSame(422, $document->status);
        self::assertSame([
            [
                'status' => '422',
                'title' => 'Unprocessable Content',
                'detail' => 'The title field is required.',
                'source' => ['pointer' => '/data/attributes/title'],
            ],
            [
                'status' => '422',
                'title' => 'Unprocessable Content',
                'detail' => 'The title must be a string.',
                'source' => ['pointer' => '/data/attributes/title'],
            ],
            [
                'status' => '422',
                'title' => 'Unprocessable Content',
                'detail' => 'The id is invalid.',
                'source' => ['pointer' => '/data/relationships/comments/data/0/id'],
            ],
        ], $document->toArray()['errors']);
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_an_error_on_a_query_parameter_names_the_parameter(): void
    {
        $exception = ValidationException::withMessages([
            'include' => ['"post" doesn\'t have relationship "unknown".'],
            'fields.post' => ['Invalid.'],
            'filter.author.name' => ['Invalid.'],
        ]);
        $request = Request::create('/posts?include=unknown&fields[post]=unknown&filter[author][name]=x');

        $errors = (new ValidationErrorRenderer())->render($exception, $request)->errors;
        $sources = array_map(static fn($error) => $error->source, $errors);

        self::assertSame([
            ['parameter' => 'include'],
            ['parameter' => 'fields[post]'],
            ['parameter' => 'filter[author][name]'],
        ], $sources);
        self::assertSame([], JsonApiSchema::errors((new ValidationErrorRenderer())->render($exception, $request)->toArray()));
    }

    public function test_the_segments_of_a_pointer_are_escaped(): void
    {
        $exception = ValidationException::withMessages(['data.attributes.a/b~c' => ['Invalid.']]);

        $error = (new ValidationErrorRenderer())->render($exception, Request::create('/', 'POST'))->errors[0];

        self::assertSame(['pointer' => '/data/attributes/a~1b~0c'], $error->source);
        self::assertSame([], JsonApiSchema::errors(['errors' => [$error->toArray()]]));
    }
}
