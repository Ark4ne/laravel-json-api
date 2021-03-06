Release note
============

# v1.1.9
### Fixes
- `JsonApiResource::toArray` don't filter on `type` and `id`

# v1.1.8
### Add
- `Skeleton::class` present skeleton of a `JsonApiResource::class`

### Changes
- **@deprecated** `Support\With` is replaced by `Support\Arr`
- `Resources\Schema::schema` return `Skeleton`
- `Resources\SchemaCollection::schema` return `Skeleton`

### Fixes
- `JsonApiResource::toArray` return true deep array
- `Support\Includes` when request has empty parameter `include`

# v1.1.7
### Fixes
- Request rules: fix `$failures` not initialized

# v1.1.6
### Fixes
- Request rules: check type of value before assert

# v1.1.5
### Fixes
- `Relationships::mapRelationships` when data is empty

# v1.1.4
### Changes
- phpstan lvl 6 compliant
- Schema safe instantiate self for structure generation
- Relationships allow conditional relation
### Fixes
- Schema correct handle attributes

# v1.1.3
### Added 
- `Includes::includes` return the remaining includes for current resource
### Fixes
- FakeModel implement correctly ArrayAccess (add missing return type on offsetGet)

# v1.1.2
### Fixes
- phpstan annotations for rules

# v1.1.1
### Fixes
- rename namespace `Resource` to `Resources`

# v1.1.0
### Added
- implement request validation rules `Requests\Rules\{Includes, Fields}`

### Change 
- Improve `Support\Includes::get()`, disable caching.
