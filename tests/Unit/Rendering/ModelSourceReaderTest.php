<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\ApiRevisions\Projection\PlainSourceReader;
use Ark4ne\JsonApi\Rendering\ModelSourceReader;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Test\app\Models\Post;
use Test\app\Models\User;

final class ModelSourceReaderTest extends TestCase
{
    public function test_an_attribute_of_a_model_is_read(): void
    {
        $user = (new User())->forceFill(['name' => 'Ada', 'email' => null]);

        self::assertSame('Ada', (new ModelSourceReader())->read($user, ['name']));
        self::assertNull((new ModelSourceReader())->read($user, ['email']));
        self::assertNull((new ModelSourceReader())->read($user, ['missing']));
    }

    public function test_a_path_goes_through_the_relations_of_a_model_and_plain_values(): void
    {
        $post = (new Post())->setRelation('user', (new User())->forceFill(['name' => 'Ada', 'settings' => ['theme' => ['dark' => true]]]));

        self::assertSame('Ada', (new ModelSourceReader())->read($post, ['user', 'name']));
        self::assertSame(['dark' => true], (new ModelSourceReader())->read($post, ['user', 'settings', 'theme']));
        self::assertNull((new ModelSourceReader())->read($post, ['editor', 'name']));
    }

    public function test_a_public_property_of_a_model_wins_over_an_attribute(): void
    {
        $model = new class extends Model {
            public string $label = 'property';
        };
        $model->forceFill(['label' => 'attribute', 'exists' => 'attribute']);

        self::assertSame('property', (new ModelSourceReader())->read($model, ['label']));
        self::assertFalse((new ModelSourceReader())->read($model, ['exists']));
    }

    public function test_what_is_not_a_model_is_read_as_the_base_reads_it(): void
    {
        $reader = new ModelSourceReader();
        $plain = new PlainSourceReader();
        $subject = ['a' => ['b' => 1], 'object' => (object) ['c' => 2]];

        foreach ([['a', 'b'], ['object', 'c'], ['missing']] as $path) {
            self::assertSame($plain->read($subject, $path), $reader->read($subject, $path));
            self::assertSame($plain->has($subject, $path), $reader->has($subject, $path));
            self::assertSame($plain->loaded($subject, $path), $reader->loaded($subject, $path));
        }
        self::assertSame(['b' => 1], $reader->peek($subject, 'a'));
    }

    public function test_reading_a_model_leaves_its_memory_as_it_is(): void
    {
        $users = [];
        for ($i = 0; $i < 200; $i++) {
            $users[] = (new User())->forceFill(['name' => "User {$i}"]);
        }
        $reader = new ModelSourceReader();

        $before = memory_get_usage();
        foreach ($users as $user) {
            $reader->read($user, ['name']);
        }

        // `get_object_vars()` on a model makes PHP keep its table of properties: about 3 KB each.
        self::assertLessThan(200 * 512, memory_get_usage() - $before);
    }
}
