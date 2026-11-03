<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Loading;

use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether what a relationship reads is loaded on a model, for `Linkage::Loaded`.
 */
final class LoadedRelations
{
    /**
     * The relation the relationship is read from, otherwise the roots of its load, are loaded. Always true for what is
     * not an Eloquent model (read as it is), for a source that is an attribute rather than a relation, and for a
     * relationship read by a closure that loads nothing (what it reads is unknown).
     */
    public static function has(mixed $model, RelationshipDefinition $relationship): bool
    {
        if (!$model instanceof Model) {
            return true;
        }

        if ($relationship->source !== null) {
            return !$model->isRelation($relationship->source) || $model->relationLoaded($relationship->source);
        }

        foreach ($relationship->load->roots() as $path) {
            if (!self::pathLoaded([$model], explode('.', $path))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Model> $models
     * @param list<string> $segments
     */
    private static function pathLoaded(array $models, array $segments): bool
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return true;
        }

        foreach ($models as $model) {
            if (!$model->relationLoaded($segment)) {
                return false;
            }
        }

        return self::pathLoaded(LoadPlan::related($models, $segment), $segments);
    }
}
