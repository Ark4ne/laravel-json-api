# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Testing
- `vendor/bin/phpunit` - Run all tests
- `vendor/bin/phpunit --coverage-clover coverage.xml` - Run tests with coverage
- `vendor/bin/phpunit --configuration phpunit.php8.1.xml.dist` - Run tests for specific PHP version
- `vendor/bin/phpunit tests/Unit` - Run only unit tests
- `vendor/bin/phpunit tests/Feature` - Run only feature tests

### Static Analysis
- `vendor/bin/phpstan analyze` - Run PHPStan static analysis (level 6)

### Dependencies
- `composer install` - Install dependencies
- `composer require laravel/framework ^9.0` - Install specific Laravel version for testing

## Architecture

This is a Laravel package that provides JSON:API compliant resource serialization. The core architecture follows these patterns:

### Resource System
- **JsonApiResource**: Main abstract class extending Laravel's JsonResource, implements JSON:API specification
- **JsonApiCollection**: Handles collections of resources with proper JSON:API formatting
- **Resourceable**: Interface defining resource contracts

### Key Components

#### Descriptors
- **Values** (`src/Descriptors/Values`): Type descriptors for attributes (string, integer, float, date, enum, etc.)
- **Relations** (`src/Descriptors/Relations`): Relationship descriptors (one, many)

#### Resource Concerns
- **Attributes**: Handles attribute serialization with field filtering
- **Relationships**: Manages relationship loading and serialization with include support
- **ConditionallyLoadsAttributes**: Laravel-style conditional attribute support
- **Identifier**: Resource ID and type handling
- **Links**: JSON:API links support
- **Meta**: Meta information handling
- **Schema**: Resource schema generation for validation
- **ToResponse**: Response formatting

#### Request Validation
- **Rules/Includes**: Validates `include` parameter against resource schema
- **Rules/Fields**: Validates `fields` parameter for sparse fieldsets

### Key Features
- **Include Support**: Dynamic relationship loading via `?include=` parameter
- **Sparse Fieldsets**: Attribute filtering via `?fields[type]=` parameter  
- **Described Notation**: Fluent API for defining attributes and relationships with type casting
- **Laravel Compatibility**: Supports Laravel 9-12 and PHP 8.1-8.4

### Configuration
The package includes a config file (`config/jsonapi.php`) with settings for:
- Nullable value handling
- Date formatting
- Float precision
- Automatic whenHas conditions
- Relationship loading behavior

### Testing Structure
- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test complete JSON:API response formatting
- Uses Orchestra Testbench for Laravel package testing
- SQLite in-memory database for testing