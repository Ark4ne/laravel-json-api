<?php

declare(strict_types=1);

namespace Test\Feature\Schemas;

use Ark4ne\ApiRevisions\Laravel\RevisionsConfig;
use Illuminate\Contracts\Console\Kernel;
use Test\app\Schemas\Checks\AbsentRelatedResource;
use Test\app\Schemas\Checks\CollidingResource;
use Test\app\Schemas\Checks\ReservedNameResource;
use Test\app\Schemas\Checks\ValidResource;
use Test\TestCase;

/**
 * The JSON:API rules this package adds to the check of `ark4ne/laravel-api-revisions`
 * (docs/rfc/api-revisions-integration.md D13), run by `revision:check`.
 */
final class ChecksTest extends TestCase
{
    public function test_a_valid_resource_passes_the_check_of_the_base(): void
    {
        [$status, $output] = $this->check(ValidResource::class);

        self::assertSame(0, $status, $output);
    }

    public function test_a_field_named_id_or_type_is_reported_at_the_revisions_it_has_that_name(): void
    {
        [$status, $output] = $this->check(ReservedNameResource::class);

        self::assertSame(1, $status);
        self::assertStringContainsString('jsonapi-reserved-name', $output);
        self::assertStringContainsString('[type] is reserved by JSON:API', $output);
        self::assertStringContainsString('[id] is reserved by JSON:API', $output);
        self::assertSame(2 + 3, substr_count($output, 'jsonapi-reserved-name'), $output);
    }

    public function test_an_attribute_and_a_relationship_sharing_a_name_are_reported(): void
    {
        [$status, $output] = $this->check(CollidingResource::class);

        self::assertSame(1, $status);
        self::assertSame(2, substr_count($output, 'jsonapi-name-collision'), $output);
        self::assertStringContainsString('[owner] names an attribute and a relationship', $output);
    }

    public function test_a_relationship_to_a_resource_absent_from_a_revision_is_reported(): void
    {
        [$status, $output] = $this->check(AbsentRelatedResource::class);

        self::assertSame(1, $status);
        self::assertSame(2, substr_count($output, 'jsonapi-absent-resource'), $output);
        self::assertStringContainsString('MemberResource', $output);
    }

    public function test_every_resource_given_is_checked(): void
    {
        [$status] = $this->check(ValidResource::class, CollidingResource::class);

        self::assertSame(1, $status);
    }

    /**
     * @param class-string ...$classes
     *
     * @return array{int, string}
     */
    private function check(string ...$classes): array
    {
        config(['revisions.discovery' => ['paths' => [], 'classes' => $classes]]);
        // Read at boot by the service provider of this package: read again with the classes of the test.
        app()->forgetInstance(RevisionsConfig::class);
        $kernel = app(Kernel::class);

        return [$kernel->call('revision:check'), $kernel->output()];
    }
}
