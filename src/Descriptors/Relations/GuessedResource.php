<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Guesses the resource of a relationship declared without resource class (`$this->one('author')`), as Laravel's
 * `Model::toResource()` does, from the Eloquent model of the resource:
 *
 * 1. the model of the resource: `#[JsonApiModel]`, otherwise by convention (`App\Http\Resources\PostResource` →
 *    `App\Models\Post`);
 * 2. the related model: `getRelated()` of its relation (`Post::author()`), built without query;
 * 3. the resource of the related model: `#[UseResource]`, otherwise `guessResourceName()` (`App\Models\User` →
 *    `App\Http\Resources\UserResource`).
 *
 * A resource whose model is not an Eloquent model gives the resource class of its relationships.
 *
 * @internal
 */
final class GuessedResource
{
    /**
     * @param class-string|null $resource The resource declaring the relationship
     * @param string $relation The relation of its model
     *
     * @return class-string<DefinitionSource>
     *
     * @throws DefinitionException when the resource cannot be guessed
     */
    public static function of(?string $resource, string $relation): string
    {
        $fail = static fn(string $reason): DefinitionException
            => DefinitionException::unresolvedRelated($resource ?? 'relationship', $relation, $reason);

        $model = self::model($resource) ?? throw $fail('no model: declare it with #[JsonApiModel]');

        if (!is_subclass_of($model, Model::class)) {
            throw $fail("[{$model}] is not an Eloquent model");
        }

        if (!self::mayBeRelation($model, $relation)) {
            throw $fail("[{$model}] has no relation [{$relation}]");
        }

        $method = [new $model(), $relation];
        $eloquent = is_callable($method) ? $method() : null;

        if (!$eloquent instanceof EloquentRelation) {
            throw $fail("[{$model}::{$relation}()] is no relation");
        }

        if ($eloquent instanceof MorphTo) {
            throw $fail('polymorphic: declare it with morph()');
        }

        $related = $eloquent->getRelated()::class;

        $declared = (new ReflectionClass($related))->getAttributes(UseResource::class)[0] ?? null;

        if ($declared !== null) {
            $class = $declared->newInstance()->class;

            // The resource the model declares, as `toResource()` would render it: never another one.
            return is_subclass_of($class, DefinitionSource::class)
                ? $class
                : throw $fail("#[UseResource] of [{$related}] is not a JSON:API resource");
        }

        foreach ($related::guessResourceName() as $class) {
            if (is_subclass_of($class, DefinitionSource::class)) {
                return $class;
            }
        }

        throw $fail("no resource for [{$related}]: declare it with #[UseResource]");
    }

    /**
     * Whether a method of the model may give a relation, checked before calling it: a public method without required
     * parameter, of the application (not one of Eloquent: `save()`, `push()`…), returning a relation when typed.
     *
     * @param class-string<Model> $model
     */
    private static function mayBeRelation(string $model, string $method): bool
    {
        if (!method_exists($model, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($model, $method);
        $type = $reflection->getReturnType();

        return $reflection->isPublic()
            && !$reflection->isStatic()
            && $reflection->getNumberOfRequiredParameters() === 0
            && !str_starts_with($reflection->getDeclaringClass()->getName(), 'Illuminate\\')
            && (!$type instanceof ReflectionNamedType || is_a($type->getName(), EloquentRelation::class, true));
    }

    /**
     * @return class-string|null
     */
    private static function model(?string $resource): ?string
    {
        if ($resource === null || !is_subclass_of($resource, DefinitionSource::class)) {
            return null;
        }

        $model = $resource::declaredModel();

        if ($model !== null) {
            return $model;
        }

        // The reverse of `Model::guessResourceName()`, for the resource then its parents.
        foreach ([$resource, ...array_values(class_parents($resource))] as $class) {
            $segments = explode('\\Http\\Resources\\', $class, 2);

            foreach (count($segments) === 2 ? array_unique([preg_replace('/Resource$/', '', $segments[1]), $segments[1]]) : [] as $name) {
                $model = "{$segments[0]}\\Models\\{$name}";

                if (class_exists($model)) {
                    return $model;
                }
            }
        }

        return null;
    }
}
