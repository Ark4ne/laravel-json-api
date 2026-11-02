<?php

declare(strict_types=1);

namespace Test\Feature;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;

/**
 * The relationships grouped by `applyWhen()` follow its condition, whether they are descriptors or relationships of a
 * resource (`UserResource::relationship()`).
 */
final class ApplyWhenTest extends FeatureTestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function conditions(): iterable
    {
        yield 'holds' => [true];
        yield 'fails' => [false];
    }

    #[DataProvider('conditions')]
    public function test_a_relationship_of_a_resource_under_apply_when_follows_the_condition(bool $condition): void
    {
        [$response] = $this->render(self::resource($condition));

        if ($condition) {
            $response->assertJsonPath('data.relationships.author.data', ['type' => 'user', 'id' => '2']);
        } else {
            $response->assertJsonMissingPath('data.relationships');
        }
    }

    /**
     * @return JsonApiResource<object{id: int, name: string, author: object}>
     */
    private static function resource(bool $condition): JsonApiResource
    {
        return new /** @extends JsonApiResource<object{id: int, name: string, author: object}> */ class ((object) ['id' => 1, 'name' => 'Ada', 'author' => (object) ['id' => 2, 'name' => 'Alan']], $condition) extends JsonApiResource {
            public function __construct(mixed $resource, private readonly bool $condition = true)
            {
                parent::__construct($resource);
            }

            protected function toType(Request $request): string
            {
                return 'post';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['name' => $this->string()];
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    $this->applyWhen($this->condition, [
                        'author' => UserResource::relationship(fn() => new UserResource($this->resource->author)),
                    ]),
                ];
            }
        };
    }
}
