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
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $for
     * @param null|string|Closure(T):mixed                                                                        $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationOne<T>
     */
    protected function one(string $for, string|Closure|null $relation = null): RelationOne
    {
        return new RelationOne($for, $relation);
    }

    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection> $for
     * @param null|string|Closure(T):mixed                                                                        $relation
     *
     * @return \Ark4ne\JsonApi\Descriptors\Relations\RelationMany<T>
     */
    protected function many(string $for, string|Closure|null $relation = null): RelationMany
    {
        return new RelationMany($for, $relation);
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
        return (new RelationMany(self::firstMorph($morphs), $relation))->morphs($morphs);
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
