<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\ApiRevisions\Compilation\Probe as CompilationProbe;
use Ark4ne\ApiRevisions\Definition\Lifetime;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Probe;
use Illuminate\Http\Request;
use ReflectionClass;

/**
 * A resource is one `Revisioned` response of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md
 * D5): its top level holds one object per hook of values (`attributes`, `meta`, `documentMeta`), so the base compiles,
 * caches, checks, locks and exports it as any class. Its lifetime is the one of `#[JsonApiSince]` / `#[JsonApiUntil]`.
 *
 * Every member of those hooks is a descriptor of the base (`Schema::string()`…): a plain value cannot be declared.
 *
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait DeclaresSchema
{
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
     * @return array{attributes: ObjectSchema, meta: ObjectSchema, documentMeta: ObjectSchema}
     *
     * @throws DefinitionException when a member is not a descriptor of the base
     */
    public function describeSchema(Request $request): array
    {
        return [
            'attributes' => self::schemaObject('toAttributes', $this->toAttributes($request)),
            'meta' => self::schemaObject('toResourceMeta', $this->toResourceMeta($request) ?? []),
            'documentMeta' => self::schemaObject('toDocumentMeta', $this->toDocumentMeta($request) ?? []),
        ];
    }

    /**
     * @param iterable<array-key, mixed> $members
     */
    private static function schemaObject(string $hook, iterable $members): ObjectSchema
    {
        $children = [];
        foreach ($members as $name => $member) {
            if (!is_string($name) || $name === '' || !$member instanceof Descriptor) {
                throw DefinitionException::notASchema(static::class, $hook, (string) $name, get_debug_type($member));
            }
            $children[$name] = $member;
        }

        return new ObjectSchema($children);
    }
}
