<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait Relationships
{
    use PrepareData, Resolver;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation|Relationship|\Illuminate\Http\Resources\PotentiallyMissing>|iterable<array-key, \Ark4ne\JsonApi\Descriptors\Relations\Relation>
     *
     * ```
     * return [
     *     'avatar' => AvatarResource::relationship(fn() => $this->avatar),
     *     // as collection
     *     'posts' => PostResource::relationship(fn() => $this->posts)->asCollection(),
     *     // with laravel conditional relationships
     *     'comments' => CommentResource::relationship(fn() => $this->when($this->canComments(), fn() => $this->comments))->asCollection(),
     * ];
     * ```
     */
    protected function toRelationships(Request $request): iterable
    {
        return [];
    }

    /**
     * @internal
     *
     * @return array<string, string|callable>
     */
    public function requestedRelationshipsLoad(Request $request): array
    {
        $schema = self::schema($request);

        $walk = static function ($schema) use (&$walk, $request) {
            $loads = [];

            foreach ($schema->loads as $name => $load) {
                if ($load && Includes::include($request, $name)) {
                    $include = Includes::through($name, static fn() => $walk($schema->relationships[$name]));
                    foreach ((array)$load as $key => $value) {
                        if (is_string($value)) {
                            $loads[$value] = $include;
                        } elseif (is_string($key)) {
                            $loads[$key] = $value;
                            foreach (Arr::dot(Arr::undot($include), "$key.") as $inc => $item) {
                                $loads[$inc] = $item;
                            }
                        }
                    }
                }
            }

            return $loads;
        };

        return Arr::dot($walk($schema));
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, array{data?: mixed, links?: mixed, meta?: mixed}>
     */
    private function requestedRelationships(Request $request): array
    {
        $relations = [];
        $relationships = $this->toRelationships($request);
        $relationships = $this->mergeValues($relationships);
        $relationships = $this->resolveValues($request, $relationships);
        $relationships = $this->filter($relationships);

        foreach ($relationships as $name => $relationship) {
            $relationship->forRelation($name);

            $included = Includes::include($request, $name);

            $relations[$name] = Includes::through($name, fn() => $this->mapRelationship(
                $included,
                $request,
                $relationship
            ));
        }

        return $relations;
    }

    /**
     * @param bool $included
     * @param \Illuminate\Http\Request $request
     * @param \Ark4ne\JsonApi\Resources\Relationship $relationship
     *
     * @return array{data?: mixed, links?: mixed, meta?: mixed}
     */
    private function mapRelationship(
        bool         $included,
        Request      $request,
        Relationship $relationship
    ): array {
        $resource = $relationship->toArray($request, $included);

        if (isset($resource['included'])) {
            $this->with['included'] = array_merge(
                $this->with['included'] ?? [],
                $resource['included']
            );
        }

        if (isset($resource['with'])) {
            foreach ($resource['with'] as $key => $value) {
                $this->with[$key] = array_merge(
                    $this->with[$key] ?? [],
                    (new Collection($value))->all()
                );
            }
        }

        return $resource['data'] ?? [];
    }
}
