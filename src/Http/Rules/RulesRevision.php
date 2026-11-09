<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Ark4ne\JsonApi\Definition\Revision;

/**
 * What the validation rules of one key were up to a revision, included (`Revised::was()`): public contract, for the
 * tools that document the rules.
 */
final readonly class RulesRevision
{
    /**
     * @param mixed $rules The rules of the key, as Laravel accepts them (a string, a list, rule objects, closures)
     */
    public function __construct(
        public Revision $revision,
        public mixed $rules,
    ) {}
}
