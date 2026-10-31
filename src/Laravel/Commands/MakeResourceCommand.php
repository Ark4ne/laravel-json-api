<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel\Commands;

use Ark4ne\JsonApi\Descriptors\Relations\GuessedResource;
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
use Throwable;
use UnitEnum;

/**
 * Generates a resource in `app/Http/Resources`, described from its Eloquent model when there is one.
 *
 * - The model: `--model` (it must exist), otherwise the convention of the guessed relationships
 *   (`App\Http\Resources\PostResource` → `App\Models\Post`); `#[JsonApiModel]` is written when the convention does
 *   not find it.
 * - The attributes: the casts (typed descriptor, with the format of a date and the scale of a decimal), then the
 *   fillable attributes without cast (`mixed()`), then the timestamps; neither the key, `id`, `type`, the hidden
 *   attributes nor the foreign keys of the `BelongsTo` relations, rendered as relationships.
 * - The relationships: the public methods of the model typed with a relation, declared without resource class
 *   (`$this->one()`); a `MorphTo` is written as a comment, its resources being unknown.
 *
 * `stubs/jsonapi-resource.stub` of the application wins over that of the package.
 */
final class MakeResourceCommand extends GeneratorCommand
{
    /** Names a resource object reserves, never written as attributes (`DefinitionCompiler`). */
    private const RESERVED_NAMES = ['id', 'type'];

    /** @var class-string|null The model of the resource being generated, resolved by `handle()` */
    private ?string $model = null;

    /** The model built, when it is an Eloquent one */
    private ?Model $instance = null;

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
     * Resolves the model before writing anything: an explicit `--model` must exist and be built without argument.
     */
    public function handle(): ?bool
    {
        /** @var string|null $option */
        $option = $this->option('model');
        $model = $option !== null
            ? $this->explicitModel($option)
            : GuessedResource::conventionalModel($this->qualifyClass($this->getNameInput()));

        if ($option !== null && $model === null) {
            $this->fail("The model [{$option}] does not exist.");
        }

        if ($model !== null && is_subclass_of($model, Model::class)) {
            try {
                $this->instance = (new ReflectionClass($model))->newInstance();
            } catch (Throwable $e) {
                $this->fail("The model [{$model}] cannot be built without argument: {$e->getMessage()}");
            }
        }

        $this->model = $model;

        return parent::handle();
    }

    /**
     * @param string $name
     */
    protected function buildClass($name): string
    {
        $imports = [];
        $attribute = '';
        $reference = 'mixed';

        if ($this->model !== null) {
            // A short name already used in the file is written fully qualified (`class Post` of `App\Models\Post`).
            $taken = [class_basename($name), 'JsonApiResource', 'Request', 'JsonApiModel'];
            $clash = in_array(class_basename($this->model), $taken, true);
            $reference = $clash ? "\\{$this->model}" : class_basename($this->model);
            if (!$clash) {
                $imports[] = $this->model;
            }
            if ($this->model !== GuessedResource::conventionalModel($name)) {
                $imports[] = JsonApiModel::class;
                $attribute = "#[JsonApiModel({$reference}::class)]\n";
            }
        }

        [$relationships, $foreignKeys] = $this->instance !== null ? $this->relationships($this->instance) : [[], []];
        $attributes = $this->instance !== null ? $this->attributes($this->instance, $foreignKeys) : [];

        return str_replace(
            ['{{ imports }}', '{{ model }}', '{{ attribute }}', '{{ attributes }}', '{{ relationships }}'],
            [
                implode('', array_map(static fn(string $import): string => "use {$import};\n", $imports)),
                $reference,
                $attribute,
                $this->members($attributes),
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
     * The class given by `--model`, as is or qualified in the models of the application; `null` when none exists.
     *
     * @return class-string|null
     */
    private function explicitModel(string $option): ?string
    {
        $model = ltrim(str_replace('/', '\\', $option), '\\');
        $model = class_exists($model) ? $model : $this->qualifyModel($model);

        return class_exists($model) ? $model : null;
    }

    /**
     * @param list<string> $foreignKeys The keys of the `BelongsTo` relations, rendered as relationships
     *
     * @return array<string, string> Descriptors by attribute
     */
    private function attributes(Model $model, array $foreignKeys): array
    {
        $excluded = [$model->getKeyName(), ...self::RESERVED_NAMES, ...$model->getHidden(), ...$foreignKeys];
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
     * The descriptor of a cast, with its format or scale; `null` for a hashed attribute, never rendered.
     */
    private function descriptor(string $cast): ?string
    {
        if (is_subclass_of($cast, UnitEnum::class)) {
            return '$this->enum()';
        }

        $parts = explode(':', $cast, 2);
        $type = strtolower($parts[0]);
        $argument = $parts[1] ?? null;
        if ($type === 'encrypted' && $argument !== null) {
            [$type, $argument] = [strtolower($argument), null];
        }
        $format = $argument !== null ? '->format(' . var_export($argument, true) . ')' : '';

        return match ($type) {
            'hashed' => null,
            // The `timestamp` cast gives the Unix timestamp.
            'int', 'integer', 'timestamp' => '$this->integer()',
            'real', 'float', 'double' => '$this->float()',
            'decimal' => '$this->decimal()' . ($argument !== null && ctype_digit($argument) ? "->scale({$argument})" : ''),
            'string', 'encrypted' => '$this->string()',
            'bool', 'boolean' => '$this->bool()',
            'date', 'immutable_date' => '$this->date()' . $format,
            'datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime' => '$this->datetime()' . $format,
            'array', 'json', 'collection' => '$this->array()',
            default => '$this->mixed()',
        };
    }

    /**
     * The relations of the model, by the public methods typed with one, and the foreign keys of its `BelongsTo`.
     *
     * @return array{array<string, string|null>, list<string>} Descriptors by relationship, `null` for a `MorphTo`
     */
    private function relationships(Model $model): array
    {
        $relationships = [];
        $foreignKeys = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if (
                $method->isStatic()
                || $method->getNumberOfRequiredParameters() > 0
                || str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')
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

            if (is_a($relation, BelongsTo::class, true)) {
                array_push($foreignKeys, ...$this->foreignKeys($model, $method->getName()));
            }
        }

        return [$relationships, $foreignKeys];
    }

    /**
     * The foreign key of a `BelongsTo`, and the type column of a `MorphTo`: building the relation runs no query.
     *
     * @return list<string>
     */
    private function foreignKeys(Model $model, string $method): array
    {
        $callable = [$model, $method];

        try {
            $relation = is_callable($callable) ? $callable() : null;
        } catch (Throwable) {
            return [];
        }

        return match (true) {
            $relation instanceof MorphTo => [$relation->getForeignKeyName(), $relation->getMorphType()],
            $relation instanceof BelongsTo => [$relation->getForeignKeyName()],
            default => [],
        };
    }

    /**
     * The members of a returned array, one per line; a `MorphTo` (`null`) as a comment, its resources being unknown.
     *
     * @param array<string, string|null> $members
     */
    private function members(array $members): string
    {
        if ($members === []) {
            return '';
        }

        $lines = array_map(
            static fn(string $name, ?string $descriptor): string => $descriptor === null
                ? "            // '{$name}' => \$this->morph([/* Model::class => Resource::class */]),\n"
                : "            '{$name}' => {$descriptor},\n",
            array_keys($members),
            $members,
        );

        return "\n" . implode('', $lines) . '        ';
    }
}
