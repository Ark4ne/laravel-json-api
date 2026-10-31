<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Illuminate\Http\Resources\MergeValue;

/**
 * The members merged by `mergeWhen()` (or `mergeUnless()`): those of its value when the condition holds, those of
 * its default (or none) when it fails. Rendered as any `MergeValue`, but hidden from the definition whatever the
 * condition, with a `merge-when` diagnostic: the definition does not depend on how the probe evaluates it.
 *
 * @internal
 */
final class MergeWhenValue extends MergeValue {}
