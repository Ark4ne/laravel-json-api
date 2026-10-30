<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Rendering\Stages\AttributesStage;
use Ark4ne\JsonApi\Rendering\Stages\LinksStage;
use Ark4ne\JsonApi\Rendering\Stages\MetaStage;
use Ark4ne\JsonApi\Rendering\Stages\RelationshipsStage;

/**
 * Renders the resource object of a resource: identifies it, then runs the stages in order (pattern Pipeline).
 */
final readonly class ResourceObjectRenderer
{
    /** @var list<Stage> */
    private array $stages;

    public function __construct(Stage ...$stages)
    {
        $this->stages = array_values($stages);
    }

    /**
     * The stages of the package: attributes, relationships, links, meta.
     */
    public static function default(): self
    {
        return new self(new AttributesStage(), new RelationshipsStage(), new LinksStage(), new MetaStage());
    }

    /**
     * The stages of the package, then those of the configuration (`rendering.stages`), each built without argument.
     *
     * @throws InvalidConfigurationException when a configured class is not a `Stage`
     */
    public static function configured(Configuration $configuration): self
    {
        $stages = [];
        foreach ($configuration->renderingStages as $class) {
            $stages[] = is_subclass_of($class, Stage::class)
                ? new $class()
                : throw InvalidConfigurationException::forKey('rendering.stages', 'a list of stage classes');
        }

        return self::default()->with(...$stages);
    }

    /**
     * A renderer running the given stages after its own.
     */
    public function with(Stage ...$stages): self
    {
        return new self(...$this->stages, ...$stages);
    }

    /**
     * @return list<Stage>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    public function identify(RenderableResource $resource, JsonApiContext $context): ResourceObject
    {
        return ResourceObject::identify($resource->renderType($context), $resource->renderIdentifier($context));
    }

    public function render(
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject {
        $object = $this->identify($resource, $context);

        foreach ($this->stages as $stage) {
            $object = $stage->render($object, $resource, $context, $included);
        }

        return $object;
    }
}
