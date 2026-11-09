<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Rules;

use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\JsonApi\Exceptions\DefinitionException;

/**
 * The validation rules of one key of a form request across the revisions of the API: the head written plainly, the
 * history on the key that changed, as the fields of a resource declare theirs.
 *
 * ```php
 * public function rules(): array
 * {
 *     return [
 *         'data.attributes.title' => Revised::rules('required|string|max:255')
 *             ->was(ApiRevision::Initial, rules: 'required|string|max:80'),
 *         'data.attributes.summary' => Revised::rules('nullable|string')->since(ApiRevision::Labels),
 *         'data.attributes.code' => Revised::rules('string')->until(ApiRevision::Initial),
 *     ];
 * }
 * ```
 *
 * A `JsonApiFormRequest` resolves them at the revision of the request (`JsonApiContext::revisionOf()`); any other
 * form request calls `Revised::resolve()`. Bounds are included, as `was()`, `since()` and `until()` of the
 * descriptors.
 *
 * A `Revised` is the whole value of its key, never one rule of a list. A key left out of a revision leaves out its
 * members too (`tags` leaves out `tags.*`).
 *
 * Public contract, for the tools that document the rules: `head()`, `lifetime()`, `history()`, `existsAt()`, `at()`,
 * `resolve()`.
 */
final readonly class Revised
{
    /**
     * @param list<RulesRevision> $history Oldest first
     */
    private function __construct(
        private mixed $head,
        private Lifetime $lifetime,
        private array $history,
    ) {}

    /**
     * The rules of the key at the head, as Laravel accepts them for one key: a string, a list, rule objects, closures.
     */
    public static function rules(mixed $head): self
    {
        return new self($head, new Lifetime(), []);
    }

    /**
     * The rules of the key up to `$revision`, included. At a revision, the `was()` with the oldest revision at least as
     * new as it applies, whatever the order of the calls; none for the head.
     *
     * @throws DefinitionException when `$revision` is already declared, is outside the lifetime of the key, is its
     *                             `until()` (the head rules would never apply), or is a case of another enum than the
     *                             other revisions
     */
    public function was(Revision $revision, mixed $rules): self
    {
        foreach ($this->history as $declared) {
            if (RevisionOrder::compare($declared->revision, $revision) === 0) {
                throw DefinitionException::duplicateRulesRevision($revision);
            }
        }

        $history = [...$this->history, new RulesRevision($revision, $rules)];
        usort($history, static fn(RulesRevision $a, RulesRevision $b): int => RevisionOrder::compare($a->revision, $b->revision));

        return self::checked($this->head, $this->lifetime, $history);
    }

    /**
     * The key exists from `$revision`, included: it is left out of the rules of the older revisions.
     *
     * @throws DefinitionException when the lifetime is empty, a `was()` is outside it, or `$revision` is a case of
     *                             another enum than the other revisions
     */
    public function since(Revision $revision): self
    {
        return self::checked($this->head, new Lifetime($revision, $this->lifetime->until), $this->history);
    }

    /**
     * The key exists up to `$revision`, included: it is left out of the rules of the newer revisions and of the head.
     *
     * @throws DefinitionException when the lifetime is empty, a `was()` is outside it, or `$revision` is a case of
     *                             another enum than the other revisions
     */
    public function until(Revision $revision): self
    {
        return self::checked($this->head, new Lifetime($this->lifetime->since, $revision), $this->history);
    }

    /**
     * The rules of the key at the head (`rules()`), whatever its lifetime.
     */
    public function head(): mixed
    {
        return $this->head;
    }

    /**
     * The revisions the key exists for (`since()`, `until()`): every revision and the head when it declares none.
     */
    public function lifetime(): Lifetime
    {
        return $this->lifetime;
    }

    /**
     * The `was()` of the key, oldest first.
     *
     * @return list<RulesRevision>
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * Whether the key exists at `$revision` (`null` for the head).
     */
    public function existsAt(?Revision $revision): bool
    {
        return $this->lifetime->covers($revision);
    }

    /**
     * The rules of the key at `$revision` (`null` for the head), `null` when the key does not exist there
     * (`existsAt()`).
     *
     * @throws DefinitionException when `$revision` is a case of another enum than the declared revisions
     */
    public function at(?Revision $revision): mixed
    {
        return $this->existsAt($revision) ? $this->rulesAt($revision) : null;
    }

    /**
     * The rules of a form request at `$revision` (`null` for the head): each `Revised` replaced by its rules there, or
     * left out with the rules of its members (`key.*`, `key.name`) when its key does not exist there; the other rules
     * unchanged, in their order.
     *
     * ```php
     * protected function validationRules(): array
     * {
     *     return Revised::resolve(parent::validationRules(), JsonApiContext::revisionOf($this));
     * }
     * ```
     *
     * @template K of array-key
     *
     * @param array<K, mixed> $rules
     *
     * @return array<K, mixed>
     *
     * @throws DefinitionException when `$revision` is a case of another enum than the declared revisions, or a
     *                             `Revised` is one rule of a list
     */
    public static function resolve(array $rules, ?Revision $revision): array
    {
        $resolved = [];
        $absent = [];
        foreach ($rules as $key => $rule) {
            if ($rule instanceof self) {
                if ($rule->existsAt($revision)) {
                    $resolved[$key] = $rule->rulesAt($revision);
                } else {
                    $absent[] = "{$key}.";
                }
            } else {
                if (is_array($rule)) {
                    foreach ($rule as $item) {
                        if ($item instanceof self) {
                            throw DefinitionException::nestedRevisedRules((string) $key);
                        }
                    }
                }

                $resolved[$key] = $rule;
            }
        }

        if ($absent === []) {
            return $resolved;
        }

        return array_filter($resolved, static function (int|string $key) use ($absent): bool {
            foreach ($absent as $prefix) {
                if (str_starts_with((string) $key, $prefix)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * The rules of the key at `$revision`, a revision it exists at: those of the oldest `was()` at least as new as it
     * (`RevisionOrder::boundaryOf()`), the head's otherwise.
     */
    private function rulesAt(?Revision $revision): mixed
    {
        $boundary = RevisionOrder::boundaryOf(array_map(static fn(RulesRevision $declared): Revision => $declared->revision, $this->history), $revision);
        foreach ($boundary === null ? [] : $this->history as $declared) {
            if ($declared->revision === $boundary) {
                return $declared->rules;
            }
        }

        return $this->head;
    }

    /**
     * @param list<RulesRevision> $history Oldest first
     *
     * @throws DefinitionException when the lifetime is empty, a `was()` is outside it or at its `until()`, or the
     *                             revisions are cases of two enums
     */
    private static function checked(mixed $head, Lifetime $lifetime, array $history): self
    {
        if (
            $lifetime->since !== null && $lifetime->until !== null
            && RevisionOrder::compare($lifetime->since, $lifetime->until) > 0
        ) {
            throw DefinitionException::emptyRulesLifetime($lifetime->since, $lifetime->until);
        }

        foreach ($history as $declared) {
            if (!$lifetime->covers($declared->revision)) {
                throw DefinitionException::rulesRevisionOutsideLifetime($declared->revision);
            }
            if ($lifetime->until !== null && RevisionOrder::compare($declared->revision, $lifetime->until) === 0) {
                throw DefinitionException::rulesRevisionHidesHead($declared->revision);
            }
        }

        return new self($head, $lifetime, $history);
    }
}
