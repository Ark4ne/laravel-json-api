<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiModel;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Console\Input\InputOption;
use UnitEnum;

/**
 * Generates a resource in `app/Http/Resources`, described from its Eloquent model when there is one.
 *
 * - The model: `--model`, otherwise the convention of the guessed relationships (`App\Http\Resources\PostResource` →
 *   `App\Models\Post`); `#[JsonApiModel]` is written when the convention does not find it.
 * - The attributes: the casts (typed descriptor), then the fillable attributes without cast (`mixed()`), then the
 *   timestamps; neither the key nor the hidden attributes.
 * - The relationships: the public methods of the model typed with a relation, declared without resource class
 *   (`$this->one()`); a `MorphTo` is written as a comment, its resources being unknown.
 *
 * `stubs/jsonapi-resource.stub` of the application wins over that of the package.
 */
final class MakeResourceCommand extends GeneratorCommand
{
    /** A `MorphTo` is written as a comment: its resources are unknown. */
    private const MORPH_TO = '$this->morph([/* Model::class => Resource::class */])';

    /** @var string */
    protected $name = 'make:jsonapi-resource';

    /** @var string */
    protected $description = 'Create a new JSON:API resource';

    /** @var string */
    protected $type = 'Resource';

    protected function getStub(): string
    {
        $custom = $this->laravel->basePath('stubs/jsonapi-resource.stub');

        return is_file($custom) ? $custom : __DIR__ . '/../../../stubs/jsonapi-resource.stub';
    }

    /**
     * @param string $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Http\\Resources";
    }

    /**
     * @return list<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            new InputOption('model', 'm', InputOption::VALUE_REQUIRED, 'The model of the resource'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Create the resource even if it already exists'),
        ];
    }

    /**
     * @param string $name
     */
    protected function buildClass($name): string
    {
        $model = $this->model($name);
        $instance = $model !== null && is_subclass_of($model, Model::class) ? new $model() : null;

        $imports = [];
        $attribute = '';
        if ($model !== null) {
            $imports[] = $model;
            if ($model !== $this->conventionalModel($name)) {
                $imports[] = JsonApiModel::class;
                $attribute = '#[JsonApiModel(' . class_basename($model) . "::class)]\n";
            }
        }

        $relationships = $instance !== null ? $this->relationships($instance) : [];

        return str_replace(
            ['{{ imports }}', '{{ model }}', '{{ attribute }}', '{{ attributes }}', '{{ relationships }}'],
            [
                implode('', array_map(static fn(string $import): string => "use {$import};\n", $imports)),
                $model !== null ? class_basename($model) : 'mixed',
                $attribute,
                $this->members($instance !== null ? $this->attributes($instance) : []),
                $relationships !== [] ? <<<PHP

                    protected function toRelationships(Request \$request): iterable
                    {
                        return [{$this->members($relationships)}];
                    }

                PHP : '',
            ],
            parent::buildClass($name),
        );
    }

    /**
     * The model of the resource: `--model`, otherwise the conventional one when it exists.
     *
     * @return class-string|null
     */
    private function model(string $resource): ?string
    {
        /** @var string|null $option */
        $option = $this->option('model');
        if ($option === null) {
            $model = $this->conventionalModel($resource);

            return $model !== null && class_exists($model) ? $model : null;
        }

        $model = ltrim(str_replace('/', '\\', $option), '\\');
        $model = class_exists($model) ? $model : $this->qualifyModel($model);

        return class_exists($model) ? $model : null;
    }

    /**
     * `X\Http\Resources\PostResource` → `X\Models\Post`, as for the relationships declared without resource class.
     */
    private function conventionalModel(string $resource): ?string
    {
        $segments = explode('\\Http\\Resources\\', $resource, 2);
        if (count($segments) !== 2) {
            return null;
        }

        $name = preg_replace('/Resource$/', '', $segments[1]);

        return $name === '' || $name === null ? null : "{$segments[0]}\\Models\\{$name}";
    }

    /**
     * @return array<string, string> Descriptors by attribute
     */
    private function attributes(Model $model): array
    {
        $excluded = [$model->getKeyName(), ...$model->getHidden()];
        $attributes = [];

        foreach ($model->getCasts() as $attribute => $cast) {
            $descriptor = $this->descriptor($cast);
            if ($descriptor !== null) {
                $attributes[$attribute] = $descriptor;
            }
        }

        foreach ($model->getFillable() as $attribute) {
            $attributes[$attribute] ??= '$this->mixed()';
        }

        if ($model->usesTimestamps()) {
            foreach ([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] as $attribute) {
                if ($attribute !== null) {
                    $attributes[$attribute] ??= '$this->datetime()';
                }
            }
        }

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * The descriptor of a cast; `null` for a hashed attribute, never rendered.
     */
    private function descriptor(string $cast): ?string
    {
        if (is_subclass_of($cast, UnitEnum::class)) {
            return '$this->enum()';
        }

        $type = strtolower(explode(':', $cast, 2)[0]);
        if ($type === 'encrypted' && str_contains($cast, ':')) {
            $type = strtolower(explode(':', $cast, 2)[1]);
        }

        return match ($type) {
            'hashed' => null,
            'int', 'integer' => '$this->integer()',
            'real', 'float', 'double' => '$this->float()',
            'decimal' => '$this->decimal()',
            'string', 'encrypted' => '$this->string()',
            'bool', 'boolean' => '$this->bool()',
            'date', 'immutable_date' => '$this->date()',
            'datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime', 'timestamp'
                => '$this->datetime()',
            'array', 'json', 'collection' => '$this->array()',
            default => '$this->mixed()',
        };
    }

    /**
     * @return array<string, string> Descriptors by relationship
     */
    private function relationships(Model $model): array
    {
        $relationships = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if (
                $method->isStatic()
                || $method->getNumberOfRequiredParameters() > 0
                || !$type instanceof ReflectionNamedType
                || !is_a($type->getName(), Relation::class, true)
            ) {
                continue;
            }

            $relation = $type->getName();
            $relationships[$method->getName()] = match (true) {
                is_a($relation, MorphTo::class, true) => null,
                is_a($relation, BelongsTo::class, true),
                is_a($relation, HasOne::class, true),
                is_a($relation, HasOneThrough::class, true),
                is_a($relation, MorphOne::class, true) => '$this->one()',
                default => '$this->many()',
            };
        }

        return array_map(static fn(?string $descriptor): string => $descriptor ?? self::MORPH_TO, $relationships);
    }

    /**
     * The members of a returned array, one per line.
     *
     * @param array<string, string> $members
     */
    private function members(array $members): string
    {
        if ($members === []) {
            return '';
        }

        $lines = array_map(
            static fn(string $name, string $descriptor): string => $descriptor === self::MORPH_TO
                ? "            // '{$name}' => {$descriptor},\n"
                : "            '{$name}' => {$descriptor},\n",
            array_keys($members),
            $members,
        );

        return "\n" . implode('', $lines) . '        ';
    }
}
