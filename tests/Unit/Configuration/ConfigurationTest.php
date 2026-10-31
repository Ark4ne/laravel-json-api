<?php

declare(strict_types=1);

namespace Test\Unit\Configuration;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Configuration\SparseRelationships;
use Ark4ne\JsonApi\Configuration\TypeConvention;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Values\MoneyValue;
use Test\Unit\Compilation\Fixtures\PrefixedTypeNaming;

final class ConfigurationTest extends TestCase
{
    public function test_defaults_match_the_published_configuration_file(): void
    {
        /** @var array<array-key, mixed> $config */
        $config = require __DIR__ . '/../../../config/jsonapi.php';

        self::assertEquals(Configuration::defaults(), Configuration::fromArray($config));
    }

    public function test_defaults(): void
    {
        $configuration = Configuration::defaults();

        self::assertTrue($configuration->nullable);
        self::assertSame(DateTimeInterface::ATOM, $configuration->datetimeFormat);
        self::assertNull($configuration->precision);
        self::assertFalse($configuration->strict);
        self::assertSame([], $configuration->whenHas);
        self::assertSame(Linkage::Always, $configuration->linkage);
        self::assertSame(SparseRelationships::Filter, $configuration->sparseRelationships);
        self::assertSame([], $configuration->resourcePaths);
        self::assertSame([], $configuration->renderingStages);
        self::assertEquals(new JsonApiObject('1.1'), $configuration->jsonapi);
        self::assertNull($configuration->maxIncludeDepth);
        self::assertSame(TypeConvention::KebabSingular, $configuration->typeNaming);
        self::assertSame([], $configuration->valueTypes);
    }

    public function test_an_empty_array_gives_the_defaults(): void
    {
        self::assertEquals(Configuration::defaults(), Configuration::fromArray([]));
    }

    public function test_every_key_is_read(): void
    {
        $configuration = Configuration::fromArray([
            'describer' => [
                'nullable' => false,
                'datetime' => 'Y-m-d',
                'precision' => 2,
                'strict' => true,
                'when-has' => ['attributes'],
                'types' => ['money' => MoneyValue::class],
            ],
            'relationship' => ['linkage' => 'loaded'],
            'fields' => ['relationships' => 'keep'],
            'definitions' => ['paths' => ['/app/Http/Resources']],
            'rendering' => ['stages' => [self::class]],
            'include' => ['max-depth' => 3],
            'type' => ['naming' => 'snake-plural'],
            'document' => ['jsonapi' => [
                'version' => '1.0',
                'ext' => ['https://jsonapi.org/ext/atomic'],
                'profile' => ['https://example.com/profile'],
                'meta' => ['copyright' => 'ACME'],
            ]],
        ]);

        self::assertFalse($configuration->nullable);
        self::assertSame('Y-m-d', $configuration->datetimeFormat);
        self::assertSame(2, $configuration->precision);
        self::assertTrue($configuration->strict);
        self::assertSame(['attributes'], $configuration->whenHas);
        self::assertSame(Linkage::Loaded, $configuration->linkage);
        self::assertSame(SparseRelationships::Keep, $configuration->sparseRelationships);
        self::assertSame(['/app/Http/Resources'], $configuration->resourcePaths);
        self::assertSame([self::class], $configuration->renderingStages);
        self::assertSame(3, $configuration->maxIncludeDepth);
        self::assertSame(TypeConvention::SnakePlural, $configuration->typeNaming);
        self::assertSame(['money' => MoneyValue::class], $configuration->valueTypes);
        self::assertEquals(
            new JsonApiObject('1.0', ['https://jsonapi.org/ext/atomic'], ['https://example.com/profile'], ['copyright' => 'ACME']),
            $configuration->jsonapi,
        );
    }

    public function test_the_type_naming_is_a_class_of_the_application(): void
    {
        $configuration = Configuration::fromArray(['type' => ['naming' => PrefixedTypeNaming::class]]);

        self::assertInstanceOf(PrefixedTypeNaming::class, $configuration->typeNaming);
    }

    public function test_a_value_type_is_added_to_a_copy(): void
    {
        $configuration = Configuration::defaults();

        $with = $configuration->withValueType('money', MoneyValue::class);

        self::assertSame([], $configuration->valueTypes);
        self::assertSame(['money' => MoneyValue::class], $with->valueTypes);
        self::assertSame($configuration->typeNaming, $with->typeNaming);
    }

    public function test_the_jsonapi_member_is_disabled_by_false(): void
    {
        self::assertNull(Configuration::fromArray(['document' => ['jsonapi' => false]])->jsonapi);
    }

    public function test_a_partial_jsonapi_member_takes_the_defaults_for_the_rest(): void
    {
        self::assertEquals(
            new JsonApiObject('1.1', meta: ['copyright' => 'ACME']),
            Configuration::fromArray(['document' => ['jsonapi' => ['meta' => ['copyright' => 'ACME']]]])->jsonapi,
        );
    }

    public function test_when_has_true_enables_every_scope(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['when-has' => true]]);

        self::assertSame(Configuration::WHEN_HAS_SCOPES, $configuration->whenHas);
    }

    public function test_when_has_false_disables_every_scope(): void
    {
        self::assertSame([], Configuration::fromArray(['describer' => ['when-has' => false]])->whenHas);
    }

    public function test_auto_when_has_tells_whether_a_scope_is_enabled(): void
    {
        $configuration = Configuration::fromArray(['describer' => ['when-has' => ['meta']]]);

        self::assertTrue($configuration->autoWhenHas('meta'));
        self::assertFalse($configuration->autoWhenHas('attributes'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'nullable' => [['describer' => ['nullable' => 'yes']], 'describer.nullable'];
        yield 'datetime' => [['describer' => ['datetime' => 1]], 'describer.datetime'];
        yield 'precision' => [['describer' => ['precision' => 'two']], 'describer.precision'];
        yield 'strict' => [['describer' => ['strict' => 1]], 'describer.strict'];
        yield 'when-has scope' => [['describer' => ['when-has' => ['unknown']]], 'describer.when-has'];
        yield 'linkage' => [['relationship' => ['linkage' => 'never']], 'relationship.linkage'];
        yield 'when-included, replaced by linkage' => [['relationship' => ['when-included' => false]], 'relationship.when-included'];
        yield 'fields.relationships' => [['fields' => ['relationships' => 'drop']], 'fields.relationships'];
        yield 'definitions.paths' => [['definitions' => ['paths' => 'app']], 'definitions.paths'];
        yield 'rendering.stages not a list' => [['rendering' => ['stages' => 'Stage']], 'rendering.stages'];
        yield 'include.max-depth zero' => [['include' => ['max-depth' => 0]], 'include.max-depth'];
        yield 'include.max-depth string' => [['include' => ['max-depth' => '3']], 'include.max-depth'];
        yield 'document.jsonapi' => [['document' => ['jsonapi' => true]], 'document.jsonapi'];
        yield 'document.jsonapi.version' => [['document' => ['jsonapi' => ['version' => 1.1]]], 'document.jsonapi.version'];
        yield 'document.jsonapi.ext' => [['document' => ['jsonapi' => ['ext' => 'atomic']]], 'document.jsonapi.ext'];
        yield 'document.jsonapi.profile' => [['document' => ['jsonapi' => ['profile' => [1]]]], 'document.jsonapi.profile'];
        yield 'document.jsonapi.meta' => [['document' => ['jsonapi' => ['meta' => ['a']]]], 'document.jsonapi.meta'];
        yield 'describer.types not an array' => [['describer' => ['types' => 'money']], 'describer.types'];
        yield 'describer.types name' => [['describer' => ['types' => ['Money' => MoneyValue::class]]], 'describer.types'];
        yield 'describer.types unknown class' => [['describer' => ['types' => ['money' => 'App\\Money']]], 'describer.types.money'];
        yield 'type.naming unknown convention' => [['type' => ['naming' => 'camel']], 'type.naming'];
        yield 'type.naming not a type naming' => [['type' => ['naming' => self::class]], 'type.naming'];
        yield 'rendering.stages unknown class' => [['rendering' => ['stages' => ['App\\Unknown']]], 'rendering.stages'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function test_a_value_of_the_wrong_type_throws_naming_the_key(array $config, string $key): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($key);

        Configuration::fromArray($config);
    }

    public function test_prepended_rendering_stages_run_before_the_configured_ones_and_are_not_repeated(): void
    {
        $configuration = Configuration::fromArray(['rendering' => ['stages' => [self::class]]])
            ->prependRenderingStages(TestCase::class, self::class);

        self::assertSame([TestCase::class, self::class], $configuration->renderingStages);
    }

    public function test_a_prepended_rendering_stage_that_does_not_exist_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('rendering.stages');

        Configuration::fromArray([])->prependRenderingStages('App\\Unknown');
    }
}
