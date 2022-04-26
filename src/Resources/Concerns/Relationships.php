<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait Relationships
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, Relationship>|iterable<Relationship>
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
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, array{data?: mixed, links?: mixed, meta?: mixed}>
     */
    private function requestedRelationships(Request $request): array
    {
        $relations = [];

        foreach ($this->toRelationships($request) as $name => $relationship) {
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
     * @param bool                                   $included
     * @param \Illuminate\Http\Request               $request
     * @param \Ark4ne\JsonApi\Resources\Relationship $relationship
     *
     * @return array{data?: mixed, links?: mixed, meta?: mixed}
     */
    private function mapRelationship(
        bool $included,
        Request $request,
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

        return $resource['data'];
    }
}
