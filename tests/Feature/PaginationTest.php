<?php

declare(strict_types=1);

namespace Test\Feature;

use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\User;

/**
 * The paginated documents keep the `links` and `meta` of Laravel's paginators (decision Q6): they comply with the
 * specification, every document being validated against the JSON:API schema by `FeatureTestCase`.
 */
final class PaginationTest extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        /** @var Router $router */
        $router->get('paginated/length-aware', static fn() => UserResource::collection(User::query()->oldest('id')->paginate(4)));
        $router->get('paginated/simple', static fn() => UserResource::collection(User::query()->oldest('id')->simplePaginate(4)));
        $router->get('paginated/cursor', static fn() => UserResource::collection(User::query()->cursorPaginate(4)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function paginators(): iterable
    {
        yield 'offset, length aware' => ['length-aware', ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']];
        yield 'offset, simple' => ['simple', ['current_page', 'current_page_url', 'from', 'path', 'per_page', 'to']];
        yield 'cursor' => ['cursor', ['path', 'per_page', 'next_cursor', 'prev_cursor']];
    }

    /**
     * @param list<string> $meta
     */
    #[DataProvider('paginators')]
    public function test_every_page_has_the_pagination_links_of_the_specification_and_the_meta_of_laravel(string $paginator, array $meta): void
    {
        // The users seeded by `FeatureTestCase`, in the order of their key: that of the three paginators.
        $ids = array_map(static function (mixed $id): string {
            self::assertIsInt($id);

            return (string) $id;
        }, User::query()->oldest('id')->pluck('id')->all());
        self::assertGreaterThan(4, count($ids));
        $pages = [];
        $url = "paginated/{$paginator}";

        while ($url !== null) {
            $response = $this->getJson($url, ['Accept' => 'application/vnd.api+json'])
                ->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.api+json');
            $links = $response->json('links');
            $metaMembers = $response->json('meta');
            $data = $response->json('data');
            self::assertIsArray($links);
            self::assertIsArray($metaMembers);
            self::assertIsArray($data);

            self::assertSame(['first', 'last', 'prev', 'next'], array_keys($links));
            self::assertEqualsCanonicalizing($meta, array_keys($metaMembers));

            /** @var list<array{id: string}> $data */
            $pages[] = array_column($data, 'id');
            self::assertNotSame([], $data, 'A page with a next link is empty.');
            self::assertContains(gettype($links['next']), ['string', 'NULL']);
            $url = is_string($links['next']) ? $links['next'] : null;
            self::assertLessThanOrEqual((int) ceil(count($ids) / 4), count($pages), 'The pagination never ends.');
        }

        self::assertSame($ids, array_merge(...$pages));
    }

    /**
     * @param list<string> $meta
     */
    #[DataProvider('paginators')]
    public function test_an_empty_page_is_a_valid_document(string $paginator, array $meta): void
    {
        User::query()->delete();

        $response = $this->getJson("paginated/{$paginator}", ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data', [])
            ->assertJsonPath('links.next', null);
        $links = $response->json('links');
        $metaMembers = $response->json('meta');
        self::assertIsArray($links);
        self::assertIsArray($metaMembers);

        self::assertSame(['first', 'last', 'prev', 'next'], array_keys($links));
        self::assertEqualsCanonicalizing($meta, array_keys($metaMembers));
    }
}
