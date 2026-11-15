<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\ApiRevisions\Compilation\Probe as CompilationProbe;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Lifetime;
use Ark4ne\ApiRevisions\Revisions\Unrevisioned;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Context\ResponseCache;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Schemas\Conditions;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Rendering\MemberFieldsets;
use Ark4ne\JsonApi\Rendering\ResourcePlan;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Memo;
use Ark4ne\JsonApi\Support\Probe;
use Illuminate\Http\Request;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * A resource is one `Revisioned` response of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md
 * D5): its top level holds one object per hook (`attributes`, `relationships`, `meta`, `documentMeta`), so the base compiles,
 * caches, checks, locks and exports it as any class. Its lifetime is the one of `#[JsonApiSince]` / `#[JsonApiUntil]`.
 *
 * Every member of those hooks is a descriptor of the base (`Schema::string()`…, `Schema::relationship()` for a
 * relationship): a plain value cannot be declared.
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
        return [
            'attributes' => static::hasDefaultAttributes() ? new ObjectSchema([]) : self::schemaObject('toAttributes', $this->toAttributes($request)),
            'relationships' => self::schemaObject('toRelationships', $this->toRelationships($request), RelationshipSchema::class),
            'meta' => self::schemaObject('toResourceMeta', $this->toResourceMeta($request) ?? []),
            'documentMeta' => self::schemaObject('toDocumentMeta', $this->toDocumentMeta($request) ?? []),
        ];
    }

    /**
     * Whether the hooks of values of the class return descriptors of the base, and at least one: its values are then
     * rendered by it. Decided on a stand-in of the model; `Definitions::declaresSchema()` remembers it.
     *
     * @internal Until every resource is declared so (docs/rfc/api-revisions-integration.md, US 2.4).
     */
    public static function declaresSchema(): bool
    {
        $resource = static::newDefinitionSource(Probe::root());

        try {
            $schema = $resource->describeSchema(new Request());
        } catch (DefinitionException $e) {
            // A hook mixing the descriptors of the base and the 2.0 ones would render the former as plain objects.
            return self::declaresAnyDescriptor($resource) ? throw $e : false;
        } catch (Throwable) {
            return false;
        }

        // Hooks declaring nothing on a stand-in (the default attributes of a model) render as before.
        return $schema['attributes']->children() !== []
            || $schema['relationships']->children() !== []
            || $schema['meta']->children() !== []
            || $schema['documentMeta']->children() !== [];
    }

    /**
     * Whether a hook of the resource returns a descriptor of the base; `false` when a hook fails on the stand-in.
     *
     * @param self<mixed> $resource
     */
    private static function declaresAnyDescriptor(self $resource): bool
    {
        $request = new Request();

        try {
            $hooks = [
                $resource->toAttributes($request),
                $resource->toRelationships($request),
                $resource->toResourceMeta($request) ?? [],
                $resource->toDocumentMeta($request) ?? [],
            ];
            foreach ($hooks as $members) {
                foreach ($members as $member) {
                    if ($member instanceof Descriptor) {
                        return true;
                    }
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
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
     * The plan of the values of the resources of this class and type in the response of `$context`: built once, on a
     * template instance (a copy of this one) whose hooks run once.
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
        $revision = $context->revision ?? Unrevisioned::Head;
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

                return new ResourcePlan(
                    static function (mixed $model) use ($template): void {
                        $template->resource = $model;
                    },
                    $projector->plan($children('attributes'), $schema['attributes']->children(), $keys),
                    $projector->plan($children('meta'), $schema['meta']->children()),
                    $projector->plan($children('documentMeta'), $schema['documentMeta']->children()),
                    $restrict,
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
     * The relationships of this instance visible at the revision of `$context`, by their name at it: each one the
     * relation in force there (`RelationshipSchema::relationAt()`), read under its name of the code.
     *
     * @internal
     *
     * @return iterable<string, array{string, Relation<mixed>}> The name of the code and the relation, by name at the revision
     */
    public function relationsAt(JsonApiContext $context): iterable
    {
        // The relationships of the shape of the revision, read once per response.
        $fields = $context->cache->remember('relationships:' . static::class, fn(): array => $context->projection->shapes
            ->for(static::class, DeclarationKind::Response, $context->revision ?? Unrevisioned::Head)
            ->field('relationships')?->children->fields ?? []);
        if ($fields === []) {
            return;
        }

        // The hook runs for each resource: the closures of a relation are read after its resource is rendered.
        $declared = $this->toRelationships($context->request);
        $declared = is_array($declared) ? $declared : iterator_to_array($declared);

        foreach ($fields as $key => $field) {
            $relationship = $declared[$key] ?? null;
            if ($relationship instanceof RelationshipSchema) {
                yield $field->name => [$key, $relationship->relationAt($field)];
            }
        }
    }

    /**
     * @param iterable<array-key, mixed> $members
     * @param class-string<Descriptor<mixed>> $of What every member is
     */
    private static function schemaObject(string $hook, iterable $members, string $of = Descriptor::class): ObjectSchema
    {
        $children = [];
        foreach ($members as $name => $member) {
            if (!is_string($name) || $name === '' || !$member instanceof $of) {
                throw DefinitionException::notASchema(static::class, $hook, (string) $name, get_debug_type($member));
            }
            $children[$name] = $member;
        }

        return new ObjectSchema($children);
    }
}
