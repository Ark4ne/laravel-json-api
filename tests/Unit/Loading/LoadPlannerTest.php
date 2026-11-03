<?php

declare(strict_types=1);

namespace Test\Unit\Loading;

use Ark4ne\JsonApi\Loading\LoadPlan;
use Ark4ne\JsonApi\Loading\LoadPlanner;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\Diagnostic;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;
use Test\Unit\Loading\Fixtures\DeltaResource;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Resources\Fixtures\AlphaResource;

class LoadPlannerTest extends TestCase
{
    public function test_a_relationship_not_included_loads_the_roots_of_its_load_for_its_linkage(): void
    {
        $plan = (new LoadPlanner())->plan(AlphaResource::class, Contexts::of());

        self::assertEquals(new LoadPlan(['b' => null, 'g' => null]), $plan);
    }

    public function test_an_included_relationship_loads_every_path_and_plans_its_related_resources(): void
    {
        $plan = (new LoadPlanner())->plan(AlphaResource::class, Contexts::of('include=beta.gamma'));

        // beta.gamma is included: `g` and `g.a`; gamma renders the linkage of `alpha` (`a`), `lazy` loads nothing.
        $gamma = new LoadPlan(['a' => null]);
        $beta = new LoadPlan(['g' => null, 'g.a' => null], nested: ['g' => [$gamma]]);

        self::assertEquals(new LoadPlan(['b' => null, 'g' => null], nested: ['b' => [$beta]]), $plan);
    }

    public function test_an_included_relationship_is_planned_in_the_context_of_its_path(): void
    {
        $plan = (new LoadPlanner())->plan(AlphaResource::class, Contexts::of('include=gammas.alpha'));

        $alpha = new LoadPlan(['b' => null, 'g' => null]);
        $gamma = new LoadPlan(['a' => null], nested: ['a' => [$alpha]]);

        self::assertEquals(new LoadPlan(['b' => null, 'g' => null], nested: ['g' => [$gamma]]), $plan);
    }

    public function test_an_include_through_a_cycle_plans_each_level_in_the_context_of_its_path(): void
    {
        $plan = (new LoadPlanner())->plan(AlphaResource::class, Contexts::of('include=gammas.alpha.beta'));

        $beta = new LoadPlan(['g' => null]);
        $alpha = new LoadPlan(['b' => null, 'g' => null], nested: ['b' => [$beta]]);
        $gamma = new LoadPlan(['a' => null], nested: ['a' => [$alpha]]);

        self::assertEquals(new LoadPlan(['b' => null, 'g' => null], nested: ['g' => [$gamma]]), $plan);
    }

    public function test_two_relationships_loading_a_path_with_different_constraints_keep_the_first_with_a_diagnostic(): void
    {
        $resource = new class (null) extends JsonApiResource {
            /**
             * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
             */
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'comments' => $this->many(DeltaResource::class),
                    'recent' => $this->many(DeltaResource::class, 'comments')
                        ->withLoad(['comments' => static fn($query) => $query]),
                ];
            }
        };
        $context = Contexts::of();

        $plan = (new LoadPlanner())->plan($resource::class, $context);

        self::assertSame(['comments' => null], $plan->relations);
        self::assertSame([LoadPlanner::LOAD_CONFLICT], array_map(
            static fn(Diagnostic $diagnostic) => $diagnostic->code,
            $context->diagnostics->all(),
        ));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('withoutLinkage')]
    public function test_nothing_is_loaded_for_a_linkage_that_is_not_rendered(string $query, array $config): void
    {
        self::assertTrue((new LoadPlanner())->plan(AlphaResource::class, Contexts::of($query, $config))->isEmpty());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function withoutLinkage(): iterable
    {
        yield 'linkage only when loaded' => ['', ['relationship' => ['linkage' => 'loaded']]];
        yield 'linkage only when included' => ['', ['relationship' => ['linkage' => 'included']]];
        yield 'relationships left out by the fieldset' => ['fields[alpha]=title', []];
    }

    public function test_relationships_left_out_by_the_fieldset_are_loaded_when_the_fieldset_keeps_them(): void
    {
        $plan = (new LoadPlanner())->plan(
            AlphaResource::class,
            Contexts::of('fields[alpha]=title', ['fields' => ['relationships' => 'keep']]),
        );

        self::assertEquals(new LoadPlan(['b' => null, 'g' => null]), $plan);
    }

    public function test_the_linkage_of_a_relationship_wins_over_the_configured_one(): void
    {
        $always = (new LoadPlanner())->plan(DeltaResource::class, Contexts::of());
        $included = (new LoadPlanner())->plan(
            DeltaResource::class,
            Contexts::of('', ['relationship' => ['linkage' => 'included']]),
        );

        // `always` renders its linkage whatever the configuration, `loaded` and `never` load nothing.
        self::assertEquals(new LoadPlan(['always' => null], ['always']), $always);
        self::assertEquals(new LoadPlan(['always' => null], ['always']), $included);
    }

    public function test_an_included_relationship_is_loaded_whatever_its_linkage(): void
    {
        $plan = (new LoadPlanner())->plan(DeltaResource::class, Contexts::of('include=loaded,never'));

        // The related resources are deltas too: they render the linkage of `always`.
        $related = new LoadPlan(['always' => null], ['always']);

        self::assertEquals(
            new LoadPlan(
                ['always' => null, 'loaded' => null, 'never' => null],
                ['always', 'loaded', 'never'],
                ['loaded' => [$related], 'never' => [$related]],
            ),
            $plan,
        );
    }
}
