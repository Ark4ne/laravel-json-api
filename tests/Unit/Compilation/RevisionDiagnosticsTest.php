<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;
use Test\app\Revisions\ProfileResource;

/**
 * The diagnostics of the revision declarations of a resource (docs/rfc/versioned-resources-epics.md US 3.4): reported
 * on the head definition, which `jsonapi:check` reads, whichever revision they are found in.
 */
final class RevisionDiagnosticsTest extends TestCase
{
    public function test_a_resource_declaring_revisions_rightly_has_no_diagnostic(): void
    {
        self::assertSame([], $this->compile(ProfileResource::class)->diagnostics);
    }

    public function test_two_was_with_the_same_revision_are_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()
                        ->was(ApiRevision::Labels, name: 'label')
                        ->was(ApiRevision::Labels, name: 'name'),
                ];
            }
        };

        $diagnostic = $this->only($resource::class, DefinitionCompiler::REVISION_DUPLICATE);

        self::assertSame(DiagnosticLevel::Error, $diagnostic->level);
        self::assertSame('title', $diagnostic->subject);
        self::assertStringContainsString('Labels', $diagnostic->message);
        // Read on the declarations, not on the shape of a revision.
        self::assertNull($diagnostic->revision);
    }

    public function test_a_bound_set_twice_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['title' => $this->string()->until(ApiRevision::Initial)->until(ApiRevision::Labels)];
            }
        };

        self::assertStringContainsString('until()', $this->only($resource::class, DefinitionCompiler::REVISION_DUPLICATE)->message);
    }

    public function test_a_was_without_name_nor_value_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['title' => $this->string()->was(ApiRevision::Labels)];
            }
        };

        self::assertSame('title', $this->only($resource::class, DefinitionCompiler::REVISION_EMPTY_WAS)->subject);
    }

    public function test_a_was_outside_the_life_of_its_field_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'old' => $this->string()->until(ApiRevision::Initial)->was(ApiRevision::Labels, name: 'older'),
                    'new' => $this->string()->since(ApiRevision::Labels)->was(ApiRevision::Initial, name: 'newer'),
                ];
            }
        };

        $diagnostics = $this->compile($resource::class)->diagnostics;

        self::assertSame(
            [DefinitionCompiler::REVISION_OUT_OF_LIFE, DefinitionCompiler::REVISION_OUT_OF_LIFE],
            array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->code, $diagnostics),
        );
        // Newer than until(): it applies up to it, a warning.
        self::assertSame(['old', DiagnosticLevel::Warning], [$diagnostics[0]->subject, $diagnostics[0]->level]);
        self::assertStringContainsString('Write was(Initial)', $diagnostics[0]->message);
        self::assertSame(['new', DiagnosticLevel::Error], [$diagnostics[1]->subject, $diagnostics[1]->level]);
    }

    public function test_two_fields_with_one_name_in_a_revision_are_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()->was(ApiRevision::Initial, name: 'name'),
                    'name' => $this->string(),
                ];
            }
        };

        $diagnostic = $this->only($resource::class, DefinitionCompiler::REVISION_NAME_COLLISION);

        self::assertSame('name', $diagnostic->subject);
        self::assertStringContainsString('Initial', $diagnostic->message);
        self::assertSame('Initial', $diagnostic->revision);
    }

    public function test_reusing_the_name_of_a_removed_relationship_needs_since(): void
    {
        $withoutSince = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['comments' => $this->integer(fn() => 0)];
            }

            protected function toRelationships(Request $request): iterable
            {
                return ['comments' => $this->many(CommentResource::class)->until(ApiRevision::Labels)];
            }
        };
        $withSince = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['comments' => $this->integer(fn() => 0)->since(ApiRevision::UsersReplaceTeachers)];
            }

            protected function toRelationships(Request $request): iterable
            {
                return ['comments' => $this->many(CommentResource::class)->until(ApiRevision::Labels)];
            }
        };

        self::assertSame('comments', $this->only($withoutSince::class, DefinitionCompiler::REVISION_NAME_COLLISION)->subject);
        self::assertSame([], $this->compile($withSince::class)->diagnostics);
    }

    public function test_a_field_named_id_in_a_revision_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['identifier' => $this->string()->was(ApiRevision::Initial, name: 'id')];
            }
        };

        self::assertSame('id', $this->only($resource::class, DefinitionCompiler::REVISION_RESERVED_NAME)->subject);
    }

    public function test_a_value_without_source_under_a_head_read_by_a_closure_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'full_name' => $this->string(fn() => 'computed')
                        ->was(ApiRevision::Initial, name: 'name', value: $this->string()),
                ];
            }
        };

        self::assertSame('full_name', $this->only($resource::class, DefinitionCompiler::REVISION_UNSOURCED_VALUE)->subject);
    }

    public function test_an_older_was_without_the_value_of_a_newer_one_is_a_warning(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'price' => $this->string()
                        ->was(ApiRevision::Labels, value: $this->integer('price_cents'))
                        ->was(ApiRevision::Initial, name: 'amount'),
                ];
            }
        };

        $diagnostic = $this->only($resource::class, DefinitionCompiler::REVISION_VALUE_GAP);

        self::assertSame(DiagnosticLevel::Warning, $diagnostic->level);
        self::assertStringContainsString('Initial', $diagnostic->message);
    }

    public function test_since_the_oldest_revision_is_a_warning(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['title' => $this->string()->since(ApiRevision::Initial)];
            }
        };

        $diagnostic = $this->only($resource::class, DefinitionCompiler::REVISION_NOOP);

        self::assertSame(DiagnosticLevel::Warning, $diagnostic->level);
        self::assertSame('title', $diagnostic->subject);
    }

    public function test_a_resource_since_the_oldest_revision_is_a_warning(): void
    {
        $resource = new #[JsonApiSince(ApiRevision::Initial)] class (null) extends JsonApiResource {};

        self::assertNull($this->only($resource::class, DefinitionCompiler::REVISION_NOOP)->subject);
    }

    public function test_declarations_of_two_enums_are_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return [
                    'title' => $this->string()->was(ApiRevision::Labels, name: 'label'),
                    'body' => $this->string()->until(OtherRevision::First),
                ];
            }

            protected function toRelationships(Request $request): iterable
            {
                return ['author' => $this->one(UserResource::class)];
            }
        };

        $definition = $this->compile($resource::class);

        self::assertSame([DefinitionCompiler::REVISION_MIXED_ENUMS], $this->codes($definition));
        self::assertSame([], $definition->boundaries);
        // Compiled without revision: `body` is still left out of the head, `until()` a revision.
        self::assertSame(['title'], array_keys($definition->attributes));
    }

    /**
     * @param class-string $class
     */
    private function compile(string $class): ResourceDefinition
    {
        /** @var class-string<DefinitionSource> $class */
        return (new DefinitionCompiler(Configuration::defaults()))->compile($class);
    }

    /**
     * @param class-string $class
     */
    private function only(string $class, string $code): Diagnostic
    {
        $definition = $this->compile($class);
        self::assertSame([$code], $this->codes($definition));

        return $definition->diagnostics[0];
    }

    /**
     * @return list<string>
     */
    private function codes(ResourceDefinition $definition): array
    {
        return array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->code, $definition->diagnostics);
    }

    public function test_a_field_since_a_revision_newer_than_its_until_is_reported(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['title' => $this->string()->since(ApiRevision::Labels)->until(ApiRevision::Initial)];
            }
        };

        self::assertSame('title', $this->only($resource::class, DefinitionCompiler::REVISION_OUT_OF_LIFE)->subject);
    }

    public function test_an_error_shown_only_by_a_revision_is_reported_on_the_head(): void
    {
        $resource = new class (null) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'author' => $this->one(UserResource::class)
                        ->was(ApiRevision::Initial, value: $this->one(stdClass::class)),
                ];
            }
        };

        $diagnostic = $this->only($resource::class, DefinitionCompiler::INVALID_MEMBER);

        self::assertSame(DiagnosticLevel::Error, $diagnostic->level);
        self::assertSame('Initial', $diagnostic->revision);
        self::assertStringNotContainsString('At the revision', $diagnostic->message);
    }

    public function test_the_head_compiled_for_a_revision_has_the_diagnostics_of_every_revision(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toAttributes(Request $request): iterable
            {
                return ['identifier' => $this->string()->was(ApiRevision::Initial, name: 'id')];
            }
        };
        /** @var class-string<DefinitionSource> $class */
        $class = $resource::class;
        $compiler = new DefinitionCompiler(Configuration::defaults());

        self::assertEquals($compiler->compile($class), $compiler->compile($class, ApiRevision::Labels));
    }
}
