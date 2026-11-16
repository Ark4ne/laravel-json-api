<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http;

use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Http\Rules\Linkage;

/**
 * The body of a JSON:API write request as descriptors of `ark4ne/laravel-api-revisions`, for the `body()` of a
 * request using its `RevisionedRequest` (docs/rfc/api-revisions-integration.md D12): the request declares its
 * attributes and its relationships only.
 *
 * ```php
 * protected function body(): array
 * {
 *     return Body::resource(
 *         PostResource::class,
 *         attributes: ['title' => Schema::string()->required()->max(255)],
 *         relationships: ['author' => Body::toOne(UserResource::class), 'tags' => Body::toMany(TagResource::class)],
 *     );
 * }
 * ```
 *
 * A `JsonApiFormRequest` writes `$this->resourceObject(attributes: …, relationships: …)`: the resource is its own.
 * The members have their history as any field (`->was(R, name: 'writer')`, `->since(R)`).
 *
 * @see https://jsonapi.org/format/#crud
 */
final class Body
{
    /**
     * The document of a resource object: `data.type` is the type of `$resource`, `data.attributes` and
     * `data.relationships` hold the members given, required when one of them is.
     *
     * @param class-string<DefinitionSource> $resource
     * @param array<string, Descriptor<mixed>> $attributes
     * @param array<string, Descriptor<mixed>> $relationships Their relationship objects: `toOne()`, `toMany()`
     * @param Descriptor<mixed>|null $id The `data.id` (`Schema::string()->required()` to update a resource, without
     *                                   `required()` for an id the client may generate); not accepted when `null`
     *
     * @return array<string, Descriptor<mixed>>
     */
    public static function resource(string $resource, array $attributes = [], array $relationships = [], ?Descriptor $id = null): array
    {
        $data = ['type' => self::type(Definitions::declaredType($resource))];
        if ($id !== null) {
            $data['id'] = $id;
        }
        if ($attributes !== []) {
            $data['attributes'] = self::members($attributes);
        }
        if ($relationships !== []) {
            $data['relationships'] = self::members($relationships);
        }

        return ['data' => Schema::object($data)->required()];
    }

    /**
     * A to-one relationship object: `{"data": {"type": …, "id": …}}`, or `{"data": null}` to clear it.
     *
     * @param class-string<DefinitionSource>|string ...$related The related resources, or their types
     */
    public static function toOne(string ...$related): ObjectSchema
    {
        // Sent with its relationship, and possibly null: `required` refuses a null.
        return Schema::object(['data' => self::identifier($related)->nullable()->rules(new Linkage())]);
    }

    /**
     * A to-many relationship object: `{"data": [{"type": …, "id": …}, …]}`, an empty list to clear it.
     *
     * @param class-string<DefinitionSource>|string ...$related The related resources, or their types
     */
    public static function toMany(string ...$related): ObjectSchema
    {
        return Schema::object(['data' => Schema::list(self::identifier($related))->required()]);
    }

    /**
     * @param array<string, Descriptor<mixed>> $members
     */
    private static function members(array $members): ObjectSchema
    {
        foreach ($members as $member) {
            if ($member->isRequired()) {
                return Schema::object($members)->required();
            }
        }

        return Schema::object($members);
    }

    /**
     * A resource identifier object of one of the types.
     *
     * @param array<array-key, string> $related
     */
    private static function identifier(array $related): ObjectSchema
    {
        $types = array_map(
            static fn(string $related): string => is_subclass_of($related, DefinitionSource::class)
                ? Definitions::declaredType($related)
                : $related,
            array_values($related),
        );

        return Schema::object([
            'type' => $types === [] ? Schema::string()->required() : self::type(...$types),
            'id' => Schema::string()->required(),
        ]);
    }

    /**
     * @return Descriptor<mixed>
     */
    private static function type(string ...$types): Descriptor
    {
        return Schema::string()->required()->rules('in:' . implode(',', $types));
    }
}
