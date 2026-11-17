<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;

/**
 * The conditions of a hook: the helpers of Laravel's resources (`when()`, `mergeWhen()`, `whenHas()`…) give plain
 * values, evaluated when the hook runs, once per response: a member is a descriptor, with its condition on it
 * (`->when()`, `->whenHas()`, `->whenInFields()`), or grouped with `applyWhen()`.
 */
trait ConditionallyLoadsAttributes
{
    /**
     * Renders a value only when a field of the resource is requested by the sparse fieldset of its type (`fields`).
     * The field is named as in the code: for a client on an older revision, by the name it has at that revision, the
     * condition failing for a field that does not exist at it. On the field itself, `->whenInFields()`.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor|Closure $value A descriptor, or a closure computing a value of any type
     *
     * @return ($value is Closure ? MixedSchema : TDescriptor)
     */
    protected function whenInFields(Request $request, string $attribute, Descriptor|Closure $value): Descriptor
    {
        $descriptor = $value instanceof Closure ? self::computed($value) : $value;

        // Read for each resource rendered: the hook runs once per response.
        return $descriptor->when(function () use ($request, $attribute): bool {
            $field = $this->nameAtRevision($attribute);

            return $field !== null && ($this->renderingContext?->fields->requests($this->toType($request), $field) ?? false);
        });
    }

    /**
     * Renders a value only when a relationship of the resource is included (`include`). Not to be confused with
     * `whenIncluded()` of the relationships. The relationship is named as in the code: for a client on an older
     * revision, by the name it has at that revision, the condition failing for one that does not exist at it.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor|Closure $value A descriptor, or a closure computing a value of any type
     *
     * @return ($value is Closure ? MixedSchema : TDescriptor)
     */
    protected function whenInIncludes(Request $request, string $relationship, Descriptor|Closure $value): Descriptor
    {
        $descriptor = $value instanceof Closure ? self::computed($value) : $value;

        // Read for each resource rendered, where it is rendered: the includes of its place in the document.
        return $descriptor->when(function () use ($relationship): bool {
            $name = $this->includeAtRevision($relationship);

            return $name !== null && ($this->renderingContext?->includes($name) ?? false);
        });
    }

    /**
     * The name a field of the code has at the revision of the rendering, `null` when it does not exist at it: the
     * client writes `fields` and `include` in the names of its revision. Of a dotted path, the first segment is
     * translated (a struct member keeps its name). A name no shape of the resource declares (a field of `toLinks()`)
     * is kept.
     */
    private function nameAtRevision(string $field): ?string
    {
        $context = $this->renderingContext;
        // Outside a rendering (the probe of the compilation, which must not ask for its own definition): no condition
        // passes anyway.
        if ($context === null) {
            return $field;
        }

        $segments = explode('.', $field, 2);
        $first = self::fieldAtRevision(static::class, $segments[0], $context->revision);

        return $first === null || !isset($segments[1]) ? $first : "{$first}.{$segments[1]}";
    }

    /**
     * The include path of the code `$path` at the revision of the rendering: each relationship by its name at that
     * revision, following the related resource of each one; `null` when one does not exist at it. Past a polymorphic
     * or native relationship, the rest of the path is kept.
     */
    private function includeAtRevision(string $path): ?string
    {
        $context = $this->renderingContext;
        if ($context === null) {
            return $path;
        }

        $class = static::class;
        $translated = [];
        $segments = explode('.', $path);
        foreach ($segments as $index => $segment) {
            $name = self::fieldAtRevision($class, $segment, $context->revision);
            if ($name === null) {
                return null;
            }

            $translated[] = $name;
            $relationship = Definitions::for($class, $context->revision)->relationships[$name] ?? null;
            if ($relationship === null || $relationship->isNative() || count(array_unique($relationship->related)) !== 1) {
                return implode('.', [...$translated, ...array_slice($segments, $index + 1)]);
            }

            $class = RelatedResource::resolve($relationship->related[0]);
            if (!Definitions::exists($class, $context->revision)) {
                return null;
            }
        }

        return implode('.', $translated);
    }

    /**
     * The name the field of the code `$field` of `$class` has at `$revision`, `null` when it does not exist at it; a
     * name no shape declares keeps it.
     *
     * @param class-string<\Ark4ne\JsonApi\Definition\DefinitionSource> $class
     */
    private static function fieldAtRevision(string $class, string $field, ?Revision $revision): ?string
    {
        // The head too: a field `until()` a revision does not exist there.
        return Definitions::for($class, $revision)->fieldNames[$field]
            ?? (Definitions::declaresField($class, $field) ? null : $field);
    }

    /**
     * Groups conditional members: each one is kept in the definition, marked conditional, and rendered when the
     * condition holds for its resource.
     *
     * ```php
     * $this->applyWhen(fn() => $request->user()?->isAdmin() ?? false, [
     *     'email' => $this->string(),
     *     'roles' => $this->many(RoleResource::class),
     * ]),
     * ```
     *
     * @param Closure(): bool $condition Read for each resource rendered: the hook runs once per response
     * @param iterable<string, mixed> $members Descriptors, relationships of a resource
     *        (`UserResource::relationship()`), or closures computing a value of any type
     *
     * @throws DefinitionException when a member is a plain value
     */
    protected function applyWhen(Closure $condition, iterable $members): MergeValue
    {
        $grouped = [];
        foreach ($members as $name => $member) {
            $descriptor = match (true) {
                $member instanceof Descriptor => $member,
                $member instanceof DescribesRelationship => new RelationshipSchema($member),
                $member instanceof Closure => self::computed($member),
                default => throw DefinitionException::notASchema(static::class, 'applyWhen', $name, get_debug_type($member)),
            };

            $grouped[$name] = $descriptor->when(static fn(): bool => $condition());
        }

        return new MergeValue($grouped);
    }

    /**
     * A closure computing a value of any type, nullable as a closure of a hook is (`describer.nullable`).
     */
    private static function computed(Closure $value): MixedSchema
    {
        return (new MixedSchema($value))->nullable(Definitions::configuration()->nullable);
    }
}
