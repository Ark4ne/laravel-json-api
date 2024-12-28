Release note
============
# v1.4.6
### Change
- Support PHP 8.4
### Fixed
- Fix `applyWhen` behavior

# v1.4.5
### Change
- `applyWhen` condition can be a closure
- CI: test all supported laravel version

# v1.4.4
### Fixes
- typo on ToResponse : change Content-type to Content-Type

# v1.4.3
### Added
- Add support for Laravel 11
### Breaking changes
- Drop support for Laravel 8

# v1.4.2
### Fixed
- Fix : exception when include relationship one [#19](https://github.com/Ark4ne/laravel-json-api/issues/19)

# v1.4.1
### Fixed
- Fix `autoWhenHas` & `autoWhenIncluded` throw exception.

# v1.4.0
### Added
- Add support for enum in values
- Add auto check when-has attributes
- Add auto set when-included relationships
- Add polyfill for `unless` method
- Surcharge `whenHas` method for support none eloquent models

### Breaking changes
- Drop support for PHP 8.0
