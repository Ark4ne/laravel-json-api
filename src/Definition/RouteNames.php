<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

/**
 * The names of the routes a resource renders its links with (`#[JsonApiRoutes]`): `links.self` of its resource
 * objects, `links.self` and `links.related` of their relationships. A relationship route is a pattern in which
 * `{relationship}` stands for the name of the relationship.
 *
 * Immutable, serializable. Public contract: semver (docs/architecture.md §3).
 */
final readonly class RouteNames
{
    public const RELATIONSHIP = '{relationship}';

    /**
     * @param string|null $self The route of the resource object (`posts.show`)
     * @param string|null $relationship The route of the relationships (`posts.relationships.{relationship}`)
     * @param string|null $related The route of the related resources (`posts.{relationship}`)
     */
    public function __construct(
        public ?string $self = null,
        public ?string $relationship = null,
        public ?string $related = null,
    ) {}

    /**
     * The route of the `self` link of a relationship, `null` for none.
     */
    public function relationshipRoute(string $relationship): ?string
    {
        return $this->relationship === null ? null : str_replace(self::RELATIONSHIP, $relationship, $this->relationship);
    }

    /**
     * The route of the `related` link of a relationship, `null` for none.
     */
    public function relatedRoute(string $relationship): ?string
    {
        return $this->related === null ? null : str_replace(self::RELATIONSHIP, $relationship, $this->related);
    }

    /**
     * Rebuilds the route names exported with `var_export()` (compiled definitions).
     *
     * @param array{self: string|null, relationship: string|null, related: string|null} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }
}
