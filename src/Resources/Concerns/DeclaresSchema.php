<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\ApiRevisions\Compilation\Probe as CompilationProbe;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Lifetime;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Context\ResponseCache;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Schemas\Conditions;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Rendering\MemberFieldsets;
use Ark4ne\JsonApi\Rendering\ResourcePlan;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Memo;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use LogicException;
use ReflectionClass;
use ReflectionMethod;

/**
 * A resource is one `Revisioned` response of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md
 * D5): its top level holds one object per hook (`attributes`, `relationships`, `meta`, `documentMeta`), so the base compiles,
 * caches, checks, locks and exports it as any class. Its lifetime is the one of `#[JsonApiSince]` / `#[JsonApiUntil]`.
 *
 * Every member of those hooks is a descriptor of the base (`$this->string()`, `Schema::string()`…, `$this->one()` for
 * a relationship) or a closure: a plain value cannot be declared.
 *
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait DeclaresSchema
{
    /** The plan of the values of this resource in the response of `$valuesPlanOf`. */
    private ?ResourcePlan $valuesPlan = null;

    private ?ResponseCache $valuesPlanOf = null;

    /**
     * @internal Read by `ark4ne/laravel-api-revisions`.
     */
    public static function declarationKinds(): array
    {
        return [DeclarationKind::Response];
    }

    /**
     * The resource built on a stand-in of its model when the base compiles it, on `$subject` otherwise: no source,
     * closure or condition runs while declaring.
     *
     * @internal Read by `ark4ne/laravel-api-revisions`.
     *
     * @return array<string, Descriptor<mixed>>
     *
     * @throws DefinitionException when a member is not a descriptor of the base
     */
    public static function describeRevisioned(DeclarationKind $kind, mixed $subject): array
    {
        $resource = static::newDefinitionSource(Probe::root());
        if (!$subject instanceof CompilationProbe) {
            $resource->resource = $subject;
        }

        return $resource->describeSchema(new Request());
    }

    /**
     * The revisions the resource exists in (`#[JsonApiSince]`, `#[JsonApiUntil]`, read on the class itself: a subclass
     * does not inherit them), bounds included.
     *
     * @internal Read by `ark4ne/laravel-api-revisions`.
     */
    public static function revisionedLifetime(): Lifetime
    {
        $reflection = new ReflectionClass(static::class);
        $since = ($reflection->getAttributes(JsonApiSince::class)[0] ?? null)?->newInstance()->revision;
        $until = ($reflection->getAttributes(JsonApiUntil::class)[0] ?? null)?->newInstance()->revision;

        return Lifetime::always()->withSince($since)->withUntil($until);
    }

    /**
     * The declaration of this instance: the descriptors of its hooks of values, whose closures read its `resource`.
     *
     * @internal
     *
     * @return array{attributes: ObjectSchema, relationships: ObjectSchema, meta: ObjectSchema, documentMeta: ObjectSchema}
     *
     * @throws DefinitionException when a member is not a descriptor of the base
     */
    public function describeSchema(Request $request): array
    {
        $configuration = $this->renderingContext->configuration ?? Definitions::configuration();

        return [
            'attributes' => static::hasDefaultAttributes()
                ? new ObjectSchema([])
                : $this->schemaObject('toAttributes', $this->toAttributes($request), $configuration->autoWhenHas('attributes')),
            'relationships' => new ObjectSchema(array_map(
                $this->autoWhenIncluded(...),
                $this->relationshipsOf($this->toRelationships($request)),
            )),
            'meta' => $this->schemaObject('toResourceMeta', $this->toResourceMeta($request) ?? [], $configuration->autoWhenHas('resource-meta')),
            'documentMeta' => $this->schemaObject('toDocumentMeta', $this->toDocumentMeta($request) ?? [], $configuration->autoWhenHas('meta')),
        ];
    }

    /**
     * Whether the resource keeps the default attributes (it does not write `toAttributes()`): those of its model,
     * which no shape can declare. They are rendered as they are, outside the declaration.
     *
     * @internal
     */
    public static function hasDefaultAttributes(): bool
    {
        return Memo::remember(DeclaresSchema::class, static::class, static fn(): bool => (new ReflectionMethod(static::class, 'toAttributes'))
            ->getDeclaringClass()->getName() === JsonApiResource::class);
    }

    /**
     * The plan of the values and of the relationships of the resources of this class and type in the response of
     * `$context`: built once, on a template instance (a copy of this one) whose hooks run once.
     *
     * @internal
     */
    public function valuesPlan(JsonApiContext $context): ResourcePlan
    {
        // Asked once per hook of values: the plan of this resource in this response is looked up once.
        if ($this->valuesPlan !== null && $this->valuesPlanOf === $context->cache) {
            return $this->valuesPlan;
        }

        $type = $this->toType($context->request);
        // No revision is the head of the application, as for its definition.
        $revision = $context->revision ?? Definitions::head();
        $this->valuesPlanOf = $context->cache;

        return $this->valuesPlan = $context->cache->remember(
            'values:' . static::class . ':' . $type . ':' . $revision->name,
            function () use ($context, $type, $revision): ResourcePlan {
                $template = clone $this;
                $template->renderingContext = $context;
                $schema = $template->describeSchema($context->request);
                $shape = $context->projection->shapes->for(static::class, DeclarationKind::Response, $revision);
                $projector = $context->projection->projector;

                $children = static fn(string $object): Shape => $shape->field($object)->children
                    ?? throw DefinitionException::cannotCompile(static::class, new LogicException("The shape has no [{$object}] object."));

                // The sparse fieldset is written in the names of the revision: it keeps the fields it allows, and a field
                // only in fields (`whenInFields()`) when it names it.
                $keys = [];
                $kept = [];
                $attributes = $schema['attributes']->children();
                foreach ($children('attributes')->fields as $key => $field) {
                    $requested = isset($attributes[$key]) && Conditions::isOnlyInFields($attributes[$key])
                        ? $context->fields->requests($type, $field->name)
                        : $context->fields->allows($type, $field->name);
                    if ($requested) {
                        $keys[] = $key;
                        $kept[] = $field;
                    }
                }

                // The fieldset may name the members of an object (`fields[users]=address.city`).
                $restrict = $context->fields->restricts($type) ? MemberFieldsets::of($kept, $context->fields, $type) : null;

                // The relationships of the revision: the relation in force at it, its guessed resource the one of the
                // definition (guessed once, when compiled). The conditions of the field (`->when()`, `applyWhen()`)
                // are read by the plan: the base never reads a relationship.
                $definitions = Definitions::for(static::class, $context->revision)->relationships;
                $declared = $schema['relationships']->children();
                $relationships = [];
                foreach ($children('relationships')->fields as $key => $field) {
                    $relationship = $declared[$key] ?? null;
                    if ($relationship instanceof RelationshipSchema) {
                        $relation = $relationship->relationAt($field);
                        $definition = $definitions[$field->name] ?? null;
                        $relationships[$field->name] = [
                            $key,
                            $relation instanceof RelationRaw
                                ? $relation
                                : $relation->resolveRelated($key, $definition?->related[0] ?? $definition?->native),
                            $relationship->conditions(),
                        ];
                    }
                }

                return new ResourcePlan(
                    static function (mixed $model, JsonApiContext $context) use ($template): void {
                        $template->resource = $model;
                        $template->renderingContext = $context;
                    },
                    $projector->plan($children('attributes'), $schema['attributes']->children(), $keys),
                    $projector->plan($children('meta'), $schema['meta']->children()),
                    $projector->plan($children('documentMeta'), $schema['documentMeta']->children()),
                    $restrict,
                    $relationships,
                );
            },
        );
    }

    /**
     * @internal
     */
    public static function describeRelationshipsAt(Shape $relationships): array
    {
        $declared = static::newDefinitionSource(Probe::root())->describeSchema(new Request())['relationships']->children();

        $described = [];
        foreach ($relationships->fields as $key => $field) {
            $relationship = $declared[$key] ?? null;
            if ($relationship instanceof RelationshipSchema) {
                $relation = $relationship->relationAt($field);
                $described[] = [
                    // Described under its name of the code (its source, its guessed resource), named as the revision does.
                    $relation->definition($key)->name($field->name)->conditional($field->conditional || $relation->isConditional()),
                    $relation->loadConstraints(),
                ];
            }
        }

        return $described;
    }

    /**
     * @internal
     */
    public static function sparseAttributes(): array
    {
        $attributes = static::newDefinitionSource(Probe::root())->describeSchema(new Request())['attributes']->children();

        return array_keys(array_filter($attributes, Conditions::isOnlyInFields(...)));
    }

    /**
     * The object of a hook of values: its descriptors by name, a closure a value of any type. `whenHas()` applies to
     * the members read from a key of the model when the resource says so (`$autoWhenHas`), otherwise when the
     * configuration of the hook does (`describer.when-has`).
     *
     * @param iterable<array-key, mixed> $members
     *
     * @throws DefinitionException when a member is a plain value or has no name
     */
    private function schemaObject(string $hook, iterable $members, bool $whenHas): ObjectSchema
    {
        $whenHas = $this->autoWhenHas ?? $whenHas;
        $nullable = Definitions::configuration()->nullable;

        $children = [];
        foreach (self::membersOf($hook, $members) as $name => $member) {
            if ($member instanceof Closure) {
                $member = (new MixedSchema($member))->nullable($nullable);
            }
            if (!$member instanceof Descriptor) {
                throw DefinitionException::notASchema(static::class, $hook, $name, get_debug_type($member));
            }

            $source = $member->source();
            $children[$name] = $whenHas && $source->closure === null && !$source->parent && $member->presences() === []
                ? $member->whenHas()
                : $member;
        }

        return new ObjectSchema($children);
    }

    /**
     * The relationships of `toRelationships()` by name: those of `$this->one()`, `$this->many()`…, and the
     * relationships of a resource (`UserResource::relationship()`).
     *
     * @param iterable<array-key, mixed> $members
     *
     * @return array<string, RelationshipSchema<mixed>>
     *
     * @throws DefinitionException when a member is not a relationship or has no name
     */
    private function relationshipsOf(iterable $members): array
    {
        $relationships = [];
        foreach (self::membersOf('toRelationships', $members) as $name => $member) {
            $relationships[$name] = match (true) {
                $member instanceof RelationshipSchema => $member,
                $member instanceof DescribesRelationship => new RelationshipSchema($member),
                default => throw DefinitionException::notASchema(static::class, 'toRelationships', $name, get_debug_type($member)),
            };
        }

        return $relationships;
    }

    /**
     * The members of a hook by name, those grouped by `applyWhen()` among the others.
     *
     * @param iterable<array-key, mixed> $members
     *
     * @return array<string, mixed>
     *
     * @throws DefinitionException when a member has no name: a `when()` or a `mergeWhen()` of Laravel's resources,
     *                             evaluated when the hook runs
     */
    private static function membersOf(string $hook, iterable $members): array
    {
        $named = [];
        foreach ($members as $name => $member) {
            if (is_int($name) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $grouped */
                $grouped = $member->data;
                $named = [...$named, ...self::membersOf($hook, $grouped)];
            } elseif (is_string($name) && $name !== '') {
                $named[$name] = $member;
            } else {
                throw DefinitionException::notASchema(static::class, $hook, (string) $name, get_debug_type($member));
            }
        }

        return $named;
    }
}
