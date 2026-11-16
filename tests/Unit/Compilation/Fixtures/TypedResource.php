<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Enums\State;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;

/**
 * Every kind of descriptor, written the documented way: the compiler must know all of it.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class TypedResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'typed';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'name' => $this->string('first_name')->nullable(false),
            'count' => $this->integer(),
            'ratio' => $this->float(),
            'active' => $this->bool(),
            'published-at' => $this->date(),
            'state' => $this->enum(State::class),
            'tags' => $this->arrayOf($this->string()),
            'full-name' => $this->string(fn() => 'computed'),
            'secret' => $this->string()->when(static fn(): bool => false),
            'content' => $this->string()->whenInFields(),
            'address' => $this->struct([
                'city' => $this->string(),
                'geo' => $this->struct([
                    'lat' => $this->float(),
                ]),
            ]),
            $this->applyWhen(static fn(): bool => true, [
                'grouped' => $this->integer(),
            ]),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(UserResource::class)->withLoad('author'),
            'comments' => $this->many(CommentResource::class)
                ->links(fn() => [])
                ->meta(fn() => [])
                ->whenIncluded()
                ->withLoad(['comments' => fn($query) => $query]),
            'reviewer' => $this->one(UserResource::class, fn() => null)->whenLoaded(),
        ];
    }

    protected function toLinks(Request $request): iterable
    {
        return [
            'self' => static fn(): string => 'https://api.example.com',
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created-at' => $this->datetime(),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return [
            'version' => $this->integer(fn() => 1),
        ];
    }
}
