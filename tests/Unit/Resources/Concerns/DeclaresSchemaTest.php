<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\ApiRevisions\Compilation\Probe;
use Ark4ne\ApiRevisions\Compilation\Shapes;
use Ark4ne\ApiRevisions\Projection\Projector;
use Ark4ne\ApiRevisions\Schema\ClassLifetime;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\TeacherResource;
use Test\app\Schemas\ArticleResource;
use Test\TestCase;

final class DeclaresSchemaTest extends TestCase
{
    public function test_a_resource_declares_one_response(): void
    {
        self::assertContains(Revisioned::class, class_implements(ArticleResource::class));
        self::assertSame([DeclarationKind::Response], ArticleResource::declarationKinds());
    }

    public function test_the_declaration_holds_one_object_per_hook_of_values(): void
    {
        $declaration = ArticleResource::describeRevisioned(DeclarationKind::Response, new Probe(self::class));

        self::assertSame(['attributes', 'meta', 'documentMeta'], array_keys($declaration));
        self::assertContainsOnlyInstancesOf(ObjectSchema::class, $declaration);
        self::assertSame(['title', 'views', 'summary'], self::children($declaration['attributes']));
        self::assertSame(['published'], self::children($declaration['meta']));
        self::assertSame(['legacy'], self::children($declaration['documentMeta']));
    }

    /**
     * @return iterable<string, array{ApiRevision, list<string>, list<string>}>
     */
    public static function shapes(): iterable
    {
        yield 'Initial' => [ApiRevision::Initial, ['name', 'views'], ['legacy']];
        yield 'Labels' => [ApiRevision::Labels, ['label', 'views', 'summary'], ['legacy']];
        yield 'head' => [ApiRevision::UsersReplaceTeachers, ['title', 'views', 'summary'], []];
    }

    /**
     * @param list<string> $attributes
     * @param list<string> $documentMeta
     */
    #[DataProvider('shapes')]
    public function test_the_base_compiles_the_shape_of_each_revision(ApiRevision $revision, array $attributes, array $documentMeta): void
    {
        $shape = (new Shapes())->for(ArticleResource::class, DeclarationKind::Response, $revision);

        self::assertSame($attributes, $shape->field('attributes')?->children?->names());
        self::assertSame(['published'], $shape->field('meta')?->children?->names());
        self::assertSame($documentMeta, $shape->field('documentMeta')?->children?->names());
    }

    public function test_the_base_projects_a_resource_built_on_its_subject(): void
    {
        $article = ['title' => 'Hello', 'views' => 12, 'body' => 'text', 'published' => true];
        $shapes = new Shapes();
        $project = static fn(ApiRevision $revision): array => (new Projector(shapes: $shapes))->project(
            $shapes->for(ArticleResource::class, DeclarationKind::Response, $revision),
            ArticleResource::describeRevisioned(DeclarationKind::Response, $article),
            $article,
        );

        self::assertSame(
            ['attributes' => ['name' => 'Hello', 'views' => '12'], 'meta' => ['published' => true], 'documentMeta' => ['legacy' => true]],
            $project(ApiRevision::Initial),
        );
        // An object without field at the revision is an empty JSON object.
        self::assertSame(
            '{"attributes":{"title":"Hello","views":12,"summary":"TEXT"},"meta":{"published":true},"documentMeta":{}}',
            json_encode($project(ApiRevision::UsersReplaceTeachers)),
        );
    }

    public function test_declaring_runs_no_closure(): void
    {
        $resource = new class ([]) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['computed' => Schema::string(static fn(): never => throw new LogicException('A source ran while declaring.'))];
            }
        };

        self::assertSame(['computed'], self::children($resource::describeRevisioned(DeclarationKind::Response, new Probe(self::class))['attributes']));
    }

    public function test_a_member_that_is_not_a_descriptor_of_the_base_is_refused(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[name] of toAttributes() in [' . UserResource::class . ']');

        UserResource::describeRevisioned(DeclarationKind::Response, new Probe(self::class));
    }

    public function test_a_plain_value_is_refused(): void
    {
        $resource = new class ([]) extends JsonApiResource {
            protected function toResourceMeta(Request $request): iterable
            {
                return ['version' => 3];
            }
        };

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[version] of toResourceMeta()');
        $this->expectExceptionMessage('int given');

        $resource::describeRevisioned(DeclarationKind::Response, new Probe(self::class));
    }

    public function test_a_member_without_name_is_refused(): void
    {
        $resource = new class ([]) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [Schema::string()];
            }
        };

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[0] of toAttributes()');

        $resource::describeRevisioned(DeclarationKind::Response, new Probe(self::class));
    }

    public function test_the_lifetime_of_the_class_is_the_one_of_its_attributes(): void
    {
        self::assertTrue(ClassLifetime::exists(ArticleResource::class, ApiRevision::Initial));

        self::assertFalse(ClassLifetime::exists(MemberResource::class, ApiRevision::Labels));
        self::assertTrue(ClassLifetime::exists(MemberResource::class, ApiRevision::UsersReplaceTeachers));

        self::assertTrue(ClassLifetime::exists(TeacherResource::class, ApiRevision::Labels));
        self::assertFalse(ClassLifetime::exists(TeacherResource::class, ApiRevision::UsersReplaceTeachers));
    }

    /**
     * @param \Ark4ne\ApiRevisions\Schema\Descriptor<mixed> $object
     *
     * @return list<string>
     */
    private static function children(mixed $object): array
    {
        self::assertInstanceOf(ObjectSchema::class, $object);

        return array_keys($object->children());
    }
}
