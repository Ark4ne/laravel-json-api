<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\Relationship;
use Ark4ne\JsonApi\Resource\Support\Includes;
use Illuminate\Http\Request;

trait Relationships
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, <Closure():<Relationship> | Relationship>>
     *
     * ```
     * return [
     *     'avatar' => AvatarResource::make($this->avatar),
     *     // with lazy evaluation
     *     'posts' => fn () => PostResource::collection($this->posts),
     *     // with laravel conditional relationships
     *     'comments' => $this->when($this->canComments(), fn() => fn() => CommentResource::collection($this->comments),
     * ];
     * ```
     */
    protected function toRelationships(Request $request): iterable
    {
        return [];
    }

    private function requestedRelationships(Request $request): array
    {
        $relations = [];
        $relationships = $this->toRelationships($request);

        foreach ($this->filter($relationships) as $name => $relationship) {
            $relationship = value($relationship);

            if (!($relationship instanceof Relationship)) {
                $relationship = new Relationship($relationship);
            }

            $minimal = !Includes::include($request, $name);

            $relations[$name] = Includes::through($name, fn() => $this->mapRelationship(
                $minimal,
                $request,
                $relationship
            ));
        }

        return $relations;
    }

    private function mapRelationship(
        bool $minimal,
        Request $request,
        Relationship $relationship
    ): array {
        $resource = $relationship->toArray($request, $minimal);

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
                    collect($value)->all()
                );
            }
        }

        return $resource['data'] ?? [];
    }
}
