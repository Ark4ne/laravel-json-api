<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Probe;
use PHPUnit\Framework\TestCase;

/**
 * Magic reads are called by name (`__get('name')` for `$probe->name`) so that the test stays typed.
 */
final class ProbeTest extends TestCase
{
    public function test_a_root_has_no_path_and_no_read(): void
    {
        $probe = Probe::root();

        self::assertNull($probe->path());
        self::assertSame([], $probe->reads());
    }

    public function test_every_read_answers_a_probe_knowing_its_path(): void
    {
        $probe = Probe::root();

        self::assertSame('name', $probe->__get('name')->path());
        self::assertSame('posts', $probe->__call('posts', [])->path());
        self::assertSame('meta.key', $probe->__get('meta')['key']->path());
        self::assertSame('posts.latest', $probe->__get('posts')->__call('latest', [])->path());
    }

    public function test_the_journal_is_shared_by_the_tree_in_reading_order_without_duplicates(): void
    {
        $probe = Probe::root();

        $probe->__get('posts')->__call('latest', []);
        $name = $probe->__get('name');
        $probe->__get('posts');

        self::assertSame(['posts', 'posts.latest', 'name'], $probe->reads());
        self::assertSame($probe->reads(), $name->reads());
    }

    public function test_two_roots_have_separate_journals(): void
    {
        Probe::root()->__get('name');

        self::assertSame([], Probe::root()->reads());
    }

    public function test_a_static_call_answers_a_new_root(): void
    {
        self::assertNull(Probe::__callStatic('query', [])->path());
    }

    public function test_invoking_a_probe_answers_itself(): void
    {
        $probe = Probe::root()->__get('name');

        self::assertSame($probe, $probe());
    }

    public function test_a_probe_behaves_as_an_empty_value(): void
    {
        $probe = Probe::root();

        self::assertSame('', (string) $probe);
        self::assertSame([], $probe->toArray());
        self::assertSame([], $probe->jsonSerialize());
        self::assertSame('[]', $probe->toJson());
        self::assertSame('[]', json_encode($probe));
        self::assertSame([], iterator_to_array($probe));
        self::assertCount(0, $probe);
        self::assertFalse($probe->__isset('name'));
        self::assertFalse(isset($probe['key']));
    }

    public function test_writes_are_ignored(): void
    {
        $probe = Probe::root();

        $probe->__set('name', 'value');
        $probe['key'] = Probe::root();
        unset($probe['key']);

        self::assertSame([], $probe->reads());
    }
}
