<?php

declare(strict_types=1);

namespace Test\Unit\Configuration;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\SparseRelationships;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        self::assertSame(DateTimeInterface::ATOM, $configuration->dateFormat);
        self::assertNull($configuration->precision);
        self::assertSame([], $configuration->whenHas);
        self::assertFalse($configuration->whenIncluded);
        self::assertSame(SparseRelationships::Filter, $configuration->sparseRelationships);
        self::assertSame([], $configuration->resourcePaths);
        self::assertSame([], $configuration->renderingStages);
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
                'date' => 'Y-m-d',
                'precision' => 2,
                'when-has' => ['attributes'],
            ],
            'relationship' => ['when-included' => true],
            'fields' => ['relationships' => 'keep'],
            'definitions' => ['paths' => ['/app/Http/Resources']],
            'rendering' => ['stages' => [self::class]],
        ]);

        self::assertFalse($configuration->nullable);
        self::assertSame('Y-m-d', $configuration->dateFormat);
        self::assertSame(2, $configuration->precision);
        self::assertSame(['attributes'], $configuration->whenHas);
        self::assertTrue($configuration->whenIncluded);
        self::assertSame(SparseRelationships::Keep, $configuration->sparseRelationships);
        self::assertSame(['/app/Http/Resources'], $configuration->resourcePaths);
        self::assertSame([self::class], $configuration->renderingStages);
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
        yield 'date' => [['describer' => ['date' => 1]], 'describer.date'];
        yield 'precision' => [['describer' => ['precision' => 'two']], 'describer.precision'];
        yield 'when-has scope' => [['describer' => ['when-has' => ['unknown']]], 'describer.when-has'];
        yield 'when-included' => [['relationship' => ['when-included' => 1]], 'relationship.when-included'];
        yield 'fields.relationships' => [['fields' => ['relationships' => 'drop']], 'fields.relationships'];
        yield 'definitions.paths' => [['definitions' => ['paths' => 'app']], 'definitions.paths'];
        yield 'rendering.stages not a list' => [['rendering' => ['stages' => 'Stage']], 'rendering.stages'];
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
}
