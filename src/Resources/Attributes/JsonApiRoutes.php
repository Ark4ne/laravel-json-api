<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Attributes;

use Ark4ne\JsonApi\Definition\RouteNames;
use Attribute;

/**
 * The routes the links of the resource are generated with, by route name: `#[JsonApiRoutes(self: 'posts.show')]`.
 *
 * - `self`: `links.self` of the resource objects. The route must exist; it receives the resource id.
 * - `relationship`, `related`: `links.self` and `links.related` of the relationships, `{relationship}` standing for
 *   the name of the relationship. By default, derived from the prefix of `self` (`posts.relationships.{relationship}`
 *   and `posts.{relationship}`); a link is rendered only when its route exists. `false` renders none.
 *
 * Links declared by the resource (`toLinks()`, `->links()`) win.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiRoutes
{
    public function __construct(
        public ?string $self = null,
        public string|false|null $relationship = null,
        public string|false|null $related = null,
    ) {}

    public function routeNames(): RouteNames
    {
        $prefix = $this->self !== null && str_contains($this->self, '.')
            ? substr($this->self, 0, (int) strrpos($this->self, '.'))
            : null;

        return new RouteNames(
            self: $this->self,
            relationship: self::pattern($this->relationship, $prefix, 'relationships.' . RouteNames::RELATIONSHIP),
            related: self::pattern($this->related, $prefix, RouteNames::RELATIONSHIP),
        );
    }

    private static function pattern(string|false|null $declared, ?string $prefix, string $convention): ?string
    {
        return match (true) {
            $declared === false => null,
            $declared !== null => $declared,
            $prefix !== null => "{$prefix}.{$convention}",
            default => null,
        };
    }
}
