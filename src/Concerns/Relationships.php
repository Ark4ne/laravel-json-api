<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Ark4ne\JsonApi\Resource\JsonApiCollection;
use Ark4ne\JsonApi\Resource\JsonApiResource;
use Ark4ne\JsonApi\Resource\Support\Included;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\PotentiallyMissing;

use function value;

trait Relationships
{
    /**
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array<string, <Closure():<JsonApiResource|JsonApiCollection> | JsonApiResource | JsonApiCollection>>
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

    /**
     * @see https://jsonapi.org/format/#document-resource-object-related-resource-links
     *
     * @param string                            $relation
     * @param JsonApiResource|JsonApiCollection $resource
     *
     * @return array<string, string>|null
     *
     * ```
     * return [
     *     'self' => route('api.user.relationships', ['id' => $this->id, 'relationships' => $relation]),
     *     'related' => route('api.user.related', ['id' => $this->id, 'relationships' => $relation]),
     * ];
     * ```
     */
    protected function toRelationshipLinks(string $relation, JsonApiCollection|JsonApiResource $resource): ?iterable
    {
        return null;
    }

    private function requestedRelationships(Request $request): array
    {
        $included = Included::get($request);

        $relations = [];
        $relationships = (array)$this->toRelationships($request);

        foreach ($this->filter($relationships) as $name => $relationship) {
            $relationship = value($relationship);
            if ($relationship instanceof JsonApiResource || $relationship instanceof JsonApiCollection) {
                $relations[$name] = Included::through($name, fn() => $this->mapRelationshipResource(
                    !in_array($name, $included, true),
                    $name,
                    $request,
                    $relationship
                ));
            }
        }

        return $relations;
    }

    private function mapRelationshipResource(
        bool $minimal,
        string $name,
        Request $request,
        JsonApiCollection|JsonApiResource $resource
    ): array {
        $relation = [];

        if (!empty($links = $this->toRelationshipLinks($name, $resource))) {
            $relation['links'] = $links;
        }

        if ($this->valueIsMissing($resource)) {
            return $relation;
        }

        $data = $resource->toArray($request, $minimal);

        $relation['data'] = [];
        if ($resource instanceof JsonApiCollection) {
            foreach ($data as $value) {
                $relation['data'][] = [
                    'type' => $value['type'],
                    'id' => $value['id']
                ];
                if (!$minimal) {
                    $this->with['included'][] = $value;
                }
            }
        } else {
            $relation['data'] = [
                'type' => $data['type'],
                'id' => $data['id']
            ];
            if (!$minimal) {
                $this->with['included'][] = $data;
            }
        }

        if (!$minimal) {
            $with = $resource->with($request);
            foreach ($with as $key => $value) {
                $this->with[$key] = array_merge($this->with[$key] ?? [], is_array($value) ? $value : [$value]);
            }
        }

        return $relation;
    }

    private function valueIsMissing($value): bool
    {
        return ($value instanceof PotentiallyMissing && $value->isMissing())
            || ($value instanceof JsonResource &&
                $value->resource instanceof PotentiallyMissing &&
                $value->isMissing());
    }
}
