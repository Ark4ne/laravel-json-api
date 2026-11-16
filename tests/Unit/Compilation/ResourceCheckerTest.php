<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\ApiRevisions\Checking\Checker;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Compilation\ResourceCheck;
use Ark4ne\JsonApi\Compilation\ResourceChecker;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Diagnostic;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Revisions\ApiRevision;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\InconsistentHistoryResource;
use Test\Unit\Compilation\Fixtures\ReservedNameResource;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Compilation\References\BoundedReferencesResource;
use Test\Unit\Compilation\References\ForgottenWasResource;

final class ResourceCheckerTest extends TestCase
{
    public function test_a_well_written_resource_has_no_finding(): void
    {
        self::assertSame(
            [TypedResource::class => [], BoundedReferencesResource::class => []],
            self::describe(self::checker()->check([TypedResource::class, BoundedReferencesResource::class])),
        );
    }

    public function test_a_class_that_is_not_a_resource_is_an_error(): void
    {
        $found = self::describe(self::checker()->check([stdClass::class, TypedResource::class]));

        self::assertSame([], $found[TypedResource::class]);
        self::assertCount(1, $found[stdClass::class]);
        self::assertStringStartsWith('[error] invalid-resource: ', $found[stdClass::class][0]);
    }

    public function test_the_rules_of_json_api_are_checked_at_every_revision(): void
    {
        $found = self::describe(self::checker()->check([ReservedNameResource::class, ForgottenWasResource::class]));

        self::assertCount(6, $found[ReservedNameResource::class]);
        self::assertStringStartsWith('[error] [Initial] jsonapi-reserved-name (attributes.id): ', $found[ReservedNameResource::class][0]);
        self::assertStringStartsWith('[error] [Initial] jsonapi-reserved-name (relationships.type): ', $found[ReservedNameResource::class][1]);
        self::assertCount(2, $found[ForgottenWasResource::class]);
        self::assertStringStartsWith('[error] [Initial] jsonapi-absent-resource (relationships.members): ', $found[ForgottenWasResource::class][0]);
        self::assertStringStartsWith('[error] [Labels] jsonapi-absent-resource (relationships.members): ', $found[ForgottenWasResource::class][1]);
    }

    public function test_the_findings_may_be_those_of_one_revision(): void
    {
        $checker = self::checker();

        self::assertSame([], $checker->check([ForgottenWasResource::class], ApiRevision::UsersReplaceTeachers)[ForgottenWasResource::class]);

        $found = self::describe($checker->check([ForgottenWasResource::class], ApiRevision::Labels));

        self::assertCount(1, $found[ForgottenWasResource::class]);
        self::assertStringStartsWith('[error] [Labels] jsonapi-absent-resource', $found[ForgottenWasResource::class][0]);
    }

    public function test_the_history_of_the_fields_is_checked_by_the_base(): void
    {
        $found = self::describe(self::checker()->check([InconsistentHistoryResource::class]));

        self::assertCount(1, $found[InconsistentHistoryResource::class]);
        self::assertStringStartsWith('[error] [Initial] conversion-missing (attributes.code): ', $found[InconsistentHistoryResource::class][0]);
    }

    public function test_values_that_cannot_be_compiled_are_reported_once_by_the_base(): void
    {
        self::assertSame(
            [FailingHookResource::class => ['[error] definition-error: Needs a real model.']],
            self::describe(self::checker()->check([FailingHookResource::class])),
        );
    }

    public function test_values_this_package_cannot_compile_are_reported_with_the_other_findings_of_the_base(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['id' => $this->string()];
            }

            protected function toRelationships(Request $request): iterable
            {
                // No related resource to guess: the base compiles the field, this package cannot describe it.
                return ['owner' => $this->one()];
            }
        };

        $found = self::describe(self::checker(null)->check([$resource::class]))[$resource::class];

        self::assertCount(2, $found);
        self::assertStringStartsWith('[error] invalid-declaration: ', $found[0]);
        self::assertStringStartsWith('[error] jsonapi-reserved-name (attributes.id): ', $found[1]);
    }

    public function test_values_that_cannot_be_compiled_at_an_older_revision_are_reported_at_it(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'owner' => $this->one(TypedResource::class)->was(ApiRevision::Initial, schema: $this->one()),
                ];
            }
        };

        $found = self::describe(self::checker()->check([$resource::class]))[$resource::class];

        self::assertCount(1, $found);
        self::assertStringStartsWith('[error] [Initial] invalid-declaration: ', $found[0]);
        self::assertSame([], self::checker()->check([$resource::class], ApiRevision::UsersReplaceTeachers)[$resource::class]);
    }

    public function test_a_failing_links_hook_is_reported_with_the_findings_of_the_base(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['id' => $this->string()];
            }

            protected function toLinks(Request $request): iterable
            {
                return ['self' => $this->string()];
            }
        };

        $found = self::describe(self::checker(null)->check([$resource::class]))[$resource::class];

        self::assertCount(2, $found);
        self::assertStringStartsWith('[error] hook-failure', $found[0]);
        // An application without revision enum has no revision to name.
        self::assertStringStartsWith('[error] jsonapi-reserved-name (attributes.id): ', $found[1]);
    }

    public function test_the_checks_may_be_given(): void
    {
        $checker = new ResourceChecker(
            new DefinitionCompiler(Configuration::defaults()->withRevisions(ApiRevision::class)),
            Checker::builtIn(),
        );

        self::assertSame([], $checker->check([ReservedNameResource::class])[ReservedNameResource::class]);
        self::assertNotSame([], (new ResourceChecker(
            new DefinitionCompiler(Configuration::defaults()->withRevisions(ApiRevision::class)),
            [new ResourceCheck()],
        ))->check([ReservedNameResource::class])[ReservedNameResource::class]);
    }

    /**
     * @param class-string<ApiRevision>|null $revisions
     */
    private static function checker(?string $revisions = ApiRevision::class): ResourceChecker
    {
        $configuration = Configuration::defaults();

        return new ResourceChecker(new DefinitionCompiler($revisions === null ? $configuration : $configuration->withRevisions($revisions)));
    }

    /**
     * @param array<string, list<Diagnostic>> $found
     *
     * @return array<string, list<string>>
     */
    private static function describe(array $found): array
    {
        return array_map(
            static fn(array $diagnostics): array => array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->describe(), $diagnostics),
            $found,
        );
    }
}
