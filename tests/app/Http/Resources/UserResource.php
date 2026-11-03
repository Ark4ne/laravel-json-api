<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Test\app\Models\User;

/**
 * @extends JsonApiResource<User>
 */
class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'email' => $this->string(),
            'only-with-fields' => $this->string(fn() => 'huge-data-set')->whenInFields(),
            $this->applyWhen(fn() => true, [
                'with-apply-conditional-raw' => 'huge-data-set',
                'with-apply-conditional-closure' => fn() => 'huge-data-set',
                'with-apply-conditional-value' => $this->string(fn() => 'huge-data-set'),
            ]),
            'struct-set' => $this->struct(fn() => [
                $this->string('name'),
                'email' => $this->resource->email,
                'casted' => $this->string(fn() => 'string'),
                $this->applyWhen(fn() => true, [
                    'with-apply-conditional-raw' => 'huge-data-set',
                ]),
                'closure' => fn() => 'closure',
                'missing' => $this->mixed(fn() => 'value')->when(false),
                'sub-struct' => $this->struct(fn() => [
                    'int' => $this->float(fn() => 200),
                    'float' => $this->float(fn() => 1.1),
                ]),
                'third-struct' => $this->struct(fn() => [
                    'int' => $this->float(fn() => 300),
                    'float' => $this->float(fn() => 3.1),
                ])->when(false),
            ]),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'main-post' => $this->one('post')->withLoad('post'),
            'posts' => $this->many()
                ->links(
                    self: fn() => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                    related: fn() => "https://api.example.com/user/{$this->resource->id}/posts",
                )
                ->meta(fn() => [
                    'total' => $this->resource->posts()->toBase()->count(),
                ])
                ->withLoad(true),
            'comments' => $this->many()
                ->whenLoaded()
                ->links(
                    self: fn() => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                    related: fn() => "https://api.example.com/user/{$this->resource->id}/comments",
                )
                ->meta(fn() => [
                    'total' => $this->resource->comments()->toBase()->count(),
                ])
                ->withLoad(['comments' => fn(Builder|Relation $query) => $query->where('content', 'like', '%e%')]),
        ];
    }
}
