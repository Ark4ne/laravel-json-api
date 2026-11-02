<?php

declare(strict_types=1);

namespace Test\Feature\Skill;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Test\Feature\FeatureTestCase;

/**
 * An agent skill shipped with the package (`skills/<name>/`): its front matter, the links between its files, and the
 * `php` blocks of its examples run in the application of the tests.
 */
abstract class SkillTestCase extends FeatureTestCase
{
    /** @var array<string, true> The blocks already run, by file and title: a class is declared once per process */
    private static array $ran = [];

    /**
     * The directory of the skill, which is also its name.
     */
    abstract protected static function skill(): string;

    /**
     * @return list<string>
     */
    abstract protected static function files(): array;

    public function test_the_skill_declares_its_name_and_when_to_use_it(): void
    {
        self::assertSame(
            1,
            preg_match('/\A---\nname: ' . preg_quote(static::skill(), '/') . '\ndescription: (.+)\n---\n/', self::read('SKILL.md'), $matches),
        );
        self::assertLessThanOrEqual(1024, strlen($matches[1]));
        self::assertStringContainsString('Ark4ne\JsonApi', $matches[1]);
    }

    public function test_every_link_between_the_files_of_the_skill_resolves(): void
    {
        foreach (static::files() as $file) {
            preg_match_all('/\]\(([^)\s]+)\)/', self::read($file), $links);

            foreach ($links[1] as $link) {
                if (preg_match('#^[a-z]+://#', $link) === 1) {
                    continue;
                }

                [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);
                $target = self::directory() . ($path === '' ? $file : $path);

                self::assertFileExists($target, "{$file}: {$link}");

                if ($anchor !== null) {
                    self::assertContains($anchor, self::anchors((string) file_get_contents($target)), "{$file}: {$link}");
                }
            }
        }
    }

    /**
     * Runs the runnable `php` block of a section (`## <title>`) once per process.
     */
    protected static function runRecipe(string $file, string $title): void
    {
        if (isset(self::$ran["{$file}#{$title}"])) {
            return;
        }

        self::$ran["{$file}#{$title}"] = true;
        eval(self::code($file, $title));
    }

    /**
     * The runnable `php` block of a section (`## <title>`), without its `php skip` blocks.
     */
    protected static function code(string $file, string $title): string
    {
        $sections = preg_split('/^## /m', self::read($file));

        foreach ($sections === false ? [] : $sections as $section) {
            if (str_starts_with($section, "{$title}\n") && preg_match('/^```php\n(.*?)^```$/ms', $section, $block) === 1) {
                return $block[1];
            }
        }

        self::fail("{$file} has no runnable block for {$title}.");
    }

    /**
     * A form request of an example, resolved as Laravel resolves those of a controller: validated when resolved.
     */
    protected static function formRequest(string $class): JsonApiFormRequest
    {
        $request = app($class);
        self::assertInstanceOf(JsonApiFormRequest::class, $request);

        return $request;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected static function model(string $class, array $attributes): Model
    {
        self::assertTrue(is_subclass_of($class, Model::class), $class);

        return new $class($attributes);
    }

    /**
     * @return JsonApiResource<Model>
     */
    protected static function resource(string $class, Model $model): JsonApiResource
    {
        self::assertTrue(is_subclass_of($class, JsonApiResource::class), $class);

        return new $class($model);
    }

    protected static function read(string $file): string
    {
        return (string) file_get_contents(self::directory() . $file);
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 3) . '/skills/' . static::skill() . '/';
    }

    /**
     * The anchors GitHub gives to the headings of a Markdown file.
     *
     * @return list<string>
     */
    private static function anchors(string $markdown): array
    {
        preg_match_all('/^#{1,6} (.+)$/m', $markdown, $headings);

        return array_map(
            static fn(string $heading): string => str_replace(' ', '-', (string) preg_replace('/[^a-z0-9 _-]/', '', strtolower($heading))),
            $headings[1],
        );
    }
}
