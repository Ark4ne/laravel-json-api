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
            'published-at' => $this->date()->format('Y-m-d'),
            'state' => $this->enum()->of(State::class),
            'tags' => $this->arrayOf($this->string()),
            'full-name' => $this->string(fn() => 'computed'),
            'secret' => $this->string()->when(false),
            'content' => $this->string()->whenInFields(),
            'address' => $this->struct(fn() => [
                'city' => $this->string(),
                'geo' => $this->struct(fn() => [
                    'lat' => $this->float(),
                ]),
            ]),
            $this->applyWhen(true, [
                'grouped' => $this->integer(),
            ]),
        ];
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<\Test\app\Models\Post>>
     */
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

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Values\Value<\Test\app\Models\Post>>
     */
    protected function toLinks(Request $request): iterable
    {
        return [
            'self' => $this->string(fn() => 'https://api.example.com'),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created-at' => $this->datetime(),
        ];
    }
}
