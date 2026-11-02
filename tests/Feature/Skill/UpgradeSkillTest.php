<?php

declare(strict_types=1);

namespace Test\Feature\Skill;

use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

/**
 * The upgrade skill shipped with the package (`skills/laravel-json-api-upgrade/`): every 2.0 port of its examples
 * runs and renders a valid document, the files link to each other, and the mapping names everything 1.x exposed to an
 * application.
 */
final class UpgradeSkillTest extends SkillTestCase
{
    /**
     * The configuration keys, classes, methods and translation keys of 1.x an application could use.
     */
    private const LEGACY = [
        'describer.date', 'describer.precision', 'describer.nullable', 'describer.when-has', 'relationship.when-included',
        'LaravelJsonApiProvider', 'Requests\Rules\Includes', 'Requests\Rules\Fields', 'Requests\Rules\Traits\UseTrans',
        'Support\Includes', 'Support\Fields', 'Support\With', 'Support\Supported', 'Support\Traits\HasLocalCache',
        'Support\Config', 'Support\FakeModel', 'Resources\Skeleton', 'Resources\Resourceable', 'PrepareData',
        'Asserts\AssertJsonApiResource', 'Asserts\EagerSetAttribute', 'Asserts\FailGenerateSchema', 'RequestReceived',
        'schema()', 'toMeta()', 'toArray($request, true)', 'with()', 'whenIncluded($request, $type, $value)',
        'mergeWhen()', 'mergeUnless()', 'resolveResourceData()', '$preserveKeys', '->date(', '->precision(',
        '->nullable(false)', 'enum()->of(', 'value(mixed $of, Request $request)', '$this->uuid()', '->withLinks(',
        '->withMeta(', '->asCollection()', '->whenLoaded()', 'withLoad(true)', 'Filters', 'Relationship::links(',
        'RelationshipDefinition::$whenIncluded', 'RelationshipDefinition::$related', 'toId()',
        'validation.custom.jsonapi.fields.invalid', 'validation.custom.jsonapi.fields.invalid_fields',
        'validation.custom.jsonapi.fields.invalid_resource', 'validation.custom.jsonapi.includes.invalid',
        'validation.custom.jsonapi.includes.invalid_includes',
    ];

    protected static function skill(): string
    {
        return 'laravel-json-api-upgrade';
    }

    protected static function files(): array
    {
        return ['SKILL.md', 'mapping.md', 'examples.md'];
    }

    public function test_the_mapping_names_everything_an_application_used_from_1_x(): void
    {
        $mapping = self::read('mapping.md');

        foreach (self::LEGACY as $legacy) {
            self::assertStringContainsString($legacy, $mapping, $legacy);
        }
    }

    public function test_the_attributes_and_document_meta_example(): void
    {
        self::runRecipe('examples.md', 'Attributes and document meta');

        $attributes = ['id' => 1, 'name' => 'Ada', 'created_at' => '2026-01-02 03:04:05', 'score' => 4.29, 'email' => 'ada@example.com'];

        [$response] = $this->render(self::resource('App\Upgrade\Users\UserResource', self::model('App\Upgrade\Users\User', [...$attributes, 'verified' => true])));

        $response->assertJsonPath('data.attributes', [
            'name' => 'Ada',
            'registered-at' => '2026-01-02T03:04:05+00:00',
            'score' => 4.2,
            'uuid' => 'user-1',
            'email' => 'ada@example.com',
        ]);
        $response->assertJsonPath('meta', ['copyright' => 'Copyright 2026 Example']);

        [$response] = $this->render(self::resource('App\Upgrade\Users\UserResource', self::model('App\Upgrade\Users\User', [...$attributes, 'verified' => false])));

        $response->assertJsonMissingPath('data.attributes.email');
        $email = self::resource('App\Upgrade\Users\UserResource', self::model('App\Upgrade\Users\User', $attributes))::definition()->attribute('email');
        self::assertNotNull($email);
        self::assertTrue($email->conditional);
    }

    public function test_the_relationships_example(): void
    {
        self::runRecipe('examples.md', 'Relationships');

        $author = self::model('App\Upgrade\Library\Author', ['id' => 1, 'name' => 'Ann', 'publisher_id' => 3])
            ->setRelation('books', collect([self::model('App\Upgrade\Library\Book', ['id' => 5, 'title' => 'Dune'])]))
            ->setRelation('publisher', self::model('App\Upgrade\Library\Publisher', ['id' => 3, 'name' => 'Ace']));
        $resource = self::resource('App\Upgrade\Library\AuthorResource', $author);

        [$response, $queries] = $this->render($resource);

        self::assertSame(0, $queries);
        $response->assertJsonPath('data.attributes', ['name' => 'Ann']);
        $response->assertJsonPath('data.relationships', [
            'books' => [
                'data' => [['type' => 'books', 'id' => '5']],
                'links' => ['related' => 'https://api.example.com/authors/1/books'],
                'meta' => ['total' => 1],
            ],
        ]);

        [$response] = $this->render($resource, 'include=books,publisher');

        $response->assertJsonPath('data.attributes', ['name' => 'Ann', 'books-count' => 1]);
        $response->assertJsonPath('data.relationships.publisher', ['data' => ['type' => 'publishers', 'id' => '3']]);
        self::assertSame(['books', 'publishers'], $this->includedTypes($response));
    }

    public function test_the_custom_value_example(): void
    {
        self::runRecipe('examples.md', 'Custom value');

        $resource = self::resource('App\Upgrade\Stats\CampaignResource', self::model('App\Upgrade\Stats\Campaign', ['id' => 1, 'ratio' => 0.25]));

        [$response] = $this->render($resource);

        $response->assertJsonPath('data.attributes', ['ratio' => '25%']);
        self::assertSame(ValueType::String, $resource::definition()->attribute('ratio')?->type);
        self::assertSame('percent', $resource::definition()->attribute('ratio')->format);
    }

    public function test_the_validation_of_the_query_example(): void
    {
        self::runRecipe('examples.md', 'Relationships');
        self::runRecipe('examples.md', 'Validation of the query');

        Route::get('upgrade/authors/1', static function (): string {
            self::formRequest('App\Upgrade\Library\AuthorShowRequest');

            return 'ok';
        });
        Route::get('upgrade/authors', static function (): string {
            self::assertInstanceOf(FormRequest::class, app('App\Upgrade\Library\AuthorIndexRequest'));

            return 'ok';
        });

        $this->getJson('upgrade/authors/1?include=books,publisher&fields[authors]=name')->assertOk();
        $this->getJson('upgrade/authors/1?include=awards')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include' => '"authors" doesn\'t have relationship "awards".']);
        $this->getJson('upgrade/authors?include=books')->assertOk();
        $this->getJson('upgrade/authors?include=books.author')->assertUnprocessable()->assertJsonValidationErrors('include');
    }

    public function test_the_collections_and_documents_example(): void
    {
        self::runRecipe('examples.md', 'Collections and documents');

        $class = 'App\Upgrade\Tags\TagResource';
        self::assertTrue(is_subclass_of($class, JsonApiResource::class));
        $collection = $class::collection(collect([
            'php' => self::model('App\Upgrade\Tags\Tag', ['id' => 1, 'label' => 'PHP']),
            'api' => self::model('App\Upgrade\Tags\Tag', ['id' => 2, 'label' => 'API']),
        ]));
        self::assertSame('App\Upgrade\Tags\TagCollection', $collection::class);

        [$response] = $this->render($collection);

        $response->assertJsonPath('data', [
            ['type' => 'tags', 'id' => '1', 'attributes' => ['label' => 'PHP']],
            ['type' => 'tags', 'id' => '2', 'attributes' => ['label' => 'API']],
        ]);
        $response->assertJsonPath('meta', ['total' => 2]);
    }
}
