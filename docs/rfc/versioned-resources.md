# RFC — Versioned resources

> **Decided.** The field-level syntax `was()` (§3.7) is retained, with frozen resource types and no attribute ↔
> relationship change. The decided model and the implementation plan are in
> [`versioned-resources-epics.md`](versioned-resources-epics.md), which supersedes the conflicting parts of this study
> (families A, B, C, G; `type:` of `was()`; any type depending on the revision or on the include path). The study is
> kept as the record of the options explored.
>
> Status: **decided, see the plan** (implemented in 2.1). Target: a minor, non-breaking 2.x release.
> Scope: how a resource declares the shapes it had in earlier revisions of an API, so that the package renders them
> and describes them (definitions, validation, OpenAPI) from the code of the latest shape only.

## 0. Summary

An application pins each client to a dated **revision** (`Api-Version: 2026-11-15`, Stripe-style). The code only holds
the latest shape of the contract, the **head**; earlier shapes are produced from **declarations**.

The study leads to one central idea, independent of the syntax chosen:

> **A revision is a transformation of the members a hook returns (descriptors), not of the rendered JSON.**
> The same transformation runs on the probe when the definition is compiled and on the resource when it is rendered.
> Definition and document agree by construction, and everything downstream — sparse fieldsets, include paths,
> eager loading, `included`, validation, OpenAPI — works on the transformed members as if they were the head.

Recommended syntax (§4): a `toRevisions()` hook on the resource, returning one fluent `Revision` per date, written as a
changelog in the past tense; the parent of a relationship declares the type its related resources had; a
`revisionBefore()` guard for the changes of meaning that have no shape.

```php
protected function toRevisions(Request $request): iterable
{
    return [
        Revision::of(ApiRevision::UsersReplaceTeachers)
            ->renamed(from: 'name', to: 'display_name')
            ->removed('full_name', was: $this->string(fn() => "{$this->first_name} {$this->last_name}"))
            ->changed('balance', was: $this->integer('balance_cents'))
            ->renamedType(from: 'teachers'),

        Revision::of(ApiRevision::Labels)
            ->renamed(from: 'label', to: 'name'),
    ];
}
```

---

## 1. Internal mechanisms involved

### 1.1 Context — where the revision can travel

| What | Where | Consequence |
| --- | --- | --- |
| `JsonApiContext` is `final readonly`, built by `fromRequest()` from the query string only | `src/Context/JsonApiContext.php:17-43` | A `?string $revision` property (last constructor parameter, default `null`) is non-breaking |
| `withPath()` rebuilds the context for the resources reached through a relationship | `src/Context/JsonApiContext.php:63-73` | The natural place to carry the type a parent gives to its related resources (case 7) |
| The container binds one context per request in a `WeakMap` | `src/Laravel/JsonApiServiceProvider.php:99-110` | **Never resolved by `src/`**: see next line |
| Resources build their own context: `JsonApiContext::fromRequest($request, Definitions::configuration())` | `src/Resources/Concerns/ToResponse.php:65-75` | The revision must be readable **from the `Request`** (a request attribute), not from the container binding |
| `renderingContext` is kept on the resource for its helpers | `src/Resources/Concerns/Renderable.php:31`, `ConditionallyLoadsAttributes.php:56,78` | A `revisionBefore()` helper reads it the same way `whenInFields()` does |

### 1.2 Type and identity

| What | Where |
| --- | --- |
| Every resource object starts from `renderType($context)` + `renderIdentifier($context)` | `src/Rendering/ResourceObjectRenderer.php:69-72` |
| `renderType()` calls the user hook `toType($request)` | `src/Resources/Concerns/Renderable.php:36-41`, `Identifier.php:35-38` |
| Primary resources are identified with the root context | `src/Rendering/DocumentBuilder.php:58-60` |
| `included` is keyed by `type:id`, each resource rendered once per path and merged | `src/Rendering/IncludedRegistry.php:43-67` |
| **Identifiers of a relationship are typed with the context of the parent**, not of the path they are included through | `src/Rendering/Stages/RelationshipsStage.php:76-80` (vs. `:55` for `included`) |
| `ResourceObject::$type` is `readonly`, there is no `withType()` | `src/Rendering/ResourceObject.php:15,24` |

Consequences:

- A type that depends on the context (revision, and for case 7 on the relationship leading to the resource) is
  consistent everywhere **if** it is resolved in `renderType()` and **if** `RelationshipsStage:77` identifies with
  `$context->withPath($name)` like `:55` does. That one-line change is the only fix the rendering engine needs.
- Since `IncludedRegistry` keys by `type:id`, the same user reached as a `teachers` and as an `authors` yields two
  distinct entries of `included` — exactly what the old revision expects (case 7). No `withType()` is needed.

### 1.3 Attributes, sparse fieldsets and names

| What | Where |
| --- | --- |
| `AttributesStage` resolves `renderAttributes()` with the type of the object | `src/Rendering/Stages/AttributesStage.php:26-31` |
| `ValueResolver` names each member by its **key** (or the retriever of an unnamed descriptor), then filters on `fields[type]` with that name, then resolves | `src/Rendering/ValueResolver.php:66-77` |
| A descriptor without retriever reads the model attribute named after **the member name** it is resolved under | `src/Rendering/ValueResolver.php:98`, `src/Descriptors/Describer.php:164-175` |
| Relationships are filtered on `fields[type]` and included by their key | `src/Rendering/Stages/RelationshipsStage.php:39-58` |

Consequences:

- A rename must happen **on the members, before `ValueResolver`** — a `Stage` appended through `rendering.stages`
  comes too late (the fieldset was already applied with head names). Renaming the keys of the iterable returned by
  `renderAttributes()` / `renderRelationships()` is enough: filtering, `includes($name)` and `withPath($name)` then
  see the old names, at every depth of a nested include.
- **Trap:** `'display_name' => $this->string()` reads `$model->display_name` because its key is `display_name`. Once
  renamed to `name`, it would read `$model->name`. A rename must pin the head name as the retriever of a descriptor
  that has none (`'name' => $this->string('display_name')`), and conditions reading `$attribute` (`whenHas()`) must
  receive the head name too.

### 1.4 Definitions and compilation

| What | Where |
| --- | --- |
| `Definitions::for($class)`: memory cache by class, compiled file read once | `src/Compilation/Definitions.php:76-84` |
| `DefinitionCompiler::run()` runs every hook on a probe with a neutral request | `src/Compilation/DefinitionCompiler.php:171-214` |
| `DefinitionSource`: the `@internal` bridges the compiler calls | `src/Definition/DefinitionSource.php:16-108` |
| `ResourceDefinition`: `final readonly`, `var_export`-able, public contract | `src/Definition/ResourceDefinition.php:15-38` |
| `fields()` lists what `fields[type]` accepts | `src/Definition/ResourceDefinition.php:130-139` |
| The renderer reads the **head** definition of the class to resolve relationships | `src/Resources/Concerns/Renderable.php:86` |

### 1.5 Consumers of the definitions

| Consumer | Where | Reads |
| --- | --- | --- |
| `LoadPlanner` | `src/Loading/LoadPlanner.php:39,48,52,88,93` | relationships by name, `fields[$definition->type]`, `includes($name)` |
| `Includes` rule | `src/Http/Rules/Includes.php:93,103` | relationships by name, recursively |
| `Fields` rule | `src/Http/Rules/Fields.php:108-127` | `$definition->type` → `fields()`, recursively through `related` |
| `JsonApiFormRequest` | `src/Http/JsonApiFormRequest.php:79-83` | builds both rules from `$resource` |
| `ValidationErrorRenderer` | `src/Http/Errors/ValidationErrorRenderer.php:57-71` | the validator keys → `source.parameter` / `source.pointer` |
| `ResourceAssertions::assertValid()` | `src/Testing/ResourceAssertions.php:26-51` | diagnostics of `Definitions::compile()` |
| `laravel-openapi-generator` | `src/Integrations/JsonApiV2/ResourceDefinitions.php:48` | `Definitions::for()`; type in `JsonApiResourceFormat.php:74,210-216,287`; fields in `FieldsRuleMapper.php:77-81` |

All of them go through `Definitions::for()`. A **revision-aware `Definitions::for($class, $revision)`** therefore
switches them all at once; each one only has to pass the revision on.

---

## 2. Shared semantics (whatever the syntax)

### 2.1 Vocabulary

- **Revision**: an opaque string ordered by a comparator the application provides (default: lexicographic, right for
  ISO dates). A backed enum of the application is accepted wherever a revision is (`string|BackedEnum`), so that
  revisions are symbols PHPStan checks and an IDE finds.
- **Requested revision**: the one of the request, `null` for the head.
- **Declared revision**: a revision in which a resource **changed**. A declaration describes the change, *as a
  changelog does*: "in 2026-12-01, `name` was renamed `display_name`".

### 2.2 "Before" — what a declared revision means

A change declared in revision `R` is **undone** for every requested revision **strictly older than `R`**. A client
pinned on `R` or later sees the head of that change. A requested revision between two declared ones sees the shape of
the next declared one: there is no need to declare every revision on every resource.

### 2.3 Composition (case 14)

The changes of a resource are undone **from the newest to the oldest**. Each declaration is written against the shape
right after it, i.e. with the names of its own revision:

```
head:          display_name
2026-12-01:    renamed(from: 'name',  to: 'display_name')   → clients < 2026-12-01 see `name`
2026-06-01:    renamed(from: 'label', to: 'name')           → clients < 2026-06-01 see `label`
```

A client on `2026-03-01` gets `display_name` → `name` → `label`. This is the model of Stripe and of Cadwyn (FastAPI):
the order is the order of the revisions, not the order of the lines.

### 2.4 Buckets — what is compiled and cached

For one resource class, the requested revisions fall into `n + 1` **buckets**, `n` being the number of revisions it
declares: the head, then one bucket per declared revision ("older than `R`"). Two requested revisions in the same
bucket get the same definition.

- `Definitions::for($class, $revision)` maps the revision to its bucket (`n` comparisons at most, usually one), then
  caches by `class@bucket`. A class without revisions has one bucket: the head, cost unchanged.
- `jsonapi:cache` writes every bucket of every class. Size: `Σ (1 + revisions declared by the class)` definitions,
  instead of one per class.
- Each class maps the requested revision to **its own** bucket: a parent and its related resources do not need to
  declare the same revisions.

### 2.5 Where the revision comes from

The package never reads a header. The application decides the revision (header, token, account setting) and puts
it on the request, typically from a middleware:

```php
JsonApi::useRevision($request, $revisions->resolve($request->header('Api-Version')));
// sets $request->attributes['jsonapi.revision']; JsonApiContext::fromRequest() reads it
```

The request attribute travels with the request (Octane safe, no global state), is read by `fromRequest()` without the
container (§1.1), and by the validation rules of a `JsonApiFormRequest`.

---

## 3. Syntax families

The same resources are used everywhere:

- `UserResource` (`users`), whose head is `display_name`, `balance {amount, currency}`, `born_at` (date-time), and
  relationship `organization`;
- `LessonSessionResource` (`lesson-sessions`), whose head has the relationship `instructor` to `UserResource`.

History to express:

| Revision | Change |
| --- | --- |
| `2026-12-01` | `users` replaces `teachers` / `authors` / `attendees` (case 6 and 7); `name` → `display_name` (case 1); `full_name` removed (case 2); `balance` integer cents → struct (case 3); `lesson-sessions.teacher` → `instructor` (case 8) |
| `2026-06-01` | `label` → `name` (case 14) |

### Family A — declaration on the resource (`revisions()` / `toRevisions()`)

A hook returns one builder per revision. The prompt suggested a **static** `revisions()`; it cannot work with the
descriptors as they are: `$this->string(fn() => $this->first_name)` is a protected helper, and its retriever closure
is bound to the resource instance (`Describer.php:174`: `$retriever()` is called without the model). A static method
has no `$this`. Hence an **instance hook**, run on the probe like the other hooks — and on the resource when, and only
when, an old revision is rendered.

```php
final class UserResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'display_name' => $this->string(),
            'balance' => $this->struct(fn() => [
                'amount' => $this->integer('balance_cents'),
                'currency' => $this->string('currency'),
            ]),
            'born_at' => $this->datetime(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return ['organization' => $this->one(OrganizationResource::class)];
    }

    protected function toRevisions(Request $request): iterable
    {
        return [
            Revision::of('2026-12-01', 'Users replace teachers, authors and attendees')
                ->renamedType(from: 'teachers')                                              // case 6
                ->renamed(from: 'name', to: 'display_name')                                  // case 1
                ->removed('full_name', was: $this->string(                                   // case 2
                    fn() => "{$this->first_name} {$this->last_name}",
                ))
                ->changed('balance', was: $this->integer('balance_cents'))                   // case 3
                ->changed('born_at', was: $this->date()),                                    // case 3

            Revision::of('2026-06-01', 'Labels become names')
                ->renamed(from: 'label', to: 'name'),                                        // case 14
        ];
    }
}

final class LessonSessionResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return ['instructor' => $this->one(UserResource::class)];
    }

    protected function toRevisions(Request $request): iterable
    {
        return [
            Revision::of('2026-12-01', 'Users replace teachers, authors and attendees')
                ->renamed(from: 'teacher', to: 'instructor')                                 // case 8
                ->relatedType('teacher', was: 'teachers'),                                   // case 7
        ];
    }
}
```

- Case 8: `include=teacher.organization` and `fields[lesson-sessions]=teacher` of an old client work because each
  level renames its own members (§1.3). `relatedType()` names the relationship as it is *in that revision*.
- Case 7: the parent declares the type it gives to its related resources (§3.6 discusses who declares).
- `was:` takes a descriptor: the old value is typed in the definition, hence in the documentation.

Limits:

- Member names are strings (as in `toAttributes()`): a rename of an unknown member can only be caught at compilation,
  by a diagnostic (`revision-unknown-member`), not by PHPStan.
- Dates repeated across resources: mitigated by an application enum (`Revision::of(ApiRevision::UsersMerge)`).
- A change that spans several resources (case 7) is spread across the files of those resources.

### Family B — PHP attributes on the class

```php
#[JsonApiType('users')]
#[Revision('2026-12-01', 'Users replace teachers, authors and attendees')]
#[RenamedType('2026-12-01', from: 'teachers')]
#[Renamed('2026-12-01', from: 'name', to: 'display_name')]
#[Removed('2026-12-01', 'full_name', was: 'legacyFullName')]       // a method name: a magic string
#[Changed('2026-12-01', 'balance', was: 'legacyBalance')]
#[Revision('2026-06-01', 'Labels become names')]
#[Renamed('2026-06-01', from: 'label', to: 'name')]
final class UserResource extends JsonApiResource
{
    // … head hooks …

    protected function legacyFullName(): ValueString
    {
        return $this->string(fn() => "{$this->first_name} {$this->last_name}");
    }

    protected function legacyBalance(): ValueInteger
    {
        return $this->integer('balance_cents');
    }
}

#[Renamed('2026-12-01', from: 'teacher', to: 'instructor')]
#[RelatedType('2026-12-01', 'teacher', was: 'teachers')]
final class LessonSessionResource extends JsonApiResource { /* … */ }
```

Limits:

- **Values cannot be written in an attribute.** PHP 8.3/8.4 accept no closure in a constant expression; PHP 8.5 accepts
  `static` closures only, without `$this`, so without the descriptor helpers either. Every restored value becomes a
  method referenced by name: a magic string PHPStan does not check, against the static analysis criterion.
- The revision is repeated on every line; grouping per revision only exists by convention.
- Excellent for pure renames (cases 1, 6, 8, 14 read well at a glance), poor for everything carrying a value (2, 3, 4,
  5, 9, 10, 11).
- Reading attributes is cheap and needs no probe, but the compiler still has to call the methods on the probe for the
  values: two mechanisms instead of one.

### Family C — change classes grouped by revision

Inspired by Cadwyn's `VersionChange`: one class per change of the API, possibly spanning several resources; the
application lists them per revision.

```php
// config/jsonapi.php
'revisions' => [
    'comparator' => DateRevisions::class,
    'changes' => [
        '2026-12-01' => [UsersReplaceTeachers::class],
        '2026-06-01' => [LabelsBecomeNames::class],
    ],
],

final class UsersReplaceTeachers implements ResourceChange
{
    public function description(): string
    {
        return 'Users replace teachers, authors and attendees';
    }

    public function changes(Changes $changes): void
    {
        $changes->on(UserResource::class)
            ->renamedType(from: 'teachers')
            ->renamed(from: 'name', to: 'display_name')
            /** the closure is bound to the resource: `$this` is a UserResource */
            ->removed('full_name', was: fn() => $this->string(fn() => "{$this->first_name} {$this->last_name}"))
            ->changed('balance', was: fn() => $this->integer('balance_cents'));

        $changes->on(LessonSessionResource::class)
            ->renamed(from: 'teacher', to: 'instructor')
            ->relatedType('teacher', was: 'teachers');

        $changes->on(AnnouncementResource::class)
            ->relatedType('author', was: 'authors');
    }
}

final class LabelsBecomeNames implements ResourceChange
{
    public function description(): string
    {
        return 'Labels become names';
    }

    public function changes(Changes $changes): void
    {
        $changes->on(UserResource::class)->renamed(from: 'label', to: 'name');
    }
}
```

Limits:

- The descriptor helpers are `protected`: building `was:` needs a closure bound to the resource
  (`Closure::bind($closure, $resource, $resource::class)`). PHPStan types `$this` in it with `@param-closure-this`
  on a generic `Changes<UserResource>`: possible, but the most demanding PHPDoc of all the families.
- The package must know a global registry (configuration), and compiling one class must find "the changes that target
  it" through an index built at boot: a departure from "a class compiles alone".
- The head stays perfectly clean, but reading a resource no longer tells that it has history (`jsonapi:check` or the
  definition can list it).
- Best for the changelog and for retiring a revision: delete the class and its configuration line. Best for case 7:
  the whole story of the merge is in one file.

### Family D — history inline on each descriptor

```php
final class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'users'; // case 6 has no descriptor: needs B's #[RenamedType] or A's renamedType() → hybrid
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'display_name' => $this->string()
                ->renamedFrom('name', before: '2026-12-01')
                ->renamedFrom('label', before: '2026-06-01'),          // case 14: order of the calls, or of the dates?
            'full_name' => $this->string(fn() => "{$this->first_name} {$this->last_name}")
                ->removedSince('2026-12-01'),                          // case 2: the removed member stays in the head
            'balance' => $this->struct(fn() => [
                'amount' => $this->integer('balance_cents'),
                'currency' => $this->string('currency'),
            ])->before('2026-12-01', $this->integer('balance_cents')),  // case 3
            'born_at' => $this->datetime()->before('2026-12-01', $this->date()),
        ];
    }
}

final class LessonSessionResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'instructor' => $this->one(UserResource::class)
                ->renamedFrom('teacher', before: '2026-12-01')          // case 8
                ->typedAs('teachers', before: '2026-12-01'),            // case 7: very local, very readable
        ];
    }
}
```

Limits:

- **The head is no longer the head**: removed members stay in `toAttributes()`, each descriptor carries its past. After
  three revisions, `toAttributes()` is a history file.
- No grouping per revision: the description of a revision has no place, the changelog cannot be extracted.
- The type, the resource meta and the document meta have no descriptor to carry their history: D always needs another
  family next to it.
- Descriptors are immutable clones carrying more state: a (small) cost on the head too, against "zero overhead".
- Retiring a revision: grep a date in every `toAttributes()`.
- Locally, `typedAs()` on the relationship of the parent is the most readable expression of case 7.

### Family F — imperative guards (no new syntax)

Listed as the baseline, because case 12 needs it anyway: the hooks read the revision and branch through the
conditions the package already has.

```php
protected function toAttributes(Request $request): iterable
{
    return [
        'display_name' => $this->string()->when(fn() => !$this->revisionBefore('2026-12-01')),
        'name' => $this->string('display_name')->when(fn() => $this->revisionBefore('2026-12-01')),
        // …
    ];
}
```

If the compiler runs the hooks once per bucket with the revision of that bucket, even this is described correctly.
But every old shape lives in the head, every member doubles, and nothing is declarative (no changelog, no retirement
by deletion). It is the **escape hatch**, not the syntax.

### Family E — hybrid

The families combine along two axes: **where the history lives** (with the resource: A, B, D; outside: C) and **how
fine-grained** (per revision: A, C; per member: B, D). Two hybrids are worth keeping:

- **E1 = A + local relationship type**: A everywhere; case 7 is declared by the parent in its own `toRevisions()`
  (`relatedType()`), i.e. A applied to the relationship that emits the identifier. This is what §3 A already shows.
- **E2 = A + C as a grouping layer**: A is the mechanism; a `ResourceChange` class (C) is only sugar returning
  revisions for several resources. It can be added later without changing A.

### 3.6 Case 7 — who declares the type of a related resource?

| Option | For | Against |
| --- | --- | --- |
| **The target** (`UserResource`: `renamedType(from: 'teachers', via: [LessonSessionResource::class, 'teacher'])`) | The whole merge is in one place; `class-string` references | The target knows its parents (inverted coupling); compiling a parent needs an index of who targets it; `via` names a relationship of another class, in which revision's naming? |
| **The parent** (`LessonSessionResource`: `relatedType('teacher', was: 'teachers')`) | The parent emits the identifier and walks the relationship (renderer, `LoadPlanner`, `Fields`): the information is where it is read; no index; each class compiles alone | The story of the merge is spread over the parents |
| Both | — | Two sources of truth that can disagree |

**Recommendation: the parent declares.** Grouping the story of the merge in one place is a matter of the changelog
(E2, or a shared application enum case with its description), not of the mechanism.

Primary data (an old `GET /teachers/1`) is a route of the old revision; the controller renders
`UserResource::make($user)->as('teachers')`, or the old route keeps a thin `TeacherResource extends UserResource` with
its type. Left to the application (§7).

### 3.7 Field-level revisions, explored further (D′)

Family D (§3) was written with one verb per kind of change (`renamedFrom()`, `before()`, `removedSince()`). Pushed
further, the field-level approach has a better form, and it covers more than it first seemed.

#### Key difference with A: states, not deltas

A declares **what a revision changed** (a delta, composed newest to oldest, §2.3). On a field, it reads better to
declare **what the field was before a revision** (a state):

> `display_name` — before 2026-12-01 it was `name`; before 2026-06-01 it was `label`.

Rule: for a requested revision, the **entry with the oldest revision still newer than the request** applies; what it
does not state (name, value, type) is taken from the next newer entry, then from the head. The order of the calls does
not matter, the dates decide.

#### D′1 — one verb, `was()`

```php
'display_name' => $this->string()
    ->was(ApiRevision::UsersReplaceTeachers, name: 'name')                    // 1
    ->was(ApiRevision::Labels, name: 'label'),                                // 14

'balance' => $this->struct(fn() => [
    'amount' => $this->integer('balance_cents'),
    'currency' => $this->string('currency'),
])->was(ApiRevision::UsersReplaceTeachers, value: $this->integer('balance_cents')),   // 3

'born_at' => $this->datetime()->was(ApiRevision::UsersReplaceTeachers, value: $this->date()),       // 3
'nickname' => $this->string()->nullable()
    ->was(ApiRevision::UsersReplaceTeachers, value: $this->string(fn() => $this->nickname ?? '')),  // 4
'status' => $this->enum()->of(UserStatus::class)
    ->was(ApiRevision::UsersReplaceTeachers, value: $this->enum(
        fn() => LegacyUserStatus::fromStatus($this->status),
    )->of(LegacyUserStatus::class)),                                                                // 5
'tag_names' => $this->arrayOf($this->string(), fn() => $this->tags->pluck('name'))
    ->since(ApiRevision::UsersReplaceTeachers),                                                     // 15
```

`was(revision, name:, value:, type:)` has three named arguments, and all of them are optional:

- `name:` — the old name of the field;
- `value:` — the old descriptor (typed);
- `type:` — on a relationship only: the type its related resources had (case 7).

`since(revision)` hides a field from the clients older than `revision`.

#### Relationships (cases 7, 8, 9) — local and very readable

```php
// LessonSessionResource
'instructor' => $this->one(UserResource::class)
    ->was(ApiRevision::UsersReplaceTeachers, name: 'teacher', type: 'teachers'),          // 7 + 8

'attendees' => $this->many(UserResource::class)
    ->was(ApiRevision::UsersReplaceTeachers, type: 'attendees'),                          // 7

// CourseResource
'teachers' => $this->many(UserResource::class)
    ->was(ApiRevision::UsersReplaceTeachers, name: 'teacher', type: 'teachers', value: $this->one(
        UserResource::class,
        fn() => $this->resource->teachers->first(),
    )),                                                                                   // 9
```

#### Removed fields (case 2): a labelled block instead of a scattered member

The weak point of D was that a removed field stays in the head. A block in the style of `applyWhen()` keeps the
removed fields together, at the end of the hook, clearly labelled as history:

```php
protected function toAttributes(Request $request): iterable
{
    return [
        'display_name' => $this->string()->was(ApiRevision::UsersReplaceTeachers, name: 'name'),
        // …

        ...$this->removedIn(ApiRevision::UsersReplaceTeachers, [
            'full_name' => $this->string(fn() => "{$this->first_name} {$this->last_name}"),
        ]),
    ];
}
```

#### Type (case 6) and meta (case 11)

- The type has no descriptor; it goes on the attribute that already declares it. Since PHP 8.2, `->value` of an enum
  case is allowed in a constant expression:

  ```php
  #[JsonApiType('users', was: [ApiRevision::UsersReplaceTeachers->value => 'teachers'])]
  ```

- Resource meta and document meta **are** descriptors (`toResourceMeta()`, `toDocumentMeta()`): `was()` and
  `removedIn()` apply to them unchanged. The first draft of D said they had no place: that is wrong for D′.

#### Case 10 — relationship ↔ attribute

`'tag_names' => $this->arrayOf(…)->was(X, name: 'tags', value: $this->many(TagResource::class))` would move a member
from `attributes` to `relationships` depending on the revision. This is doable, but surprising when reading
`toAttributes()`. It is clearer to write `since()` on `tag_names` plus `removedIn()` in `toRelationships()`.

#### D′2 — a timeline per field (variant)

```php
'balance' => $this->revised(
    $this->struct(fn() => [/* … */]),
    before: [
        ApiRevision::UsersReplaceTeachers->value => $this->integer('balance_cents'),
        ApiRevision::Labels->value => $this->integer('balance_cents')->nullable(),
    ],
),
```

It reads like a table, but it puts the head inside a wrapper, it needs a value object for renames
(`Field::named('name')`), and enum cases can only be keys through `->value`. D′1 is better.

#### Assessment of D′

| | |
| --- | --- |
| ✅ | Every change sits **next to the field it concerns**: when reading a field, you see its history. |
| ✅ | Nested struct members get `was()` naturally (open question 4 disappears). |
| ✅ | **Retiring is guided by PHPStan.** Delete `case Labels` from the enum, and `composer stan` lists every line still referencing it. |
| ✅ | No composition to reason about: each entry is a state. Cases 9, 10, 15 read well. |
| ✅ | The changelog can still be extracted: each entry is structured (revision, field, old name/kind). The revision description comes from the enum, through a small interface the package defines, e.g. `DescribesRevision::description()`. |
| 🟡 | The head is still readable (the history is a suffix of the line, the removed fields sit in a labelled block), but after 3–4 revisions, a heavily changed field becomes long. |
| 🟡 | A cross-resource change (case 7) is scattered across the relationships of every parent, as with A. |
| 🟡 | The type history lives in an attribute, a second place to look at. |
| ❌ | No global view of "what changed in 2026-12-01" when reading the code; only the generated changelog gives it. |

Internally, D′ is the same mechanism as A: per bucket, `RevisedMembers` resolves each member to its effective
(name, descriptor, type), on the probe and at rendering. Only where the declarations are read changes. A descriptor
carries a `history` list: this costs a little memory on the head and nothing at resolution time when there is no
revision.

### 3.8 Revisions by inheritance (G)

An old revision **is a resource**: a class that extends the head and overrides its hooks. The package only has to
**pick the class** that matches the requested revision, wherever a resource class is used. The prerequisite is that
head resources are not `final` (this is an application convention; the `final` rule applies to the package's own
classes).

#### Registration

The head lists its revisions, as `class-string`s, checked by PHPStan:

```php
#[JsonApiType('users')]
#[JsonApiRevisions(
    Revisions\R20261201\UserResource::class,
    Revisions\R20260601\UserResource::class,
)]
class UserResource extends JsonApiResource { /* head, unchanged */ }
```

Each revision class carries its revision and its description:

```php
#[Revision(ApiRevision::UsersReplaceTeachers, 'Users replace teachers, authors and attendees')]
```

Why the head lists them, rather than each subclass declaring `#[RevisionOf(UserResource::class)]`: the package must
not scan the application to compile one class. "A class compiles alone" stays true.

#### G1 — delta by inheritance: each revision overrides the next newer one

```php
namespace App\Http\Resources\Revisions\R20261201;

#[JsonApiType('teachers')]                                                        // 6
#[Revision(ApiRevision::UsersReplaceTeachers, 'Users replace teachers, authors and attendees')]
class UserResource extends \App\Http\Resources\UserResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            ...Arr::except([...parent::toAttributes($request)], ['display_name', 'balance', 'born_at']),
            'name' => $this->string('display_name'),                              // 1: the retriever is explicit
            'full_name' => $this->string(fn() => "{$this->first_name} {$this->last_name}"),   // 2
            'balance' => $this->integer('balance_cents'),                         // 3
            'born_at' => $this->date(),                                           // 3
        ];
    }
}

namespace App\Http\Resources\Revisions\R20260601;

#[JsonApiType('teachers')]
#[Revision(ApiRevision::Labels, 'Labels become names')]
class UserResource extends \App\Http\Resources\Revisions\R20261201\UserResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            ...Arr::except([...parent::toAttributes($request)], ['name']),
            'label' => $this->string('display_name'),                             // 14
        ];
    }
}

namespace App\Http\Resources\Revisions\R20261201;

#[Revision(ApiRevision::UsersReplaceTeachers)]
class LessonSessionResource extends \App\Http\Resources\LessonSessionResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            ...Arr::except([...parent::toRelationships($request)], ['instructor']),
            'teacher' => $this->one(UserResource::class, 'instructor'),           // 8: same relation, old name
        ];
    }
}
```

- **Composition (case 14) is the inheritance chain.** `R20260601` extends `R20261201`, which extends the head. Each
  class only states its own delta, and PHP already composes it.
- **Case 12 needs no guard:** the old revision overrides the relationship and keeps the old query.
- **Case 15:** an old revision removes the addition with `Arr::except()`, if exact documentation is wanted.
- **Case 11/13:** `toResourceMeta()`, `toDocumentMeta()` are overridden the same way; a `UserCollection` gets its own
  revision classes.
- **Retiring the oldest revision** means deleting the `R20260601/` folder and removing its line from
  `#[JsonApiRevisions]`. Nothing extends the oldest class, so nothing else moves.
- **Adding a revision** costs more than it seems. The current head becomes a new class `R20270301\UserResource`, and
  `R20261201\UserResource` must now `extends` it instead of the head: one line to change per resource, easy to forget.
  The package can check it (§3.8, "Internal changes": diagnostic `revision-chain`).

#### Case 7 with inheritance: thin role classes… and their limit

A first idea is very natural: in the old revision, `teachers`, `authors` and `attendees` were distinct resources, so
they become classes again.

```php
namespace App\Http\Resources\Revisions\R20261201;

#[JsonApiType('teachers')] class TeacherResource extends UserResource {}
#[JsonApiType('authors')]  class AuthorResource extends UserResource {}

// LessonSessionResource (R20261201)
'teacher' => $this->one(TeacherResource::class, 'instructor'),
```

The `Fields` rule, `LoadPlanner`, `included` and the OpenAPI generator (one component per class) work **as they are**:
no notion of a type given by a relationship.

**But single inheritance does not compose two axes** (revision × role). For clients older than 2026-06-01, a teacher
must render `label`, so it needs a `R20260601\TeacherResource`, which must extend both `R20260601\UserResource` (for
`label`) and the role. With *k* roles and *n* revisions, that is *k × n* classes, or traits. The way out is the same
mechanism as in A: the parent gives the type to the relationship,
`$this->one(UserResource::class, 'instructor')->as('teachers')`. `UserResource` is then substituted per revision as
usual. So inheritance **also** needs the "type given by the relationship" from §1.2 / §6, unless the roles never
change again after the merge.

#### G0 — snapshot: the old revision is a frozen copy

The variant without inheritance: `R20261201\UserResource extends JsonApiResource` and restates the whole old shape.

| | G0 snapshot | G1 delta |
| --- | --- | --- |
| Reading an old revision | ✅ the whole old shape in one file | 🟡 the shape is spread along the chain (`parent::` up to the head) |
| A head fix (a closure, a condition) | ❌ not propagated: duplicated in every snapshot | ✅ inherited |
| A head addition | ✅ never leaks: the old revision is exact | 🟡 leaks (non-breaking), unless removed |
| Adding a revision | ✅ copy the head before changing it | 🟡 re-point the `extends` of the previous newest |
| Volume of code | ❌ one full resource per revision and per changed resource | ✅ only the deltas |

G0 is the most faithful option (an old client gets exactly what it was coded against), but the most expensive to
maintain. G1 is the reasonable default; a team can switch to G0 for one resource that changed too much.

#### G2 — inheritance + vocabulary (variant)

`Arr::except` plus re-adding is plain PHP, but the intent ("rename") is only visible from the explicit retriever. A
helper can give the intent back without a new mechanism:

```php
protected function toAttributes(Request $request): iterable
{
    return $this->revise(parent::toAttributes($request))
        ->renamed(from: 'name', to: 'display_name')
        ->removed('full_name', was: $this->string(fn() => "{$this->first_name} {$this->last_name}"))
        ->changed('balance', was: $this->integer('balance_cents'));
}
```

This is A's vocabulary placed in a subclass. It brings back A's structured changelog, but makes two concepts to learn
instead of one.

#### Internal changes for G — a different mechanism from A/D′

| Where | Change |
| --- | --- |
| `Definable::declaredType()` (`:72`) | `#[JsonApiType]` is read on `static::class` only (`#[JsonApiModel]` walks up the parents, `:93`). A revision class without its own attribute would get the type of the naming convention (`user-resource`). It must be inherited **for revision classes only**: walking up the parents for every subclass would change the type of the existing subclasses of an application (breaking change). |
| new `Compilation\Revisions` | `classFor(string $class, ?string $revision): string`, the head → bucket class map, read from `#[JsonApiRevisions]`/`#[Revision]`, cached like `declaredType()`. Diagnostics: a revision class that does not extend the head or its chain (`revision-chain`), revisions out of order. |
| `Definitions::for($class, $revision)` | `for(Revisions::classFor($class, $revision))`: the definitions themselves are unchanged. **No change to the definition model**, apart from an optional `RevisionDefinition` (description) for the changelog. |
| `Renderable` (every `render*()` bridge) | **The single substitution point at rendering.** A head instance delegates to an instance of the bucket class built on the same model (`$this->revised($context)`, kept for the rendering). The primary resource (`UserResource::make()` in a controller), resources created by `Relationship::resolve()` (`:400`, `:454`), morphs and collections all go through these bridges. Without that single point, every instantiation would have to be patched: the "gaps" the post-processing approach suffers from. |
| `LoadPlanner::plan()`, `JsonApiResource::loadRelations()`, `JsonApiCollection::renderableResources()` | plan the bucket class (`Revisions::classFor()`), not `static::class` / `$resource::class`. |
| `Rules\Includes`, `Rules\Fields` | `Definitions::for(…, $revision)`, like A. |
| `RelationshipsStage` | unchanged for G0/G1 with role classes; the fix of `:77` (§1.2) is needed as soon as `->as()` is used. |

#### Assessment of G

| | |
| --- | --- |
| ✅ | **No new vocabulary**: an old revision is a resource like any other. The package docs, `jsonapi:check` and `assertValid()` apply as they are. |
| ✅ | **Best static analysis**: real methods, `class-string`s, overrides checked by PHPStan. |
| ✅ | **The head is untouched** (one attribute listing the revisions). |
| ✅ | **Retiring = deleting a folder.** The changelog per revision is the list of classes in that folder, each with its description. |
| ✅ | Lightest impact on the definition model and on the OpenAPI generator: each revision is a set of classes. Components named after the class can collide (two `UserResource`s), but only across documents, never within one revision. |
| 🟡 | The rename trap (§1.3) is visible but not prevented: `'name' => $this->string()` without `'display_name'` reads the wrong attribute, silently. A diagnostic can catch some of these cases (an old field reading an attribute the probe never saw). |
| 🟡 | One class per changed resource and per revision: the 2026-12-01 merge means 3 classes (users, lesson-sessions, announcements). |
| 🟡 | Head resources must not be `final`; their helpers must be `protected`. |
| 🟡 | Adding a revision means re-pointing the chain (G1). |
| ❌ | Case 7 does not compose with later changes without the type given by the relationship (`->as()`). |
| ❌ | No structured changelog (per change), only per class, unless using G2. |
| ❌ | `Arr::except` + re-adding loses the order of the attributes in the JSON (cosmetic) and cannot rename a member inside an `applyWhen()` block (a `MergeValue` with an integer key). |

### 3.9 Field-level vs inheritance vs A

| Criterion | A (`toRevisions()`) | D′ (`was()` on the field) | G1 (inheritance) |
| --- | --- | --- | --- |
| Where you read the history | per resource, per revision | per field | per revision, per resource (one class) |
| Global view of a revision | 🟡 one block per resource | ❌ | ✅ one folder |
| Readability of the head | ✅ | 🟡 suffixes + removed block | ✅ |
| Typing of old values | ✅ | ✅ | ✅ |
| Static analysis | 🟡 names are strings | 🟡 names are strings, revisions an enum (retiring guided by PHPStan) | ✅ classes and methods |
| Composition of several revisions | deltas, newest to oldest | states, oldest applicable entry | PHP inheritance |
| Structs | 🟡 dotted paths to define | ✅ native | ✅ override the struct |
| Case 7 | ✅ `relatedType()` | ✅ `was(type:)` | 🟡 role classes, ❌ with later changes, unless `->as()` |
| Case 12 (meaning) | guard `revisionBefore()` | guard | ✅ override |
| Retiring | delete blocks (enum guides) | delete `was()` entries (PHPStan guides) | ✅ delete a folder |
| Adding a revision | add one block | add one `was()` | ❌ new class + re-point the chain |
| Structured changelog | ✅ | ✅ | ❌ (✅ with G2) |
| Internal mechanism | member transformation | member transformation | class substitution |
| Change to the definition model | `revisions`, `RelationshipDefinition::$type` | same | none (optional description) |
| Rendering risk | `RevisedMembers` (one pure function) | same | delegation in the `Renderable` bridges (one point) |

A and D′ share their mechanism: supporting both costs little, only the parsing of the declarations differs. G is a
**different mechanism** (class substitution). Supporting G **and** A/D′ means two engines.

---

## 4. Comparison

✅ good · 🟡 acceptable / with workaround · ❌ fails the criterion

| Criterion | A (hook) | B (attributes) | C (classes) | D (inline) | F (guards) | **E1 (A + parent type)** |
| --- | --- | --- | --- | --- | --- | --- |
| Consistency by construction | ✅ members transformed, same code path | ✅ | ✅ | ✅ | ✅ | ✅ |
| Typing of old values | ✅ descriptors | 🟡 via methods | ✅ descriptors | ✅ | ✅ | ✅ |
| Static analysis | 🟡 member names are strings, revisions an enum | ❌ method names are strings | 🟡 `@param-closure-this` | 🟡 | 🟡 | 🟡 |
| Retiring a revision | ✅ delete one `Revision::of(…)` block per resource, found by the enum case | 🟡 delete lines by date | ✅ delete one class | ❌ grep dates in every hook | ❌ | ✅ |
| Readability of the head | ✅ `toAttributes()` untouched | ✅ | ✅ | ❌ | ❌ | ✅ |
| Head overhead | ✅ one comparison | ✅ | ✅ | 🟡 heavier descriptors | ❌ conditions on every member | ✅ |
| Compilation / cache | ✅ one definition per bucket | ✅ | 🟡 boot index | ✅ | ✅ | ✅ |
| Agnosticism | ✅ | ✅ | 🟡 global registry in config | ✅ | ✅ | ✅ |
| Non-breaking 2.x | ✅ new optional hook | ✅ | ✅ | ✅ | ✅ | ✅ |
| Octane | ✅ revision in the context | ✅ | ✅ | ✅ | ✅ | ✅ |
| Testability | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ |
| OpenAPI per revision | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Changelog extraction | ✅ per resource, per revision | 🟡 | ✅ per change | ❌ | ❌ | ✅ (+ E2 later) |
| Case 7 | 🟡 spread over parents | 🟡 | ✅ one file | ✅ local | 🟡 | ✅ local |
| Covers every case (§5.2) | ✅ | ❌ values | ✅ | ❌ type, meta | ✅ | ✅ |

---

## 5. Recommended syntax: E1

### 5.1 Principles

1. **One hook, `toRevisions(Request $request): iterable<Revision>`**, next to `toAttributes()`; absent by default.
2. **Each `Revision` is a changelog entry**: a revision, a description, and verbs in the past tense describing what that
   revision did. The old form is always the named argument `from:` (a name) or `was:` (a descriptor).
3. **Old values are descriptors** (`was: $this->integer(…)`), so they are typed.
4. **A field is a field**: attributes and relationships share one namespace in JSON:API, so `renamed()`, `removed()`,
   `changed()`, `added()` apply to both. Meta has its own verbs, its names being another namespace.
5. **Revisions are application symbols**: `string|BackedEnum`; an enum case carries the date, its description can
   carry the changelog title.

### 5.2 The vocabulary

| Verb | Undoes, for older clients | Cases |
| --- | --- | --- |
| `renamed(from: 'old', to: 'head')` | rename of an attribute or a relationship | 1, 8, 14 |
| `removed('old', was: $descriptor)` | removal: the member is restored | 2, 9, 10 |
| `changed('name', was: $descriptor)` | change of type, format, nullability, enum values, cardinality | 3, 4, 5, 9 |
| `added('name')` | addition: hidden from older revisions (optional, for exact documentation) | 15, 9, 10 |
| `renamedType(from: 'old')` | rename of the type of the resource | 6 |
| `relatedType('relationship', was: 'old')` | type of the resources reached through one relationship | 7 |
| `renamedMeta()`, `removedMeta()`, `changedMeta()`, `addedMeta()` | the same, on `toResourceMeta()` | 11 |
| `changedMeaning('name', 'description')` | nothing: documents a change of meaning in the changelog | 12 |

Within one revision, the names are those **after** the revision for `renamed(to:)`, `changed()`, `added()`, and
**before** it for `renamed(from:)`, `removed()` and `relatedType()` (which describes the old relationship): each line
reads as a sentence of the changelog. Renames are applied first, then the other changes.

### 5.3 Full example

```php
enum ApiRevision: string
{
    case Labels = '2026-06-01';
    case UsersReplaceTeachers = '2026-12-01';
    case OwnSessions = '2027-02-01';
}

#[JsonApiType('users')]
final class UserResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'display_name' => $this->string(),
            'nickname' => $this->string()->nullable(),
            'status' => $this->enum()->of(UserStatus::class),
            'balance' => $this->struct(fn() => [
                'amount' => $this->integer('balance_cents'),
                'currency' => $this->string('currency'),
            ]),
            'born_at' => $this->datetime(),
            'tag_names' => $this->arrayOf($this->string(), fn() => $this->tags->pluck('name')),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'organization' => $this->one(OrganizationResource::class),
            // case 12: same shape, new meaning; the guard reads the revision of the rendering
            'sessions' => $this->many(LessonSessionResource::class, fn() => $this->revisionBefore(ApiRevision::OwnSessions)
                ? $this->resource->sessions
                : $this->resource->sessions->where('user_id', $request->user()?->id)),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return ['last_seen_at' => $this->datetime()];
    }

    protected function toRevisions(Request $request): iterable
    {
        return [
            Revision::of(ApiRevision::OwnSessions, 'Sessions are those of the current user')
                ->changedMeaning('sessions', 'Lists the sessions of the current user only'),        // 12

            Revision::of(ApiRevision::UsersReplaceTeachers, 'Users replace teachers, authors and attendees')
                ->renamedType(from: 'teachers')                                                      // 6
                ->renamed(from: 'name', to: 'display_name')                                          // 1
                ->removed('full_name', was: $this->string(
                    fn() => "{$this->first_name} {$this->last_name}",
                ))                                                                                   // 2
                ->changed('balance', was: $this->integer('balance_cents'))                           // 3
                ->changed('born_at', was: $this->date())                                             // 3
                ->changed('nickname', was: $this->string(fn() => $this->nickname ?? ''))             // 4
                ->changed('status', was: $this->enum(
                    fn() => LegacyUserStatus::fromStatus($this->status),
                )->of(LegacyUserStatus::class))                                                      // 5
                ->removed('tags', was: $this->many(TagResource::class))                              // 10
                ->added('tag_names')                                                                 // 10, 15
                ->renamedMeta(from: 'seen_at', to: 'last_seen_at'),                                  // 11

            Revision::of(ApiRevision::Labels, 'Labels become names')
                ->renamed(from: 'label', to: 'name'),                                                // 14
        ];
    }
}

final class LessonSessionResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'instructor' => $this->one(UserResource::class),
            'attendees' => $this->many(UserResource::class),
        ];
    }

    protected function toRevisions(Request $request): iterable
    {
        return [
            Revision::of(ApiRevision::UsersReplaceTeachers, 'Users replace teachers, authors and attendees')
                ->renamed(from: 'teacher', to: 'instructor')                                         // 8
                ->relatedType('teacher', was: 'teachers')                                            // 7
                ->relatedType('attendees', was: 'attendees'),                                        // 7
        ];
    }
}

final class CourseResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return ['teachers' => $this->many(UserResource::class)];
    }

    protected function toRevisions(Request $request): iterable
    {
        return [
            Revision::of(ApiRevision::UsersReplaceTeachers, 'A course has several teachers')
                ->removed('teacher', was: $this->one(UserResource::class, fn() => $this->teachers->first()))
                ->added('teachers')                                                                  // 9
                ->relatedType('teacher', was: 'teachers'),
        ];
    }
}
```

What a client pinned on `2026-03-01` gets for `GET /lesson-sessions/1?include=teacher&fields[teachers]=label`:

```json
{
  "data": {
    "type": "lesson-sessions", "id": "1",
    "relationships": { "teacher": { "data": { "type": "teachers", "id": "7" } } }
  },
  "included": [
    { "type": "teachers", "id": "7", "attributes": { "label": "Ada Lovelace" } }
  ]
}
```

`label` is the head `display_name` renamed twice (§2.3), `teachers` comes from the parent (`relatedType`), and the
sparse fieldset is applied to the old type and the old name, because the members were transformed before
`ValueResolver`.

### 5.4 Cases 12 and 13: reading the revision

- `$this->revisionBefore(string|BackedEnum $revision): bool` on the resource (and on `JsonApiCollection`), reading
  `renderingContext`. On the probe, it answers for the bucket being compiled, so a guard used in a `when()` is even
  described per revision.
- `with()` and `additional()` are Laravel's and receive only the request: `JsonApi::revision($request)` (or
  `JsonApiContext::revisionOf($request)`) reads the request attribute. Top-level `meta` declared through
  `toDocumentMeta()` gets `renamedDocumentMeta()`… only if a real need appears.

### 5.5 Testing

```php
ResourceAssertions::assertValid(UserResource::class);                         // head, as today
ResourceAssertions::assertValid(UserResource::class, revision: '2026-03-01'); // one bucket
ResourceAssertions::assertValidAtEveryRevision(UserResource::class);          // every bucket compiles, no unknown member

$this->getJson('/api/lesson-sessions/1', ['Api-Version' => '2026-03-01']);    // feature tests of the application
$document = ResourceAssertions::render(UserResource::make($user), revision: '2026-03-01', include: 'organization');
```

---

## 6. Internal changes

Ordered by layer. Every change is additive: a resource without `toRevisions()` and a request without revision behave
exactly as in 2.0.

| Layer | Class | Change |
| --- | --- | --- |
| Configuration | new `RevisionComparator` (interface) + `LexicographicRevisions` | `compare(string $a, string $b): int`; configured by `revisions.comparator` like `type.naming` (class built without argument) |
| Configuration | `Configuration` | `revisionComparator` |
| Definition | new `RevisionDefinition` | `final readonly`: `name`, `description`, `changes` (`list<RevisionChange>`: kind, member, from, to, meaning) — the changelog, serializable |
| Definition | `ResourceDefinition` | `+ list<RevisionDefinition> $revisions` (every revision the class declares, newest first); `+ ?string $revision` (the bucket this definition describes, `null` for the head); `type` is the type **in that bucket** |
| Definition | `RelationshipDefinition` | `+ ?string $type`: the type the related resources have through this relationship, when the parent declares one (`relatedType()`); readers fall back to the related definition's type |
| Definition | `DefinitionSource` | `+ describeRevisions(Request): iterable` bridge. Adding an interface method breaks a third-party implementer: the interface is documented as implemented by `JsonApiResource` (to confirm, §7) |
| Descriptors | new `Revisions\Revision` (fluent, immutable) | the verbs of §5.2; holds descriptors |
| Descriptors | new `Revisions\RevisedMembers` | **the core**: `apply(iterable $members, list<Revision> $undone, Scope): iterable` — renames keys (pinning the head name as retriever, §1.3), replaces, removes, restores. Pure; used by both the compiler and the renderer |
| Compilation | `Definitions` | `for(string $class, string\|BackedEnum\|null $revision = null)`; `bucket()`; cache by `class@bucket`; `revisions(): list<string>` (every revision of the compiled classes, for the tools) |
| Compilation | `DefinitionCompiler` | `compile($class, ?string $bucket)`: runs `toRevisions()` on the probe, applies `RevisedMembers` to each hook before `Members`; diagnostics `revision-unknown-member`, `revision-name-collision` |
| Compilation | `CompiledDefinitions` | stores every bucket (`class@bucket` keys); file format change, regenerated by `jsonapi:cache` |
| Context | `JsonApiContext` | `+ ?string $revision`, `+ ?string $type` (type given by the parent at this path); `fromRequest()` reads the `jsonapi.revision` request attribute; `withPath(string $relationship, ?string $type = null)`; `withRevision()` |
| Loading | `LoadPlanner` | `Definitions::for($class, $context->revision)`; fields filtered on `$context->type ?? $definition->type`; nested plans get `withPath($name, $relationship->type)` |
| Rendering | `RelationshipsStage` | identify related resources with `$context->withPath($name, $resolved->type)` (`:77`), the context `included` uses (`:55`) |
| Rendering | `ResolvedRelationship` | `+ ?string $type` from the relationship |
| Resources | new `Concerns\Revisions` | `toRevisions()` (default `[]`), `revisionBefore()`, the bridge |
| Resources | `Concerns\Renderable` | fast path: bucket = head → nothing changes; otherwise `RevisedMembers` on attributes, relationships, resource meta; `renderType()` returns `$context->type`, else the bucket's type; `Definitions::for(static::class, $context->revision)` (`:86`) |
| Resources | `Relationship` | carries the `relatedType` of the bucket into `ResolvedRelationship` |
| Http | `Rules\Includes`, `Rules\Fields` | `+ ?string $revision` constructor parameter; `Fields` indexes `$relationship->type ?? $related->type` |
| Http | `JsonApiFormRequest` | builds the rules with the revision of the request |
| Laravel | `JsonApi` | `useRevision(Request, string\|BackedEnum)`, `revision(Request)` |
| Laravel | commands | `jsonapi:cache` writes every bucket; `jsonapi:check` checks every bucket; `jsonapi:revisions` prints the changelog from `RevisionDefinition`s (optional) |
| Testing | `ResourceAssertions` | `assertValid(…, revision:)`, `assertValidAtEveryRevision()`, `render()` |

Non-breaking checklist:

- every new constructor parameter is last and optional; every new definition property has a default (`[]`, `null`);
- `Definitions::for($class)` without revision = the head, the same object as today;
- the head path costs one `null` check per resource (`$context->revision === null`), or one comparison with the newest
  declared revision of the class;
- `docs/architecture.md` §3 says "a change of the definition model is a major version": **additive** properties
  must be stated as minor there (§7).

---

## 7. Impacts on `laravel-openapi-generator` (JsonApiV2 integration)

| Where | Today | With revisions |
| --- | --- | --- |
| `ResourceDefinitions::of()` (`:48`) | `Definitions::for($class)` | `Definitions::for($class, $context->revision)`: the revision becomes part of the `GenerationContext`; the generator runs once per revision |
| Revisions to document | — | `Definitions::revisions()` (or the configuration of the application) lists them; one OpenAPI document per revision, plus the head |
| `JsonApiResourceFormat::component()` (`:74`) | one component per class, `type` const from the definition | one component **per (class, type)** when a relationship gives another type (`teachers`, `authors` from `UserResource`): component names must include the type |
| `JsonApiResourceFormat::identifier()` (`:287`) | type of the related definition | `$relationship->type ?? related type` |
| `JsonApiResourceFormat::included()` (`:210-216`) | discriminator by type, warning when two classes share one | one class may now appear under several types: the mapping is by type → (class, type) component |
| `FieldsRuleMapper::types()` (`:77-81`) | `fields[type]` keyed by the definition type | also the types given by relationships |
| `JsonApiResourceFormat::rendered()` (`:341`) | renders samples | renders them with the revision (request attribute) |
| Changelog | — | `ResourceDefinition::$revisions` can feed `info.description` or an `x-changes` extension per revision |

---

## 8. Open questions

1. **Request bodies.** Should `JsonApiFormRequest` map the body of an old client to the head (renamed attributes)?
   The same `renamed()` declarations could be inverted on `data.attributes`, but required/nullable changes differ
   between input and output. Proposal: out of scope for the first iteration; the application validates per revision.
2. **Error pointers.** If the body is mapped to head names, `ValidationErrorRenderer` (`:57-71`) must map
   `source.pointer` back to the old names. Tied to question 1.
3. **Primary resources under an old type** (`GET /teachers/1`): `->as('teachers')` on the resource instance, or left to
   the application with a subclass?
4. **Struct members.** Should `renamed(from: 'price.amount', to: 'balance.amount')` reach inside structs, or is
   `changed('balance', was: …)` enough?
5. **`added()` by default?** Hiding every new member from older revisions makes the documentation exact but needs a
   declaration per addition; leaving them visible is the non-breaking default.
6. **Retired revisions.** The application's resolver refuses a retired revision; does the package need to know them
   (e.g. a diagnostic when a resource still declares a revision older than the oldest supported one)?
7. **Semver of the definition model.** Confirm that additive properties (`revisions`, `revision`,
   `RelationshipDefinition::$type`) and a new `DefinitionSource` bridge are minor changes, and write it in
   `docs/architecture.md` §3.
8. **Grouping (E2).** Is a `ResourceChange` class grouping the revisions of several resources wanted from the start, or
   once a real application needs it?
9. **Compiled cache.** One entry per bucket (recommended: same cost as the head at runtime) or lazily applying the
   changes to the head definition (smaller file, but a second code path that can diverge from rendering)?
10. **Mechanism.** Member transformation (A / D′), class substitution (G), or both? Both means two engines to
    maintain; a possible line: A/D′ is the mechanism, G0 snapshots are offered only as "a revision may name a whole
    resource class", for a resource that changed too much.
11. **Inherited `#[JsonApiType]`.** For G: inherited only by the classes marked `#[Revision]`, or a declared type
    inherited by every subclass (breaking change, 3.0)?

## 9. Spike

Not done yet: this document fixes the syntax first. What a throwaway spike must verify, in this order:

1. Renaming the keys of `renderAttributes()` keeps the head retriever (`Describer.php:164-175`) and the sparse
   fieldset applies to the old names.
2. `RelationshipsStage:77` identifying with `withPath()` gives `teachers` both in the linkage and in `included`, and a
   user reached through `teacher` and `author` appears twice, once per type, with the expected number of queries.
3. A nested old include (`teacher.organization`) loads the right relations (`LoadPlanner` with old names).
4. `Fields` and `Includes` accept the old names and types, and reject the head ones, for an old revision.
5. Compiling one bucket per declared revision: size of `bootstrap/cache/jsonapi.php` and cold rendering time
   (`composer benchmark -- --cold`) on the application of `tests/app`.
