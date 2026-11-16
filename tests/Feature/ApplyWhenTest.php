<?php

declare(strict_types=1);

namespace Test\Feature;

use Ark4ne\ApiRevisions\Projection\StrictValues;
use Ark4ne\ApiRevisions\Projection\ValueErrorHandler;
use Ark4ne\ApiRevisions\Schema\Schema;
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

    #[DataProvider('conditions')]
    public function test_a_relationship_with_a_condition_follows_it(bool $condition): void
    {
        $resource = new /** @extends JsonApiResource<object{id: int, name: string, author: object}> */ class ((object) ['id' => 1, 'name' => 'Ada', 'author' => (object) ['id' => 2, 'name' => 'Alan']], $condition) extends JsonApiResource {
            public function __construct(mixed $resource, private readonly bool $condition = true)
            {
                parent::__construct($resource);
            }

            protected function toType(Request $request): string
            {
                return 'conditional-post';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'name' => Schema::string(),
                    $this->applyWhen(fn(): bool => $this->condition, ['shout' => Schema::string(fn(): string => strtoupper($this->resource->name))]),
                ];
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'author' => $this->one(UserResource::class)->when(fn(mixed $value, mixed $model): bool => $this->condition && $model === $this->resource),
                ];
            }
        };

        [$response] = $this->render($resource);

        if ($condition) {
            $response->assertJsonPath('data.attributes', ['name' => 'Ada', 'shout' => 'ADA']);
            $response->assertJsonPath('data.relationships.author.data', ['type' => 'user', 'id' => '2']);
        } else {
            $response->assertJsonPath('data.attributes', ['name' => 'Ada']);
            $response->assertJsonMissingPath('data.relationships');
        }
    }

    #[DataProvider('conditions')]
    public function test_a_relationship_of_a_resource_follows_the_conditions_set_on_its_schema(bool $condition): void
    {
        // On a model that is not an Eloquent one, "loaded" is "has the attribute".
        $model = ['id' => 1, 'name' => 'Ada', 'author' => (object) ['id' => 2, 'name' => 'Alan']] + ($condition ? ['reviewed' => true] : []);
        $resource = new /** @extends JsonApiResource<object{id: int, name: string, author: object}> */ class ((object) $model) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'guarded-post';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['name' => Schema::string()];
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'author' => Schema::relationship(UserResource::relationship(fn() => new UserResource($this->resource->author)))
                        ->whenLoaded('reviewed'),
                ];
            }
        };

        [$response] = $this->render($resource);

        if ($condition) {
            $response->assertJsonPath('data.relationships.author.data', ['type' => 'user', 'id' => '2']);
        } else {
            $response->assertJsonMissingPath('data.relationships');
        }
    }

    public function test_a_closure_under_a_condition_renders_a_null_as_a_closure_of_the_hook_does(): void
    {
        // `describer.nullable` is on: a `null` is a value of every helper and closure, refused by none.
        app()->instance(ValueErrorHandler::class, new StrictValues());

        $resource = new /** @extends JsonApiResource<object{id: int, name: string}> */ class ((object) ['id' => 1, 'name' => 'Ada']) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'nullable-post';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'direct' => fn(): ?string => null,
                    $this->applyWhen(fn(): bool => true, ['grouped' => fn(): ?string => null]),
                    'requested' => $this->whenInFields($request, 'requested', fn(): ?string => null),
                    'tags' => $this->array(fn(): array => ['a', null]),
                ];
            }
        };

        [$response] = $this->render($resource, 'fields[nullable-post]=direct,grouped,requested,tags');

        $response->assertJsonPath('data.attributes', ['direct' => null, 'grouped' => null, 'requested' => null, 'tags' => ['a', null]]);
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
                return ['name' => Schema::string()];
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    $this->applyWhen(fn(): bool => $this->condition, [
                        'author' => Schema::relationship(UserResource::relationship(fn() => new UserResource($this->resource->author))),
                    ]),
                ];
            }
        };
    }
}
