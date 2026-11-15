<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Conversion\Converters;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\DeclaresConversions;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;

/**
 * A relationship as a field of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md D3): the base
 * gives it its history (`since()`, `until()`, `was(R, name:, schema:)`), its name and its visibility at each
 * revision; this package keeps what is JSON:API in the relation it holds (related resource, cardinality, loads,
 * links, meta, filters, linkage) and resolves it itself.
 *
 * A custom type of the base (`jsonapi.relationship`): its fragment names the cardinality and the related resources,
 * so a change of them shows in the lock, the export and the changelog. A relationship to another resource at an old
 * revision is `->was(R, schema: Schema::relationship($this->one(OldResource::class)))`: no value is converted.
 *
 * @extends Descriptor<mixed>
 */
final class RelationshipSchema extends Descriptor implements DeclaresConversions
{
    public const TYPE = 'jsonapi.relationship';

    /**
     * @param Relation<mixed> $relation
     */
    public function __construct(private readonly Relation $relation)
    {
        // No source: the base never reads a relationship, and the schema of a `was()` may not declare one.
        parent::__construct();
    }

    public static function conversions(Converters $converters): Converters
    {
        return $converters->identity(self::TYPE);
    }

    /**
     * The relation of the head.
     *
     * @return Relation<mixed>
     */
    public function relation(): Relation
    {
        return $this->relation;
    }

    /**
     * The relation in force at the revision of `$field`, a field of a shape compiled from this descriptor: the one of
     * the `was(schema:)` the shape puts in force, the head's otherwise.
     *
     * @return Relation<mixed>
     */
    public function relationAt(FieldDefinition $field): Relation
    {
        $schema = $field->schemaChange === null ? null : $this->history()->changes[$field->schemaChange]->schema ?? null;

        return $schema instanceof self ? $schema->relation : $this->relation;
    }

    /**
     * Any value: the related models are read by this package, never by the base.
     */
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    protected function type(): TypeDefinition
    {
        $related = array_values($this->relation->morphMap());
        if ($this->relation->related() !== null) {
            $related[] = $this->relation->related();
        }
        $related = array_values(array_unique($related));
        sort($related);

        return TypeDefinition::custom(self::TYPE, [
            'type' => 'object',
            'x-jsonapi-cardinality' => $this->relation instanceof RelationMany ? 'many' : 'one',
            'x-jsonapi-related' => $related,
        ]);
    }
}
