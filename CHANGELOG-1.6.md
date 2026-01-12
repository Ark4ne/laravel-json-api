# Changelog - Version 1.6

## [1.6.3] - 2025-02-15
### Change
- Drop support for Laravel > 12.44 due to compatibility issues with `illuminate/http` package.

## [1.6.0] - 2025-01-28
### Added

- **Typed Arrays Support**: Added support for typed arrays in resource attributes, allowing type-safe array elements
  - New `of()` method on `array()` descriptor to specify element type
  - Support for class references (e.g., `ValueString::class`) in addition to descriptor instances
  - Alternative `arrayOf()` helper method for more concise syntax
  - Full support for nested typed arrays (multi-dimensional arrays)
  - Compatible with all conditional methods (`when()`, `whenNotNull()`, `whenFilled()`, etc.)
  - Type casting applied to all array elements (strings, integers, floats, booleans, etc.)