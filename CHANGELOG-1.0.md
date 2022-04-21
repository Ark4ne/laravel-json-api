Release note
============

# v1.0.0
### Added
- `Schema` trait implement method `schema` on `JsonApiResource` and `JsonApiCollection`
- `Relationize::relationship` static method which allow to create relation for resource (like : `PostResource::relationship(fn() => $this->posts)`)


### Breaking change
- `Relationship::construct` signature has change for :
```php 
public function __construct(
  protected string $resource, // represent class-string of resource  
  protected Closure $value,   // MUST be a closure which return real value
  protected ?Closure $links = null,
  protected ?Closure $meta = null
)
```

- `JsonApiResource`
  - `construct` can't be overwrited
  - `toAttributes` **SHOULD** return an `array<string, Closure>`
  - `toRelationships` **MUST** return an `array<string, Relationship>`


- `JsonApiCollection`
    - `construct` can't be overwrited


- `Resourceable`
  - `toArray` second parameter has been changed from `false` to `true`, and comportment has been inverted. 


- `Relationize`
  - `asRelationship` has been deleted
