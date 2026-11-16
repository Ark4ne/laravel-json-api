<?php

declare(strict_types=1);

namespace Test\Architecture;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use PHPat\Selector\Classname;
use PHPat\Selector\ClassNamespace;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule as ArchitectureRule;
use PHPat\Test\PHPat;

/**
 * Architecture rules of docs/architecture.md, run by PHPStan through phpat (`composer stan`).
 *
 * The 1.x code does not follow them yet: its violations sit in phpstan-baseline.neon, which may only shrink.
 */
final class ArchitectureTest
{
    private const ROOT = 'Ark4ne\JsonApi';

    /**
     * `ark4ne/laravel-api-revisions`: typed values and revisions (docs/rfc/api-revisions-integration.md).
     */
    private const BASE = 'Ark4ne\ApiRevisions';

    /**
     * Layers from left to right: each one depends only on those to its left (docs/architecture.md §2).
     */
    private const LAYERS = [
        'Support',
        'Configuration',
        'Definition',
        'Descriptors',
        'Compilation',
        'Context',
        'Loading',
        'Rendering',
        'Resources',
        'Http',
        'Laravel',
        'Testing',
    ];

    /**
     * Extensible base classes allowed by docs/architecture.md §14.
     */
    private const BASE_CLASSES = [
        JsonApiResource::class,
        JsonApiCollection::class,
        Describer::class,
        Relation::class,
        JsonApiFormRequest::class,
    ];

    /**
     * @return iterable<ArchitectureRule>
     */
    public function test_layers_depend_only_on_the_layers_to_their_left(): iterable
    {
        foreach (self::LAYERS as $position => $layer) {
            $right = array_slice(self::LAYERS, $position + 1);

            if ($right === []) {
                continue;
            }

            yield PHPat::rule()
                ->classes(self::layer($layer))
                ->shouldNotDependOn()
                ->classes(...self::layers(...$right))
                ->because("{$layer} depends only on the layers to its left.");
        }
    }

    public function test_exceptions_depend_only_on_support(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(self::layer('Exceptions'))
            ->shouldNotDependOn()
            ->classes(...self::layers(...array_slice(self::LAYERS, 1)))
            ->because('exceptions are usable by every layer.');
    }

    public function test_only_the_laravel_layer_uses_the_laravel_bridge_of_the_base(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(self::layer('Laravel'), self::layer('Testing'))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace(self::BASE . '\\Laravel'))
            ->because('the Laravel bridge of ark4ne/laravel-api-revisions is wired in src/Laravel/ only.');
    }

    public function test_the_layers_left_of_the_definition_do_not_know_the_base(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(...self::layers('Support', 'Configuration'))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace(self::BASE))
            ->because('the model of ark4ne/laravel-api-revisions is used from Definition rightwards.');
    }

    public function test_classes_are_final(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::isInterface(),
                Selector::isEnum(),
                Selector::isTrait(),
                ...self::baseClasses(),
            )
            ->shouldBeFinal()
            ->because('classes are final by default; the extensible base classes are listed in docs/architecture.md.');
    }

    public function test_only_the_base_classes_are_abstract(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::isInterface(),
                ...self::baseClasses(),
            )
            ->shouldNotBeAbstract()
            ->because('abstract classes are reserved for the base classes listed in docs/architecture.md.');
    }

    public function test_only_resources_use_traits(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(self::layer('Resources'))
            ->shouldNotInclude()
            ->classes(Selector::all())
            ->because('traits are only allowed in the user façade (src/Resources/): use composition elsewhere.');
    }

    public function test_interfaces_have_no_interface_suffix(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(Selector::inNamespace(self::ROOT), Selector::isInterface()))
            ->shouldBeNamed('/^(?!.*Interface$).*$/', true)
            ->because('interfaces are named after a role: Stage, ErrorRenderer, TypeNaming.');
    }

    private static function layer(string $layer): ClassNamespace
    {
        return Selector::inNamespace(self::ROOT . '\\' . $layer);
    }

    /**
     * @return list<ClassNamespace>
     */
    private static function layers(string ...$layers): array
    {
        return array_values(array_map(self::layer(...), $layers));
    }

    /**
     * @return list<Classname>
     */
    private static function baseClasses(): array
    {
        return array_map(static fn(string $class) => Selector::classname($class), self::BASE_CLASSES);
    }
}
