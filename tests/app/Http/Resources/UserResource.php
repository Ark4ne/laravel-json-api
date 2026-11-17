<?php

declare(strict_types=1);

namespace Test\app\Http\Resources;

use Ark4ne\ApiRevisions\Schema\Schema;
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
        $always = static fn(): bool => true;
        $never = static fn(): bool => false;

        return [
            'name' => Schema::string()->nullable()->whenHas(),
            'email' => Schema::string()->nullable()->whenHas(),
            'only-with-fields' => Schema::string(fn() => 'huge-data-set')->whenInFields(),
            'with-apply-conditional-raw' => Schema::string(fn() => 'huge-data-set')->when($always),
            'with-apply-conditional-closure' => Schema::string(fn() => 'huge-data-set')->when($always),
            'with-apply-conditional-value' => Schema::string(fn() => 'huge-data-set')->when($always),
            'struct-set' => Schema::object([
                'name' => Schema::string()->nullable(),
                'email' => Schema::string()->nullable(),
                'casted' => Schema::string(fn() => 'string'),
                'with-apply-conditional-raw' => Schema::string(fn() => 'huge-data-set')->when($always),
                'closure' => Schema::string(fn() => 'closure'),
                'missing' => Schema::mixed(fn() => 'value')->when($never),
                'sub-struct' => Schema::object([
                    'int' => Schema::float(fn() => 200),
                    'float' => Schema::float(fn() => 1.1),
                ]),
                'third-struct' => Schema::object([
                    'int' => Schema::float(fn() => 300),
                    'float' => Schema::float(fn() => 3.1),
                ])->when($never),
            ]),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => Schema::dateTime()->nullable()->whenHas(),
            'updated_at' => Schema::dateTime()->nullable()->whenHas(),
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
