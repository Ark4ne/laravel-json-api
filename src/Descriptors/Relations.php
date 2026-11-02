<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;

/**
 * @template T
 */
trait Relations
{
    /**
     * A to-one relationship. Without resource class, the resource is guessed from the Eloquent model of the resource,
     * as `Model::toResource()` does (`GuessedResource`): `$this->one()` reads the relation named like the member,
     * `$this->one('author')` the relation `author`. A resource whose model is not an Eloquent model passes the class.
     * A resource of Laravel's native JSON:API (`Illuminate\Http\Resources\JsonApi\JsonApiResource`) is rendered as a
     * leaf: its type, id, attributes, links and meta, never its relationships, nothing included under it.
     *
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection|\Illuminate\Http\Resources\JsonApi\JsonApiResource>|string|null $for
     *        The resource class; a name without namespace is the relation (`'author'`)
     * @param null|string|Closure(T):mixed $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T>
     */
    protected function one(?string $for = null, string|Closure|null $relation = null): RelationOne
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T> */
        return new RelationOne(...self::declared($for, $relation));
    }

    /**
     * A to-many relationship. Without resource class, the resource is guessed like that of `one()`:
     * `$this->many()`, `$this->many('comments')`.
     *
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection|\Illuminate\Http\Resources\JsonApi\JsonApiResource>|string|null $for
     *        The resource class; a name without namespace is the relation (`'comments'`)
     * @param null|string|Closure(T):mixed $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T>
     */
    protected function many(?string $for = null, string|Closure|null $relation = null): RelationMany
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T> */
        return new RelationMany(...self::declared($for, $relation));
    }

    /**
     * A polymorphic to-one relationship (`morphTo`): the related model is rendered with the resource of its class.
     *
     * ```php
     * 'commentable' => $this->morph([Post::class => PostResource::class, Video::class => VideoResource::class]),
     * ```
     *
     * @param array<class-string, class-string<\Ark4ne\JsonApi\Resources\JsonApiResource<*>>> $morphs Resource class
     *                                                                                                     by model class
     * @param null|string|Closure(T):mixed $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T>
     */
    protected function morph(array $morphs, string|Closure|null $relation = null): RelationOne
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T> */
        return (new RelationOne(self::firstMorph($morphs), $relation))->morphs($morphs);
    }

    /**
     * A polymorphic to-many relationship: each related model is rendered with the resource of its class.
     *
     * @param array<class-string, class-string<\Ark4ne\JsonApi\Resources\JsonApiResource<*>>> $morphs Resource class
     *                                                                                                     by model class
     * @param null|string|Closure(T):mixed $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T>
     */
    protected function morphMany(array $morphs, string|Closure|null $relation = null): RelationMany
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T> */
        return (new RelationMany(self::firstMorph($morphs), $relation))->morphs($morphs);
    }

    /**
     * The resource class, the relation and the resource declaring them: a name without namespace is the relation, its
     * resource guessed (`'author'`, never a class: a resource class has a namespace).
     *
     * @return array{0: class-string<\Ark4ne\JsonApi\Resources\JsonApiResource<mixed>|\Ark4ne\JsonApi\Resources\JsonApiCollection<\Ark4ne\JsonApi\Resources\JsonApiResource<mixed>>|\Illuminate\Http\Resources\JsonApi\JsonApiResource>|null, 1: string|Closure|null, 2: class-string}
     */
    private static function declared(?string $for, string|Closure|null $relation): array
    {
        if ($for !== null && $relation === null && !str_contains($for, '\\')) {
            return [null, $for, static::class];
        }

        /** @var class-string<\Ark4ne\JsonApi\Resources\JsonApiResource<mixed>|\Ark4ne\JsonApi\Resources\JsonApiCollection<\Ark4ne\JsonApi\Resources\JsonApiResource<mixed>>|\Illuminate\Http\Resources\JsonApi\JsonApiResource>|null $for Checked when compiled (`RelatedResource`) */
        return [$for, $relation, static::class];
    }

    /**
     * @template R
     *
     * @param array<class-string, R> $morphs
     *
     * @return R
     *
     * @throws DefinitionException when the map is empty
     */
    private static function firstMorph(array $morphs): mixed
    {
        return $morphs !== [] ? $morphs[array_key_first($morphs)] : throw DefinitionException::emptyMorphMap();
    }
}
