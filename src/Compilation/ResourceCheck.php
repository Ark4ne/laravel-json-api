<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Checking\Check;
use Ark4ne\ApiRevisions\Checking\CheckContext;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Schema\ClassLifetime;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;

/**
 * The rules of JSON:API the check of `ark4ne/laravel-api-revisions` does not know (docs/rfc/api-revisions-integration.md
 * D13), on a resource, revision by revision:
 *
 * - `jsonapi-reserved-name`: an attribute or a relationship named `id` or `type`;
 * - `jsonapi-name-collision`: an attribute and a relationship sharing a name (they share one name space);
 * - `jsonapi-absent-resource`: a relationship to a resource that does not exist at the revision.
 *
 * Tagged by the service provider (`RevisionsServiceProvider::CHECKS`): run by `revision:check` and
 * `assertRevisionsAreConsistent()`.
 */
final readonly class ResourceCheck implements Check
{
    public const RESERVED_NAME = 'jsonapi-reserved-name';

    public const NAME_COLLISION = 'jsonapi-name-collision';

    public const ABSENT_RESOURCE = 'jsonapi-absent-resource';

    private const RESERVED = ['id', 'type'];

    public function run(CheckContext $context): void
    {
        if ($context->class === null || !is_subclass_of($context->class, DefinitionSource::class)) {
            return;
        }

        $declared = $context->descriptors(DeclarationKind::Response)['relationships'] ?? null;
        $declared = $declared instanceof ObjectSchema ? $declared->children() : [];

        foreach ($context->order() as $revision) {
            $shape = $context->shape(DeclarationKind::Response, $revision);
            $attributes = $shape->field('attributes')?->children;
            $relationships = $shape->field('relationships')?->children;

            // A field is named by its key of the code, under its object: as the checks of the base name it.
            foreach (['attributes' => $attributes, 'relationships' => $relationships] as $object => $fields) {
                foreach ($fields->fields ?? [] as $key => $field) {
                    if (in_array($field->name, self::RESERVED, true)) {
                        $context->error(
                            self::RESERVED_NAME,
                            "[{$field->name}] is reserved by JSON:API for the identification of the resource: rename the member.",
                            "{$object}.{$key}",
                            $revision,
                        );
                    }
                }
            }

            foreach (array_intersect(self::names($attributes), self::names($relationships)) as $key => $name) {
                $context->error(
                    self::NAME_COLLISION,
                    "[{$name}] names an attribute and a relationship: they share one name space, bound one of them with until() / since().",
                    "attributes.{$key}",
                    $revision,
                );
            }

            foreach ($relationships->fields ?? [] as $key => $field) {
                $relationship = $declared[$key] ?? null;
                if (!$relationship instanceof RelationshipSchema) {
                    continue;
                }
                $relation = $relationship->relationAt($field);
                $relatedTo = array_values($relation->morphMap());
                if ($relation->related() !== null) {
                    $relatedTo[] = $relation->related();
                }
                foreach ($relatedTo as $related) {
                    if (!ClassLifetime::exists($related, $revision)) {
                        $context->error(
                            self::ABSENT_RESOURCE,
                            "[{$field->name}] relates to [{$related}], which does not exist at this revision: give it a was() to the resource of the revision, or bound it with since() / until().",
                            "relationships.{$key}",
                            $revision,
                        );
                    }
                }
            }
        }
    }

    /**
     * @return array<string, string> The name at the revision of the shape, by name of the code
     */
    private static function names(?Shape $shape): array
    {
        return $shape === null ? [] : SchemaAttributes::names($shape);
    }
}
