Release note
============

# v1.2.0
### Added
- Implement described notation
- Implement `applyWhen`, Unlike `mergeWhen`, `applyWhen` keeps the keys even when the condition is not met.

### Breaking change
- `Resources\Concerns\Identifier::toType`: by default return type computed from resource class insteadof model class
