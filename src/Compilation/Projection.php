<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Compilation\Shapes;
use Ark4ne\ApiRevisions\Projection\Projector;
use Ark4ne\JsonApi\Descriptors\Schemas\Conditions;
use Ark4ne\JsonApi\Descriptors\Schemas\SchemaTypes;

/**
 * What renders the values of a response: the shapes and the projector of `ark4ne/laravel-api-revisions`. The Laravel
 * layer gives those of the application (compiled shapes, Eloquent source reader, value errors as configured) through
 * `Definitions::projectWith()`; without it, shapes compiled on first use and a plain projector.
 */
final readonly class Projection
{
    public Projector $projector;

    public function __construct(
        public Shapes $shapes = new Shapes(),
        ?Projector $projector = null,
    ) {
        // What a resource may declare (`$this->decimal()`, `->whenInFields()`, a relationship at an old revision), known
        // without the service provider too: a type or a macro already registered keeps its place.
        SchemaTypes::register();
        Conditions::register();

        $this->projector = $projector ?? new Projector(shapes: $shapes);
    }
}
