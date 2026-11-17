<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Compilation\ShapeSelector;
use Ark4ne\ApiRevisions\Projection\Projector;
use Ark4ne\ApiRevisions\Revisions\Unrevisioned;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\DescriptorMacros;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Descriptors\Schemas\Conditions;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConditionsTest extends TestCase
{
    protected function setUp(): void
    {
        DescriptorMacros::flush();
        Conditions::register();
    }

    public function test_every_macro_is_registered_under_its_name(): void
    {
        foreach (Conditions::MACROS as $name) {
            self::assertTrue(DescriptorMacros::has($name), $name);
        }
    }

    public function test_a_macro_the_application_registered_first_keeps_its_name(): void
    {
        DescriptorMacros::flush();
        DescriptorMacros::register('whenNotNull', static fn(Descriptor $descriptor): Descriptor => $descriptor->describe('mine'));

        Conditions::register();

        self::assertSame('mine', Schema::string()->whenNotNull()->toType()->description);
    }

    public function test_unless_is_the_one_of_the_base(): void
    {
        self::assertNotContains('unless', Conditions::MACROS);

        $visible = Schema::string('name')->unless(static fn(array $subject): bool => $subject['hidden'] === true);

        self::assertSame(['name' => 'Ada'], self::project(['name' => $visible], ['name' => 'Ada', 'hidden' => false]));
        self::assertSame([], self::project(['name' => $visible], ['name' => 'Ada', 'hidden' => true]));
    }

    public function test_when_not_null_leaves_out_a_null_value(): void
    {
        $descriptors = ['nickname' => Schema::string()->nullable()->whenNotNull()];

        self::assertSame([], self::project($descriptors, ['nickname' => null]));
        self::assertSame(['nickname' => ''], self::project($descriptors, ['nickname' => '']));
    }

    public function test_when_not_null_reads_the_source_before_the_value_as_in_2_0(): void
    {
        $runs = 0;
        $descriptors = ['nickname' => Schema::string(static function () use (&$runs): ?string {
            $runs++;

            return null;
        })->nullable()->whenNotNull()];

        self::assertSame([], self::project($descriptors, ['id' => 1]));
        // Read once by the condition; the value is never read when it fails.
        self::assertSame(1, $runs);
        self::assertSame(['label' => 'a'], self::project(['label' => Schema::string('name.first')->whenNotNull()], ['name' => ['first' => 'a']]));
        self::assertSame([], self::project(['label' => Schema::string('name.first')->nullable()->whenNotNull()], ['name' => ['first' => null]]));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function filled(): iterable
    {
        yield 'null' => [null, false];
        yield 'an empty string' => ['', false];
        yield 'blanks' => ['   ', false];
        yield 'an empty list' => [[], false];
        yield 'zero' => [0, true];
        yield 'false' => [false, true];
        yield 'a string' => ['a', true];
        yield 'a list' => [[1], true];
    }

    #[DataProvider('filled')]
    public function test_when_filled_leaves_out_a_blank_value(mixed $value, bool $rendered): void
    {
        $descriptors = ['field' => (new MixedSchema())->nullable()->whenFilled()];

        self::assertSame($rendered ? ['field' => $value] : [], self::project($descriptors, ['field' => $value]));
    }

    public function test_when_in_fields_marks_the_field_without_hiding_its_value(): void
    {
        $sparse = Schema::string()->whenInFields();

        self::assertTrue(Conditions::isOnlyInFields($sparse));
        self::assertFalse(Conditions::isOnlyInFields(Schema::string()->whenNotNull()));
        self::assertTrue($sparse->isConditional());
        // Whether it is requested is decided when the fields are planned: the condition itself passes.
        self::assertSame(['name' => 'Ada'], self::project(['name' => $sparse], ['name' => 'Ada']));
    }

    public function test_when_appended_renders_an_accessor_the_model_appends(): void
    {
        $descriptors = ['initials' => Schema::string('initials')->whenAppended()];
        $model = new class extends Model {
            public function getInitialsAttribute(): string
            {
                return 'AL';
            }
        };

        self::assertSame([], self::project($descriptors, $model));
        self::assertSame(['initials' => 'AL'], self::project($descriptors, (clone $model)->append('initials')));
        self::assertSame([], self::project($descriptors, ['initials' => 'AL']));
    }

    public function test_when_appended_needs_the_name_of_the_accessor(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('whenAppended()');

        Schema::string()->whenAppended();
    }

    public function test_when_appended_names_the_accessor_of_another_key(): void
    {
        $descriptors = ['short' => Schema::string(static fn(mixed $value, Model $model): mixed => $model->getAttribute('initials'))->whenAppended('initials')];
        $model = new class extends Model {
            public function getInitialsAttribute(): string
            {
                return 'AL';
            }
        };

        self::assertSame(['short' => 'AL'], self::project($descriptors, $model->append('initials')));
    }

    public function test_a_macro_returns_a_copy_of_the_same_type(): void
    {
        $string = Schema::string()->max(3);
        $conditional = $string->whenNotNull();

        self::assertNotSame($string, $conditional);
        self::assertFalse($string->isConditional());
        self::assertSame(3, $conditional->toType()->max);
    }

    /**
     * @param array<string, Descriptor<mixed>> $descriptors
     * @param array<string, mixed>|Model $subject
     *
     * @return array<string, mixed>
     */
    private static function project(array $descriptors, array|Model $subject): array
    {
        $shape = ShapeSelector::at($descriptors, Unrevisioned::Head, DeclarationKind::Response->direction());

        return (new Projector())->project($shape, $descriptors, $subject);
    }
}
