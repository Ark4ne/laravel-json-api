# Versioned resources — implementation plan

> Epics and user stories to deliver versioned resources in a minor 2.x release (target **2.1.0**).
> Design study: [`versioned-resources.md`](versioned-resources.md). This plan **supersedes** the options of the study
> that contradict §1 below (families A, B, C, G, any type that depends on the revision or on the include path).

## How to read this plan

- §1 is the **decided model**: the reference every story implements and tests.
- §2 maps the change onto the layers of `docs/architecture.md` §2.
- §3 is the Definition of Done shared by every story.
- §4 lists the epics and their stories. Each story has a goal, acceptance criteria (the red tests to write first), the
  code it touches (`file:line` at commit `0e68ea4`) and its dependencies.
- §5 gives the order and the milestones; §6 the questions still open; §7 the risks.

Sizes: **S** ≤ 1 day, **M** 2–3 days, **L** 4–5 days.

---

## 1. Decided model

### 1.1 Revisions are enum cases

The package defines a contract; the application implements it with an enum. The package never knows that enum, a
header or a date format.

```php
namespace Ark4ne\JsonApi\Definition;

/**
 * A revision of the API, as an application pins its clients on it. The cases of the enum are declared from the oldest
 * to the newest: their order is the order of the revisions.
 */
interface Revision extends \UnitEnum
{
    /** What changed in this revision, for the changelog. */
    public function description(): string;
}
```

```php
enum ApiRevision: string implements Revision
{
    case Initial = '2026-09-01';
    case Labels = '2026-12-01';
    case UsersReplaceTeachers = '2027-01-15';

    public function description(): string
    {
        return match ($this) {
            self::Initial => 'First published revision',
            self::Labels => 'Names become labels',
            self::UsersReplaceTeachers => 'Users replace teachers, authors and attendees',
        };
    }
}
```

- `was()`, `until()`, `since()`, `#[JsonApiSince]`, `#[JsonApiUntil]` and the guards accept a `Revision` only: no string, no date.
- Order = declaration order of the cases (`$revision::cases()`). Comparing cases of two different enums is a
  programming error.
- The enum holds the revisions **clients can be pinned on**, the oldest supported one first. The application maps its
  header to a case; it refuses retired revisions itself.
- The requested revision `null` is the head. The newest case renders the head as well.

### 1.2 Semantics: inclusive bounds

> **Decision D1 — confirmed:** inclusive bounds.

Every revision named in a declaration is **inclusive**:

| Declaration | Meaning |
| --- | --- |
| `was(X, …)` | the field looked like this **up to X included** |
| `until(X)` / `#[JsonApiUntil(X)]` | the field / resource exists **up to X included** |
| `since(X)` / `#[JsonApiSince(X)]` | the field / resource exists **from X included** |

Why: retiring the oldest revision `P` means deleting the case `P`. Larastan then reports exactly the declarations
that served only the clients of `P` (`was(P)`, `until(P)`, `#[JsonApiUntil(P)]`), and nothing else is dead. With exclusive
bounds ("before X"), the declarations serving only `P` would reference the next case, still alive, and stay in the
code unseen.

Writing rule: **when a field changes in a new revision, write `was(<the revision that was the newest until now>)`.**

### 1.3 `was()`: total replacement, only one applies

```php
was(Revision $revision, ?string $name = null, ?Describer $value = null): static
```

- A `was()` describes the **whole** field as it was up to `$revision`.
- For a client on revision `R`, the package applies **one** `was()`: the one with the **oldest revision ≥ `R`**
  among those declared on the field. If none qualifies, the head shape is used.
- The order is that of the revisions, never the order of the calls.
- An argument left out takes the value **of the head**, never that of a newer `was()`. The author repeats whatever
  differs from the head.
- `name:` alone keeps the head descriptor (its conditions, its type) under the old name. `value:` replaces the
  descriptor entirely, conditions included.

**Reference example** (written as such in US 2.5 and US 4.1):

```php
// Initial → Labels → UsersReplaceTeachers (head)
'display_name' => $this->string()
    ->was(ApiRevision::Labels, name: 'label')
    ->was(ApiRevision::Initial, name: 'name'),
```

| Client | Field rendered |
| --- | --- |
| head, `UsersReplaceTeachers` | `display_name` |
| `Labels` | `label` (`was(Labels)`) |
| `Initial` | `name` (`was(Initial)`) |

**Value at one revision, name at an older one** — the oldest `was()` must repeat the old value:

```php
'price' => $this->struct(fn() => [
    'amount' => $this->integer('price_cents'),
    'currency' => $this->string('currency'),
])
    ->was(ApiRevision::Labels, value: $this->integer('price_cents'))                 // Labels: integer cents
    ->was(ApiRevision::Initial, name: 'amount', value: $this->integer('price_cents')), // Initial: renamed, value repeated
```

Without the repeated `value:`, `Initial` clients would get the head struct under `amount`. `jsonapi:check` reports
this case with a `revision-value-gap` warning (US 3.4). The **contract test of the application** — the definitions
generated for `R` compared with a frozen snapshot of `R` — is the complete safety net (US 7.2).

### 1.4 Where the value of a `was()` reads from

The model has not changed, only the API has. A `value:` descriptor **without source** reads the head field's source:

- the head reads a model attribute (`$this->string()` → its name, `$this->string('x')` → `x`): the `was()` value reads
  that same attribute, not the old name;
- the head reads a relation (`$this->one(UserResource::class)` → relation `instructor`): the `was()` value reads the
  same relation;
- the head reads through a closure: the `was()` value must give its own source, otherwise the compilation reports a
  `revision-unsourced-value` error.

The conditions split along the same line (US 4.6):

- **Model side → always the head.** `whenHas()`, `whenAppended()`, `whenNotNull()`, `when()` and their closures read
  the model, which only has one version: the current one. They never depend on the revision; `$attribute` is the head
  source.
- **Request side → the revision.** `whenInFields()` and `whenInIncludes()` read `fields` and `include`, which the
  client writes in the names of its revision, the same names it receives.

### 1.5 Fields that exist for a range of revisions

```php
'full_name' => $this->string(fn() => "{$this->first_name} {$this->last_name}")
    ->until(ApiRevision::Labels),                     // removed in UsersReplaceTeachers
'tag_names' => $this->arrayOf($this->string(), fn() => $this->tags->pluck('name'))
    ->since(ApiRevision::UsersReplaceTeachers),       // added in UsersReplaceTeachers
```

- `until()` applies to an attribute, a relationship or a meta member.
- `since()` is **optional**. An addition is non-breaking and stays visible to every revision by default. `since()` is
  **required** in one case: when a field takes the name of an older field of the other kind (§1.6). It is
  **recommended** when the documentation of each revision must be exact.
- A field declared under `until()` stays in the head hook until its revision is retired. Convention: keep these fields
  at the end of the hook, under a `// Revisions` comment.

### 1.6 Frozen types, no attribute ↔ relationship change

- **A resource never changes type.** A new type is a **new resource**, absent from older revisions
  (`#[JsonApiSince(X)]`). A resource that only serves older revisions is marked `#[JsonApiUntil(X)]`.
- **A field never changes kind.** An attribute that becomes a relationship (or the reverse) is a removal (`until()`)
  plus an addition (`since()`).
- Attributes and relationships share one namespace (JSON:API): a new field may reuse the name of an older field of the
  other kind **if they coexist in no revision**. The uniqueness of names is checked **revision by revision**.
- Changing the cardinality (to-one ↔ to-many) stays a `was()`: both are relationships.

Users replacing teachers (cases 6 and 7):

```php
#[JsonApiType('users')]
#[JsonApiSince(ApiRevision::UsersReplaceTeachers)]
final class UserResource extends JsonApiResource { /* … */ }

#[JsonApiType('teachers')]
#[JsonApiUntil(ApiRevision::Labels)]
final class TeacherResource extends JsonApiResource { /* the old resource, kept as it was */ }

// LessonSessionResource (head)
'instructor' => $this->one(UserResource::class)
    ->was(ApiRevision::Labels, name: 'teacher', value: $this->one(TeacherResource::class)),
```

The same person reached as a `teacher` and as an `author` gets two entries in `included` (`teachers:7`,
`authors:7`): they are two resources. `IncludedRegistry` already keys by `type:id`; nothing to change.

### 1.7 Behaviour guards (cases 12 and 13)

A change of meaning has no shape to declare. The resource reads the revision of its rendering:

```php
'sessions' => $this->many(LessonSessionResource::class, fn() => $this->requestedRevision()->atMost(ApiRevision::Labels)
    ? $this->resource->sessions
    : $this->resource->sessions->where('user_id', $request->user()?->id)),
```

`$this->requestedRevision()` returns a `RequestedRevision`: `atMost(X)`, `atLeast(X)`, `is(X)`, `isHead()`. It is not
named `revision()`: a resource forwards unknown calls to its model (`DelegatesToResource::__call()`), and a model may
have a `revision()` of its own — taking that name would break it. Outside a resource (`with()`, `additional()`, a
collection, a controller), `RequestedRevision::of($request)` or `JsonApi::revision($request)` gives the same object.

### 1.8 Use cases

| # | Case | Written as | Story |
| --- | --- | --- | --- |
| 1 | Attribute renamed | `->was(X, name: 'old')` | 2.1, 4.1 |
| 2 | Attribute removed, restored, computed, typed | `'full_name' => $this->string(fn() => …)->until(X)` | 2.3, 4.1 |
| 3 | Type / format changed | `->was(X, value: $this->date())`, `value: $this->integer('price_cents')` | 2.1, 4.1 |
| 4 | Became nullable | `->was(X, value: $this->string(fn() => $this->nickname ?? ''))` | 2.1 |
| 5 | Enum value renamed / split | `->was(X, value: $this->enum(fn() => Legacy::from(…))->of(Legacy::class))` | 2.1 |
| 6 | Type renamed globally | new resource `#[JsonApiSince]`, old one `#[JsonApiUntil]`, a `was()` on every relationship | 2.4, 4.2, 6.1 |
| 7 | Type depending on the relationship | one old resource per old type, chosen by the `was()` of each relationship | 2.2, 4.2 |
| 8 | Relationship renamed | `->was(X, name: 'teacher')` on the relationship; old include paths and fields | 2.2, 4.2, 5.1 |
| 9 | Cardinality changed | `->was(X, name: 'teacher', value: $this->one(…, fn() => …->first()))` | 2.2, 4.2 |
| 10 | Relationship ↔ attribute | `until()` on one, `since()` on the other | 2.3, 3.4 |
| 11 | Resource meta changed | `was()` / `until()` on the descriptors of `toResourceMeta()` | 3.1, 4.1 |
| 12 | Meaning changed | guard `$this->requestedRevision()->atMost(X)` | 1.3 |
| 13 | Top-level meta / links | `was()` in `toDocumentMeta()`; `JsonApi::revision($request)` in `with()` / `additional()` | 1.2, 3.1 |
| 14 | Several successive revisions | several `was()`, one applies (§1.3) | 2.5 |
| 15 | Additions | nothing; `since()` for an exact documentation | 2.3 |

### 1.9 Out of scope (2.1)

- Reading the header, the registry of the application, the lifecycle (active, deprecated, retired): the application's.
- **Request bodies** (`JsonApiFormRequest` attributes renamed or required per revision) and the `source.pointer` of
  their errors: §6, a later epic if needed.
- The OpenAPI generator: its own repository, epic EV8 lists what it needs.

---

## 2. Change overview by layer

| Layer | New | Modified |
| --- | --- | --- |
| Definition | `Revision` (interface), `RevisionOrder`, `JsonApiSince`, `JsonApiUntil` (attributes) | `ResourceDefinition` (+ `revision`, `since`, `until`, `boundaries`) |
| Descriptors | `FieldRevision` (one `was()`), `FieldHistory` (selection), `RevisedMembers` (transformation of a hook's members) | `Describer` (`until()`, `since()`, history storage), `Values\Value::was()`, `Relations\Relation::was()`, `Members` (diagnostics) |
| Compilation | — | `Definitions` (`for($class, $revision)`, buckets, `loadConstraints()` per revision), `DefinitionCompiler`, `CompiledDefinitions` |
| Context | `RequestedRevision` | `JsonApiContext` (+ `revision`, `revisionOf()`) |
| Loading | — | `LoadPlanner` |
| Rendering | — | `RenderingException::notInRevision()` |
| Resources | `Concerns\Revisions` (`requestedRevision()`) | `Concerns\Renderable`, `Concerns\ConditionallyLoadsAttributes` (helpers translated, US 4.6) |
| Http | — | `Rules\Includes`, `Rules\Fields`, `JsonApiFormRequest` |
| Laravel | — | `JsonApi` (`useRevision()`, `revision()`), `CheckDefinitionsCommand`, `CacheDefinitionsCommand` |
| Testing | — | `ResourceAssertions` |

Unchanged on purpose: `renderType()`, `ResourceObject`, `ResourceObjectRenderer`, `IncludedRegistry`,
`RelationshipsStage`, `ValueResolver`, `RelationshipDefinition`, `AttributeDefinition`.

**Central invariant:** one pure function, `RevisedMembers`, turns the members of a hook (head) into the members of
revision `R`. The compiler applies it on the probe, the renderer on the resource, so definition and document agree
by construction. Every story of EV3 and EV4 tests this invariant.

---

## 3. Definition of Done (every story)

1. **Contract first**: signatures and PHPDoc with neutral bodies, then red tests, then the implementation
   (`CLAUDE.md`).
2. Unit tests: one test class per class; test names are sentences.
3. Feature tests: documents validated against the JSON:API 1.1 schema, expected documents in
   `tests/Feature/Revisions/expected/`, query counts asserted for includes.
4. **Non-regression**: the existing suite and its fixtures are unchanged (a resource without revision renders as in
   2.0).
5. `composer ci` green: PHPStan max with an empty baseline, phpat (layers, `final`, traits only in `Resources/`),
   PER-CS.
6. `composer test:coverage` ≥ 90 %; Infection MSI ≥ 80 % on the new classes.
7. `CHANGELOG.md` updated under `[Unreleased]` for every public change.
8. Octane: no static state outside `Compilation\Definitions`; the revision travels in the context.

---

## 4. Epics

### EV1 — Revision contract and context

Goal: the revision of a request is known to the rendering, the validation and the resources, without the package
knowing anything about the application's format.

#### US 1.1 — Revision contract and order · S

*As a package maintainer, I want a revision contract and its order, so that every declaration compares revisions the
same way.*

Acceptance criteria:
- `Definition\Revision extends UnitEnum` with `description(): string`.
- `Definition\RevisionOrder` (`final`, pure):
  - `compare(Revision $a, Revision $b): int`;
  - `atMost(?Revision $requested, Revision $bound): bool`, `atLeast(?Revision $requested, Revision $bound): bool` (the
    head, `null`, is newer than every revision: the single place of that rule, `RequestedRevision` delegates to it);
  - `newest(Revision ...$revisions): ?Revision`, `oldest(…)`;
  - `previous(Revision $revision): ?Revision`.
- Comparing cases of two enums is a programming error: `DefinitionException::mixedRevisions()` (`LogicException`, as
  every definition error).
- A test enum `tests/app/Revisions/ApiRevision` (`Initial`, `Labels`, `UsersReplaceTeachers`).

Tests: `test_revisions_are_ordered_by_the_declaration_of_their_cases`,
`test_comparing_revisions_of_two_enums_throws`, `test_previous_of_the_oldest_revision_is_null`.

#### US 1.2 — The revision of a request · M

*As an application developer, I want to set the revision of a request once, in a middleware, so that every rendering
and every validation of that request uses it.*

Acceptance criteria:
- `JsonApiContext` gets `public ?Revision $revision = null` as the **last** constructor parameter, kept by
  `withInclude()` and `withPath()` (`src/Context/JsonApiContext.php:22-73`).
- `JsonApiContext::REVISION = 'jsonapi.revision'`: the request attribute. `fromRequest()` reads it (`:34-43`).
- `JsonApiContext::revisionOf(Request): ?Revision` (Context layer, readable by Http). A value that is not a
  `Revision` throws `InvalidArgumentException`.
- `JsonApi::useRevision(Request $request, ?Revision $revision): void` and `JsonApi::revision(Request): RequestedRevision`
  (Laravel layer, delegating to the Context).
- Resources pick it up without the container (`src/Resources/Concerns/ToResponse.php:65-75`), as the container
  binding does (`src/Laravel/JsonApiServiceProvider.php:102-110`).
- Two requests in a row (Octane) do not share their revision.

Tests: Unit `JsonApiContextTest` (`test_the_context_reads_the_revision_of_the_request`,
`test_entering_a_relationship_keeps_the_revision`, `test_a_request_without_revision_renders_the_head`); Feature
`test_two_requests_in_a_row_do_not_share_their_revision`.

#### US 1.3 — Revision guards in resources · S

*As an application developer, I want to read the revision of the rendering inside a resource, so that I can keep the
old behaviour of a field whose meaning changed (case 12).*

Acceptance criteria:
- `Context\RequestedRevision` (`final readonly`): `atMost(Revision)`, `atLeast(Revision)`, `is(Revision)`, `isHead()`.
  A `null` revision is the head: `atMost()` is false, `atLeast()` true.
- `Resources\Concerns\Revisions::requestedRevision(): RequestedRevision` reads `renderingContext`
  (`src/Resources/Concerns/Renderable.php:31`), as `whenInFields()` does (`ConditionallyLoadsAttributes.php:56`).
- On the probe (compilation), `requestedRevision()` answers the head until US 3.2 compiles per revision: it then
  answers for the revision being compiled.
- A collection (`toDocumentMeta($request)`), `with()` and `additional()` use `RequestedRevision::of($request)`: no
  helper on `JsonApiCollection`.

Tests: `test_a_guard_keeps_the_old_relationship_content_for_an_old_revision`,
`test_a_guard_renders_the_head_content_without_revision`.

---

### EV2 — Field-level declarations

Goal: the syntax of §1, stored in immutable descriptors, plus the selection rule as one pure function.

#### US 2.1 — `was()` on value descriptors · M

*As an application developer, I want to declare what an attribute was up to a revision, so that older clients keep
its name and its value (cases 1, 3, 4, 5).*

Acceptance criteria:
- `Values\Value::was(Revision $revision, ?string $name = null, ?Value $value = null): static`, immutable
  (`Describer::with()`), returns a clone with one more `FieldRevision`.
- `FieldRevision` (`final readonly`): `revision`, `?string $name`, `?Describer $value`.
- Available on every value type, the struct included, and on the members of a struct.
- No effect on the head: the descriptor resolves and describes itself exactly as before (unit test on the definition
  and the value).
- PHPStan: `$this->string()->was(ApiRevision::Labels, value: $this->one(…))` is rejected (a `Value` expected).

Tests: `test_was_returns_a_new_descriptor`, `test_was_has_no_effect_on_the_head_definition`,
`test_was_accepts_a_value_of_another_type`.

#### US 2.2 — `was()` on relation descriptors · M

*As an application developer, I want to declare what a relationship was up to a revision, so that older clients keep
its name, its related resource and its cardinality (cases 7, 8, 9).*

Acceptance criteria:
- `Relations\Relation::was(Revision $revision, ?string $name = null, ?Relation $value = null): static`.
- `value:` may change the related resource (`TeacherResource`) and the cardinality (`one` ↔ `many`).
- Filters, `withLoad()`, links and meta of a `value:` relation are its own: nothing is inherited from the head.
- Works for `one`, `many`, `morph`, `morphMany`, and inside `applyWhen()` (`RelationRaw`).

Tests: `test_was_on_a_relation_keeps_its_own_load`,
`test_was_on_a_to_many_relationship_accepts_a_to_one_relation`.

#### US 2.3 — `until()` and `since()` on descriptors · S

*As an application developer, I want to declare that a field exists only up to, or only from, a revision (cases 2, 10,
15).*

Acceptance criteria:
- `Describer::until(Revision)` and `Describer::since(Revision)`, immutable, available on values, structs (and their
  members), relations and meta descriptors.
- Calling `until()` (or `since()`) twice on the same descriptor keeps the last call and reports a
  `revision-duplicate` diagnostic (US 3.4).
- No effect on the head when the head revision is inside the range. A field `until(X)` is absent from the head
  definition: the head is "newer than every case".

Tests: `test_until_hides_the_field_from_the_head`, `test_since_keeps_the_field_in_the_head`.

#### US 2.4 — `#[JsonApiSince]` and `#[JsonApiUntil]` on resources · S

*As an application developer, I want to mark a resource that only exists from, or up to, a revision, so that the
package and the static analysis know it (cases 6, 7).*

Acceptance criteria:
- Attributes `Definition\JsonApiSince(Revision $revision)` and `Definition\JsonApiUntil(Revision $revision)`, `TARGET_CLASS`.
- Read **by reflection in the compiler** (`DefinitionCompiler`, through a `Compilation` helper cached by
  `Definitions` like `declaredType()`): `DefinitionSource` gets **no new method** (Q4). Read on the class
  itself only, like `#[JsonApiType]` (`src/Resources/Concerns/Definable.php:70-77`): a subclass of a resource does
  not inherit its lifetime.
- A resource with both, `since` newer than `until`: `DefinitionException`.
- Larastan reports the attribute when its case is deleted (the resources of `tests/app/Revisions` use them and are
  analysed by `composer stan`).
- Done: the bounds are given as a `Definition\Lifetime` (`covers(?Revision)`), compiled by
  `DefinitionCompiler::lifetime()` and cached by `Definitions::lifetime()`.

Tests: `test_a_resource_until_a_revision_declares_it`, `test_a_resource_with_since_after_until_cannot_be_compiled`.

#### US 2.5 — Selecting the shape of a field for a revision · M

*As a package maintainer, I want one pure function giving the shape of a field for a revision, so that the
compilation and the rendering never disagree.*

Acceptance criteria:
- `Descriptors\FieldHistory::at(Describer $head, string $headName, ?Revision $requested): ?array{string, Describer}`:
  `null` when the field does not exist at `$requested`, otherwise the name and the descriptor to use.
- Implements §1.2 and §1.3 (decision D1): `until`/`since` first; then the `was()` with the oldest revision
  ≥ `$requested`; arguments left out taken from the head.
- Implements §1.4: a `value:` without source reads the head's source (string retriever, or head name); a head read by
  a closure plus a `value:` without source → `null` source, reported by US 3.4.
- `name:` alone keeps the head descriptor; the head name stays its source.
- The **reference example** of §1.3 is a data-provider test, case by case.
- Done: the descriptor returned is always **run with the head name as `$attribute`** (model side → head, §1.4): the
  renderer (US 3.1, 4.6) keys the field by the name returned, never passes it to the descriptor. Conditions receive
  the descriptor they run on (4th argument of a rule), so that `whenNotNull()`, `whenFilled()`, `whenLoaded()` and
  `whenPivotLoaded()` read the source of the copy `readingFrom()` returns.

Tests: `test_the_head_is_used_when_no_was_applies`, `test_the_oldest_was_newer_than_the_request_applies`,
`test_the_order_of_the_calls_does_not_matter`, `test_an_argument_left_out_takes_the_head_value`,
`test_a_renamed_field_still_reads_the_head_attribute`, `test_a_field_until_a_revision_is_absent_after_it`.

---

### EV3 — Definitions per revision

Goal: `Definitions::for($class, $revision)` describes the shape of `$class` for `$revision`, compiled from the same
transformation as the rendering.

#### US 3.1 — Transforming the members of a hook for a revision · L

*As a package maintainer, I want to turn the members of a hook into those of a revision, so that the compiler and the
renderer share one code path.*

Acceptance criteria:
- `Descriptors\RevisedMembers::apply(iterable $members, ?Revision $revision): iterable`. Pure, lazy (no value
  resolved), order of the members kept, each member through `FieldHistory::at()`.
- Handles: keyed descriptors; unnamed descriptors (`retrieveName()`, `src/Descriptors/Describer.php:204`);
  `MergeValue` of `applyWhen()`, recursively; `RelationRaw`; struct members (the struct closure is wrapped so that its
  members are transformed when it is read, `src/Descriptors/Values/ValueStruct.php:68-74`); closures and raw values
  (unchanged, they have no history).
- `$revision === null`: returns `$members` as they are (same instance).
- Applies to `toAttributes()`, `toRelationships()`, `toResourceMeta()`, `toDocumentMeta()` (resources and
  collections).

Tests: one per member kind, plus the **invariant test**: for every fixture resource and every revision, the names of
the definition equal the names of the rendered document with an empty fieldset.

#### US 3.2 — Compiling a definition for a revision · L

*As a tool or a validation rule, I want the definition of a resource at a revision, so that I describe and validate what
an older client sees.*

Acceptance criteria:
- `Definitions::for(string $class, ?Revision $revision = null): ResourceDefinition`; without revision, the same object
  as today (`src/Compilation/Definitions.php:76-84`).
- **Buckets**: two revisions giving the same shape of `$class` share one definition. A bucket is identified by its
  boundary, the oldest revision ≥ `R` at which a declaration of the class changes the shape (`was(X)` and `until(X)` →
  `X`, `since(X)` → `previous(X)`, the same for `#[JsonApiSince]` / `#[JsonApiUntil]`), or the head. Cache key `class@boundary`.
- **Fast path**: `R` newer than every boundary of the class → the head definition, in one comparison.
- `Definitions::isRevised(string $class): bool` (`@internal`): whether a hook of the class declares a revisioned
  member. Needed by the rendering of the **head** too: a field `until(X)` is written in the head hook and must be
  removed for head clients (spike M0, §5.1).
- `DefinitionCompiler::compile($class, ?Revision $revision)` runs the hooks on the probe **once**, then applies
  `RevisedMembers` per bucket (`src/Compilation/DefinitionCompiler.php:171-214`).
- `Definitions::loadConstraints($class, $revision)`: the constraints are keyed by the names of the revision
  (`:109-112`).
- `Definitions::exists(string $class, ?Revision $revision): bool` (`#[JsonApiSince]` / `#[JsonApiUntil]`); `for()` on a revision where
  the class does not exist throws `DefinitionException::notInRevision()`.
- `Definitions::flush()` clears every bucket.

Tests: `test_two_revisions_with_the_same_shape_share_a_definition`,
`test_a_revision_newer_than_every_declaration_reads_the_head`, `test_a_resource_since_a_revision_does_not_exist_before_it`,
`test_load_constraints_follow_the_renamed_relationship`.

#### US 3.3 — Definition model additions · S

*As a tool author, I want to know which revision a definition describes and where the shape of a resource changes, so
that I generate one document per revision without comparing them myself.*

Acceptance criteria:
- `ResourceDefinition` gets (each with a default, `__set_state` keys optional, `src/Definition/ResourceDefinition.php:28-38,156-159`):
  - `?Revision $revision`: the boundary described, `null` for the head;
  - `?Revision $since`, `?Revision $until`: the lifetime of the resource;
  - `list<Revision> $boundaries`: where its shape changes, oldest first.
- Cases are exported by `var_export()` (`\App\ApiRevision::Labels`) and read back from the compiled file.
- `AttributeDefinition` and `RelationshipDefinition` are **unchanged**: a definition is already the shape of its
  revision.
- `docs/architecture.md` §3 states that **additive** public properties of the definition model are minor changes
  (US 7.1).

Tests: `test_the_definition_names_its_revision`, `test_the_boundaries_list_the_declared_revisions_once`,
`test_a_definition_with_revisions_survives_var_export`.

#### US 3.4 — Diagnostics per resource · M

*As an application developer, I want the compilation to report the declarations that cannot be right, so that
`jsonapi:check` catches them before a client does.*

| Code | Level | When |
| --- | --- | --- |
| `revision-duplicate` | error | two `was()` with the same revision on a field; `until()` / `since()` set twice |
| `revision-empty-was` | error | `was()` without `name:` nor `value:` |
| `revision-out-of-life` | error | `was(X)` outside the range of the field (`X` newer than its `until`, older than its `since`) |
| `revision-name-collision` | error | two fields with the same name **in one revision** (attribute and relationship included) |
| `revision-reserved-name` | error | a field named `id` or `type` in one revision (`DefinitionCompiler.php:85-93`, per bucket) |
| `revision-unsourced-value` | error | `value:` without source while the head reads through a closure |
| `revision-value-gap` | warning | a newer `was()` gives `value:` (or `name:`) and an older one does not: the older one serves the head's |
| `revision-noop` | warning | `since(X)` / `#[JsonApiSince(X)]` where `X` is the oldest case: it hides nothing any more |
| `revision-mixed-enums` | error | declarations of one resource from two enums |

Acceptance criteria: each code is a public constant of `DefinitionCompiler` (like `RESERVED_NAME`); the subject is the
field; the message names the revision and the fix.

Tests: one per code, plus `test_reusing_the_name_of_a_removed_relationship_needs_since` (case 10).

#### US 3.5 — Compiled cache per revision · S

*As an application developer, I want `php artisan optimize` to compile every revision, so that an old client costs
one OPcache read like the head.*

Acceptance criteria:
- `CompiledDefinitions` writes and reads the keys `class@boundary` (`src/Compilation/CompiledDefinitions.php:29-80`).
- `jsonapi:cache` compiles every bucket of every class found; the command prints the number of definitions written.
- A compiled file of 2.0 (no bucket key) is still read.

Tests: `test_the_cache_command_writes_every_revision`, `test_a_compiled_file_without_revisions_is_still_read`.

---

### EV4 — Rendering per revision

Goal: a document rendered at revision `R` is exactly the shape the definitions of `R` describe, wherever a resource
appears.

Shared feature scenario (`tests/Feature/Revisions/`), on the models of `tests/app`. Its resources live in
`tests/app/Http/Resources/Revisions/`, so the existing resources and their fixtures stay untouched:

- `users`: `display_name` (head) ← `label` (`Labels`) ← `name` (`Initial`); `full_name` `until(Labels)`;
  `born_at` date-time ← date.
- `posts.user` → `users` (head) ← `writer` → `WriterResource` (`writers`, `#[JsonApiUntil(Labels)]`).
- `comments.user` → `users` (head) ← `commenter` → `CommenterResource` (`commenters`, `#[JsonApiUntil(Labels)]`).
- `UserResource` `#[JsonApiSince(UsersReplaceTeachers)]`.
- Expected documents per revision: `expected/{head,labels,initial}/*.json`.

#### US 4.1 — Attributes and meta at a revision · M

*As an older client, I want the attributes and meta of my revision, filtered by my sparse fieldset, so that my app
keeps working.*

Acceptance criteria:
- `Renderable::renderAttributes()`, `renderResourceMeta()`, `renderDocumentMeta()` apply `RevisedMembers` when the
  bucket of the class is not the head (`src/Resources/Concerns/Renderable.php:66-74,142-165`).
- `fields[users]=label` filters on the old name (the members are transformed before `ValueResolver`,
  `src/Rendering/ValueResolver.php:66-71`); `fields[users]=display_name` renders nothing for `Labels`.
- A renamed attribute reads the head attribute (§1.4).
- A field `until(Labels)` is rendered for `Initial` and `Labels`, never for the head.
- **Fast path**: for a class without revisioned member (`Definitions::isRevised()`), `RevisedMembers` does not run,
  whatever the revision (asserted with a spy). For a revisioned class at the head, it only removes the `until()`
  fields (spike M0, §5.1).
- `RevisedMembers` copies a `MergeValue` keeping its class: a `MergeWhenValue` (`mergeWhen()`) stays hidden from the
  definition (spike M0, §5.1).

Tests: the reference example (§1.3) end to end; `test_the_sparse_fieldset_of_an_old_client_uses_the_old_names`.

#### US 4.2 — Relationships and included resources at a revision · L

*As an older client, I want my old relationships, include paths and resource types, so that the same person keeps
appearing as a writer and as a commenter (cases 7, 8, 9).*

Acceptance criteria:
- `Renderable::renderRelationships()` applies `RevisedMembers` and reads `Definitions::for(static::class,
  $context->revision)` (`src/Resources/Concerns/Renderable.php:85-86`).
- `include=writer` and `include=comments.commenter` (old names, nested) include `writers:1` and `commenters:1` for the
  same user; the head includes `users:1` once.
- The linkage and `included` agree on the type (no change to `RelationshipsStage`: the old resource is another class).
- `fields[writers]=…` filters the old resource.
- A to-many relationship whose `was()` is a to-one renders `data` as an object (case 9).
- The number of SQL queries is asserted for every revision; it is the same as the head's for the same includes.

Tests: `test_the_same_user_is_included_once_per_old_type`, `test_an_old_nested_include_path_is_rendered`,
`test_a_relationship_that_was_to_one_renders_one_identifier`.

#### US 4.3 — Eager loading at a revision · M

*As a package maintainer, I want the load plan of a revision, so that an old include path loads its relations in
batch.*

Acceptance criteria:
- `LoadPlanner::plan()` reads `Definitions::for($class, $context->revision)` and
  `Definitions::loadConstraints($class, $context->revision)` (`src/Loading/LoadPlanner.php:39-40`).
- The fieldset filter (`:48`) and `includes($name)` (`:52`) use the names of the revision.
- Nested plans (`:88`, `:93`) plan the related class of the revision (`WriterResource`, not `UserResource`).
- A `was()` relation with its own `withLoad()` and constraint loads it.

Tests: `test_an_old_include_loads_its_relations_in_batch`, `test_a_was_relation_loads_its_own_constraint`.

#### US 4.4 — A resource that does not exist at the revision · S

*As an application developer, I want a clear error when a resource is rendered at a revision where it does not exist,
so that a missing `was()` never reaches a client silently.*

Acceptance criteria:
- Rendering a resource whose class does not exist at `R` (primary, related or included) throws
  `RenderingException::notInRevision($class, $revision)`, before anything is written.
- The check costs nothing for a class without `#[JsonApiSince]` / `#[JsonApiUntil]`.
- `jsonapi:check` reports the same mistake statically (US 6.1).

Tests: `test_rendering_a_resource_before_its_since_throws`,
`test_a_relationship_without_was_to_a_new_resource_throws_for_an_old_client`.

#### US 4.6 — Conditions and field helpers follow the revision · M

*As an application developer, I want the conditions of my fields and the helpers `whenInFields()` /
`whenInIncludes()` to keep working for older clients, so that a renamed field behaves like the head one.*

The rule: **what reads the model follows the head, what reads the request follows the revision.** The model has
one version, the current one; `fields` and `include` are written by the client in the names of its revision.

| Condition | Reads | Name used |
| --- | --- | --- |
| `->whenHas()`, `->whenAppended()`, `->whenNotNull()`, `->whenFilled()`, `->when()` / `->unless()` and their closures (`$attribute`), `->can()` | the model, the request user | **head** source, whatever the revision |
| `->whenLoaded()` on a relation | the loaded relations of the model | **head** relation |
| `->whenInFields()` (descriptor) | `fields[type]` | **revision** name |
| `->whenIncluded()` on a relation | `include` | **revision** name |
| `$this->whenInFields($request, 'name', $value)` | `fields[type]` | head name written in the code, **translated** to the revision |
| `$this->whenInIncludes($request, 'name', $value)` | `include` | head name written in the code, **translated** to the revision |

The two resource helpers wrap a **value**, not a field: they run inside the hook, before the member is attached to its
key, and may test another field (`'comments_count' => $this->whenInIncludes($request, 'comments', fn() => …)`).
Hence a name written by hand, as in Laravel. It is a name of the code, so a head name.

Acceptance criteria:
- **Model side**: the rules receive the head source (§1.4), for the head descriptor under an old name and for the
  descriptor of a `value:`. `->whenHas()` on `display_name` rendered as `label` reads `$model->display_name`.
  Nothing in these rules reads the revision.
- **Request side, descriptors**: sparse fields and `->whenIncluded()` are checked against the revision name. They
  already go through the rendered name (`src/Rendering/ValueResolver.php:107-112`, `RelationshipsStage.php:40-44`),
  and the members are renamed before. Covered by tests, no new code expected.
- **Request side, helpers**: the head name is translated to the revision:
  - `whenInFields($request, 'display_name', …)` tests `fields[users]=label` for `Labels`;
  - `whenInIncludes($request, 'user', …)` tests `include=writer` for `Labels`, relative to the current include path;
  - a name absent from the revision (`until()`, `since()`) makes the condition fail.
- Translation table head name → revision name, per class and bucket: `Definitions::names($class, $revision)`
  (`@internal`, memory and compiled file, built by the compiler from `FieldHistory`).
- Head: no translation (fast path).

Tests: `test_when_has_on_a_renamed_field_reads_the_head_attribute`,
`test_the_when_in_fields_helper_translates_a_head_name_for_an_old_client`,
`test_the_when_in_includes_helper_translates_a_head_relationship_for_an_old_client`,
`test_a_helper_naming_a_field_absent_from_the_revision_fails`,
`test_a_sparse_field_is_rendered_when_an_old_client_requests_its_old_name`.

#### US 4.5 — Performance guard · S

*As a package maintainer, I want to prove that the head costs nothing, so that versioning is not a regression for
clients on the latest revision.*

Acceptance criteria:
- `composer benchmark` does not regress (reference `bin/benchmark/reference.json`).
- A revisioned scenario is added to `bin/benchmark/`: head, newest case and oldest case measured, reported, not gated.
- `composer benchmark -- --cold`: with compiled definitions, an old revision costs the same as the head.

---

### EV5 — HTTP validation per revision

Goal: `include` and `fields` of an older client are validated against the shape of its revision.

#### US 5.1 — `Includes` rule at a revision · S

Acceptance criteria:
- `new Includes(string $resource, ?int $maxDepth = null, ?Revision $revision = null)`; `revision()` accessor for the
  tools.
- Reads `Definitions::for(…, $revision)` at every level (`src/Http/Rules/Includes.php:93`).
- For `Labels`: `writer` is accepted, `author` rejected; for the head, the reverse.

Tests: `test_an_old_client_may_include_an_old_relationship`, `test_an_old_client_may_not_include_a_new_relationship`.

#### US 5.2 — `Fields` rule at a revision · S

Acceptance criteria:
- `new Fields(string $resource, ?Revision $revision = null)`; `revision()` accessor.
- Indexes the types of the revision: `writers` and `commenters` for `Labels`, not `users`
  (`src/Http/Rules/Fields.php:106-132`).
- `fields[users]=label` is accepted for `Labels`, `fields[users]=display_name` rejected.

Tests: `test_an_old_client_may_restrict_an_old_type`, `test_an_old_client_may_not_restrict_a_new_type`.

#### US 5.3 — `JsonApiFormRequest` at a revision · S

Acceptance criteria:
- `validationRules()` builds both rules with `JsonApiContext::revisionOf($this)` (`src/Http/JsonApiFormRequest.php:79-83`).
- The middleware setting the revision runs before the form request: documented, and covered by a feature test.
- Error responses keep `source.parameter` (`include`, `fields[writers]`): nothing to change in
  `ValidationErrorRenderer`.

---

### EV6 — Tooling and testing

#### US 6.1 — `jsonapi:check` at every revision · M

*As an application developer, I want `jsonapi:check` to check every revision and the references between resources, so
that a forgotten `was()` fails CI.*

Acceptance criteria:
- Every bucket of every resource is compiled and its diagnostics printed with the revision
  (`[Labels] users.label: …`).
- Cross-resource diagnostic **`revision-unavailable-resource`** (error), for every revision `R` and every
  relationship of a resource existing at `R`: its related resources must exist at `R`. It covers:
  - a parent pointing to a `#[JsonApiSince]` resource without `was()` for older revisions (forgotten `was()`);
  - a head relationship pointing to an `#[JsonApiUntil]` resource (an old resource still referenced by the head).
- `--revision=Labels` restricts the check to one revision (the enum case name).

Tests: Feature `DefinitionCommandsTest` (`test_check_reports_a_relationship_to_a_resource_absent_from_a_revision`).

#### US 6.2 — `ResourceAssertions` at a revision · S

*As an application developer, I want to assert my resources at every revision in my own test suite.*

Acceptance criteria:
- `ResourceAssertions::assertValid(string $class, bool $strict = false, ?Revision $revision = null)`
  (`src/Testing/ResourceAssertions.php:26-51`).
- `ResourceAssertions::assertValidAtEveryRevision(string $class, bool $strict = false)`: every case of the enum used by
  the class, cross-resource diagnostic included.
- `ResourceAssertions::definitionAt(string $class, Revision $revision): ResourceDefinition` (for snapshot tests).

#### US 6.3 — Changelog of the revisions · M (optional, after 2.1.0 if needed)

*As an API owner, I want the changes of each revision listed from the code, so that the changelog of the API never
drifts.*

Acceptance criteria:
- `php artisan jsonapi:revisions` prints, per case (newest first): its `description()`, then the difference between
  the definitions at that case and at the previous one (types added or removed, fields added, removed, renamed, type
  changed).
- `--format=json` for the tools.
- Computed from the definitions only (no extra storage): a renaming is detected through the `was()` boundaries kept in
  the compiled definitions (to be designed in the story: `boundaries` may have to carry the name of each changed
  field).

---

### EV7 — Documentation and release

#### US 7.1 — Architecture · S

- New section of `docs/architecture.md`: "Revisions" — §1 of this plan in short, the central invariant, buckets,
  fast path.
- §3: additive public properties of the definition model are minor; the cases of `ValueType` stay major.
- §11: no new extension point (the contract is an interface implemented by an enum).
- The study `docs/rfc/versioned-resources.md` is marked "decided, see the plan".

#### US 7.2 — Guide · M

The reference of the skill (`reference.md`, checked by `tests/Feature/Skill/SkillTest.php`) names every new helper
and modifier (`was()`, `until()`, `since()`, `requestedRevision()`, `#[JsonApiSince]`, `#[JsonApiUntil]`); the suite fails
otherwise (spike M0).

README section and `laravel-json-api-docs` page:

1. Declaring the enum, setting the revision in a middleware.
2. Changing a field: the writing rule (`was(<the previous newest revision>)`), the reference example.
3. Removing, adding, reusing a name.
4. A new type: `#[JsonApiSince]`, `#[JsonApiUntil]`, a `was()` on every relationship, `jsonapi:check`.
5. Guards.
6. **Retiring a revision**: delete the oldest case, run `composer stan`, delete every reported line and every
   `#[JsonApiUntil]` resource, run `jsonapi:check`.
7. **Contract test in the application**: snapshot the definitions (or the generated OpenAPI) of each supported
   revision, compare on every change. The only net for `revision-value-gap` cases the warning misses.

#### US 7.3 — Release 2.1.0 · S

- `CHANGELOG.md` `[2.1.0]` "Added: versioned resources"; nothing in `UPGRADE.md` (no breaking change), unless
  `DefinitionSource` gains a method (see §6, Q4).
- Benchmark reference updated if the head moved within noise.

---

### EV8 — `laravel-openapi-generator` (separate repository)

Recorded here for the dependency only; the stories are written and delivered in that repository
(`src/Integrations/JsonApiV2/`).

| Story | Content |
| --- | --- |
| US 8.1 — one document per revision | Configuration `revisions: ApiRevision::class`; one generation per case, plus the head; `GenerationContext` carries the revision; `ResourceDefinitions::of()` reads `Definitions::for($class, $revision)` (`ResourceDefinitions.php:48`); samples rendered with the revision (`JsonApiResourceFormat.php:341`). |
| US 8.2 — resources absent from a revision | Components and routes of resources that do not exist at `R` (`Definitions::exists()`) are left out of the document of `R`. |
| US 8.3 — changelog | `info.description` of each document lists `description()` of the newer revisions (or `jsonapi:revisions --format=json`). |

No change for types: a resource keeps one type, so one component per class stays right.

---

## 5. Order and milestones

```
EV1 ── US1.1 ─┬─ US1.2 ── US1.3
              │
EV2 ──────────┴─ US2.1 ─ US2.2 ─ US2.3 ─ US2.4 ─ US2.5
                                                   │
EV3 ───────────────────────────── US3.1 ── US3.2 ──┼─ US3.3 ─ US3.4 ─ US3.5
                                                   │
EV4 ──────────────────────────────────── US4.1 ─ US4.2 ─ US4.3 ─ US4.4 ─ US4.6 ─ US4.5
EV5 ──────────────────────────────────── US5.1 ─ US5.2 ─ US5.3   (after US3.2)
EV6 ──────────────────────────────────── US6.1 ─ US6.2           (after US3.4, US4.4)
EV7 ──────────────────────────────────── US7.1 ─ US7.2 ─ US7.3   (last)
EV8 ── other repository, after US3.3
```

| Milestone | Content | Exit criterion |
| --- | --- | --- |
| **M0 — Spike** (throwaway branch, 1–2 days) | US 2.5 + a minimal `RevisedMembers` + rendering of the §4 scenario, without definitions | rename + fieldset, old include path, two old types for one user, Larastan reporting a deleted case in an attribute: all four confirmed |
| **M1 — Shape** | EV1, EV2, US 3.1–3.3 | `Definitions::for($class, $revision)` correct for the scenario |
| **M2 — Rendering** | EV4, EV5 | feature scenario green at every revision, query counts equal |
| **M3 — Safety** | US 3.4, 3.5, EV6 (6.1, 6.2) | every diagnostic of §US 3.4 and `revision-unavailable-resource` covered |
| **M4 — Release** | EV7 | 2.1.0 tagged; EV8 unblocked |

Estimate: EV1 3 d · EV2 7 d · EV3 10 d · EV4 11 d · EV5 3 d · EV6 4 d (+3 d for US 6.3) · EV7 4 d → about **42 days**
without US 6.3 and EV8.

### 5.1 Spike M0 — results

Branch `spike/versioned-resources` (commit `0dc96f2`, throwaway, never merged): `was()` / `until()` on descriptors,
`FieldHistory`, `RevisedMembers`, the revision in `JsonApiContext`, definitions and load plans per revision (one per
case, no buckets), wired into `DefinitionCompiler`, `Renderable` and `LoadPlanner`. Scenario:
`tests/Feature/Revisions/SpikeTest.php`, on the models of `tests/app`.

| Point | Result |
| --- | --- |
| Rename + sparse fieldset | ✅ `fields[users]=mail,label` renders the old names; `fields[users]=display_name` renders nothing for `Labels`. A field renamed without source (`email` → `mail`) reads the head attribute. |
| Old nested include path | ✅ `include=writer,comments.commenter` rendered and loaded in batch: **same number of queries** as the head `include=author,comments.user`. |
| One user, two old types | ✅ `writers:1` and `commenters:1` in `included`, linkage and `included` agree. **No change** to `RelationshipsStage`, `IncludedRegistry` or `renderType()`. |
| Larastan on a deleted case | ✅ `classConstant.notFound` reported in an attribute argument (`#[ProbeUntil(ApiRevision::Deleted)]`) and in code. |
| Definition = rendered shape | ✅ `Definitions::for($class, $revision)` gives the names rendered (`fields()`, relationships). |
| Existing suite | ✅ unchanged, except `SkillTest` (the skill reference must name the new helpers → US 7.2); phpat green. |

Findings carried into the stories:

1. **The head runs `RevisedMembers` too**, for a class with `until()` fields: they are written in the head hook and
   must be removed for head clients. The fast path is per class (`Definitions::isRevised()`), not per revision
   (US 3.2, US 4.1).
2. **`MergeValue` must be copied keeping its class**: rebuilding a plain `MergeValue` made the members of
   `mergeWhen()` visible in the definition (caught by an existing test) (US 3.1, US 4.1).
3. `readingFrom(string $source)` on `Value` / `Relation`, which sets the source only when the descriptor has none, is
   enough for §1.4: the name-only `was()`, the `value:` without source and the relation of a `was()` relation all
   read the head source (US 2.5).
4. The sizes of EV3 and EV4 hold: the spike's wiring is ~60 lines across the compiler, the bridges and the planner;
   the work left is the buckets, the diagnostics, structs, guards, helpers and the tooling.

---

## 6. Questions

| # | Question | Status |
| --- | --- | --- |
| Q1 | **D1**: inclusive bounds (`was(X)` = up to X) rather than "before X" | **Decided**: inclusive (§1.2). |
| Q2 | Request bodies of older clients (`JsonApiFormRequest`) and their `source.pointer` | **Decided**: out of 2.1; the application validates per revision. A later epic could invert the `was(name:)` of the attributes on `data.attributes`. |
| Q3 | Rendering a resource absent from the revision | **Decided**: exception (US 4.4). |
| Q4 | How the compiler reads `#[JsonApiSince]` / `#[JsonApiUntil]` without breaking `DefinitionSource` | **Decided**: by reflection in the compiler, no new interface method (US 2.4). |
| Q5 | Names of the class attributes | **Decided**: `#[JsonApiSince]` / `#[JsonApiUntil]`, consistent with `#[JsonApiType]`. |
| Q6 | Field conditions and the helpers `whenInFields()` / `whenInIncludes()` for older clients | **Decided**: in 2.1 (US 4.6). What reads the model (`whenHas()`, `when()`…) always uses the head; what reads the request (`whenInFields()`, `whenInIncludes()`, `whenIncluded()`) uses the revision. |
| Q7 | Changelog (US 6.3): diff of definitions enough to detect a rename? | **Later**, with US 6.3 (after 2.1.0). |

## 7. Risks

| Risk | Mitigation |
| --- | --- |
| A path of the rendering misses `RevisedMembers` (definition and document diverge) | Invariant test of US 3.1 on every fixture resource and every revision |
| A renamed field reads the old model attribute (§1.4) | `FieldHistory` pins the head source; dedicated test in US 2.5 and 4.1 |
| `revision-value-gap` cases the warning misses | Application contract test (US 7.2) |
| Larastan does not report a deleted case used in an attribute argument | Checked in the spike (M0); otherwise `jsonapi:check` reports an unknown case when reading the attributes |
| Size of the compiled cache | Buckets shared between revisions; size measured in US 4.5 |
| Head regression | US 4.5 gate on `composer benchmark` |
| A condition receives the old name and reads the wrong model attribute (`whenHas()`) | US 4.6: rules receive the head source |
