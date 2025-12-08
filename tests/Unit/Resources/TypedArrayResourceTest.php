<?php

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Stub;
use Test\TestCase;

class TypedArrayResourceTest extends TestCase
{
    public function testResourceWithArrayOfStrings()
    {
        $model = Stub::model([
            'id' => 1,
            'tags' => ['php', 'laravel', 'json-api', true, 1.0]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'article';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'tags' => $this->arrayOf($this->string(), 'tags')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('article', $result['type']);
        $this->assertEquals('1', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('tags', $result['attributes']);
        $this->assertIsArray($result['attributes']['tags']);
        $this->assertSame(['php', 'laravel', 'json-api', '1', '1'], $result['attributes']['tags']);
    }

    public function testResourceWithArrayOfIntegers()
    {
        $model = Stub::model([
            'id' => 2,
            'scores' => [1.5, '2', 3, '4.9']
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'game';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'scores' => $this->arrayOf($this->integer(), 'scores')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('game', $result['type']);
        $this->assertEquals('2', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('scores', $result['attributes']);
        $this->assertIsArray($result['attributes']['scores']);
        $this->assertSame([1, 2, 3, 4], $result['attributes']['scores']);
    }

    public function testResourceWithArrayOfFloats()
    {
        $model = Stub::model([
            'id' => 3,
            'prices' => [10, '20.5', 30.75, '40']
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'product';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'prices' => $this->arrayOf($this->float(), 'prices')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('product', $result['type']);
        $this->assertEquals('3', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('prices', $result['attributes']);
        $this->assertIsArray($result['attributes']['prices']);
        $this->assertEquals([10.0, 20.5, 30.75, 40.0], $result['attributes']['prices']);
    }

    public function testResourceWithNestedTypedArrays()
    {
        $model = Stub::model([
            'id' => 4,
            'matrix' => [
                ['1', 2, 3],
                [4, '5', 6],
                [7, 8, '9']
            ]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'matrix';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'matrix' => $this->arrayOf(
                        $this->arrayOf($this->integer()),
                        'matrix'
                    )
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('matrix', $result['type']);
        $this->assertEquals('4', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('matrix', $result['attributes']);
        $this->assertIsArray($result['attributes']['matrix']);
        $this->assertSame([
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9]
        ], $result['attributes']['matrix']);
    }

    public function testResourceWithEmptyArray()
    {
        $model = Stub::model([
            'id' => 5,
            'tags' => []
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'article';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'tags' => $this->arrayOf($this->string(), 'tags')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('article', $result['type']);
        $this->assertEquals('5', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('tags', $result['attributes']);
        $this->assertIsArray($result['attributes']['tags']);
        $this->assertEmpty($result['attributes']['tags']);
    }

    public function testResourceWithUntypedArray()
    {
        $model = Stub::model([
            'id' => 6,
            'mixed' => [1, 'string', true, 3.14]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'mixed-data';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'mixed' => $this->array('mixed')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('mixed-data', $result['type']);
        $this->assertEquals('6', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('mixed', $result['attributes']);
        $this->assertIsArray($result['attributes']['mixed']);
        $this->assertSame([1, 'string', true, 3.14], $result['attributes']['mixed']);
    }

    public function testResourceWithMultipleTypedArrays()
    {
        $model = Stub::model([
            'id' => 7,
            'tags' => ['php', 'laravel'],
            'scores' => [95, 87, 92],
            'prices' => [10.5, 20.75]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'complex';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'tags' => $this->arrayOf($this->string(), 'tags'),
                    'scores' => $this->arrayOf($this->integer(), 'scores'),
                    'prices' => $this->arrayOf($this->float(), 'prices')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('complex', $result['type']);
        $this->assertEquals('7', $result['id']);
        $this->assertArrayHasKey('attributes', $result);

        $this->assertArrayHasKey('tags', $result['attributes']);
        $this->assertSame(['php', 'laravel'], $result['attributes']['tags']);

        $this->assertArrayHasKey('scores', $result['attributes']);
        $this->assertSame([95, 87, 92], $result['attributes']['scores']);

        $this->assertArrayHasKey('prices', $result['attributes']);
        $this->assertEquals([10.5, 20.75], $result['attributes']['prices']);
    }

    public function testResourceWithArrayOfBooleans()
    {
        $model = Stub::model([
            'id' => 8,
            'flags' => [true, false, 1, 0, 'true', '']
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'flags';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'flags' => $this->arrayOf($this->bool(), 'flags')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('flags', $result['type']);
        $this->assertEquals('8', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('flags', $result['attributes']);
        $this->assertIsArray($result['attributes']['flags']);
        $this->assertSame([true, false, true, false, true, false], $result['attributes']['flags']);
    }

    public function testResourceWithArrayAndClosure()
    {
        $model = Stub::model([
            'id' => 9,
            'numbers' => [1, 2, 3, 4, 5]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'calculated';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'doubled' => $this->arrayOf(
                        $this->integer(),
                        fn() => array_map(fn($n) => $n * 2, $this->numbers)
                    )
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('calculated', $result['type']);
        $this->assertEquals('9', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('doubled', $result['attributes']);
        $this->assertIsArray($result['attributes']['doubled']);
        $this->assertSame([2, 4, 6, 8, 10], $result['attributes']['doubled']);
    }

    // Edge cases with conditions

    /**
     * Note: when() conditions on item types are not evaluated per-item in the current implementation.
     * The when() only affects whether the entire array attribute is included, not individual items.
     * This test documents the actual behavior.
     */
    public function testArrayWithItemTypeConditionsAreNotEvaluated(): void
    {
        $model = Stub::model([
            'id' => 10,
            'flags' => [true, false, 1, 0]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'conditional-item';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    // The when(false) on the item type is not evaluated per-item
                    // It only affects the array descriptor itself
                    'flags' => $this->arrayOf($this->bool()->when(false), 'flags')
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('conditional-item', $result['type']);
        $this->assertEquals('10', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('flags', $result['attributes']);
        // Items are still included because when() on item type doesn't filter items
        $this->assertSame([true, false, true, false], $result['attributes']['flags']);
    }

    public function testArrayWithArrayTypeWhenFalse(): void
    {
        $model = Stub::model([
            'id' => 12,
            'flags' => [true, false, 1, 0]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'conditional-array';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'flags' => $this->arrayOf($this->bool(), 'flags')->when(false)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        // When the array itself has when(false), the entire attribute should not appear
        $this->assertEquals('conditional-array', $result['type']);
        $this->assertEquals('12', $result['id']);
        $this->assertArrayNotHasKey('attributes', $result);
    }

    public function testArrayWithArrayTypeWhenTrue(): void
    {
        $model = Stub::model([
            'id' => 13,
            'flags' => [true, false, 1, 0]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'conditional-array-true';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'flags' => $this->arrayOf($this->bool(), 'flags')->when(true)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('conditional-array-true', $result['type']);
        $this->assertEquals('13', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('flags', $result['attributes']);
        $this->assertSame([true, false, true, false], $result['attributes']['flags']);
    }

    public function testArrayWithWhenNotNull(): void
    {
        $model = Stub::model([
            'id' => 14,
            'present' => [1, 2, 3],
            'absent' => null
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'when-not-null';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'present' => $this->arrayOf($this->integer(), 'present')->whenNotNull(),
                    'absent' => $this->arrayOf($this->integer(), 'absent')->whenNotNull()
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('when-not-null', $result['type']);
        $this->assertEquals('14', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('present', $result['attributes']);
        $this->assertArrayNotHasKey('absent', $result['attributes']);
        $this->assertSame([1, 2, 3], $result['attributes']['present']);
    }

    public function testArrayWithWhenFilled(): void
    {
        $model = Stub::model([
            'id' => 15,
            'filled' => [1, 2, 3],
            'empty' => []
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'when-filled';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'filled' => $this->arrayOf($this->integer(), 'filled')->whenFilled(),
                    'empty' => $this->arrayOf($this->integer(), 'empty')->whenFilled()
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('when-filled', $result['type']);
        $this->assertEquals('15', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('filled', $result['attributes']);
        // Empty array is not "filled" so should not appear
        $this->assertArrayNotHasKey('empty', $result['attributes']);
        $this->assertSame([1, 2, 3], $result['attributes']['filled']);
    }

    public function testArrayWithWhenHas(): void
    {
        $model = Stub::model([
            'id' => 16,
            'existing' => [1, 2, 3]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'when-has';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'existing' => $this->arrayOf($this->integer(), 'existing')->whenHas(),
                    'missing' => $this->arrayOf($this->integer(), 'missing')->whenHas()
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('when-has', $result['type']);
        $this->assertEquals('16', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('existing', $result['attributes']);
        $this->assertArrayNotHasKey('missing', $result['attributes']);
        $this->assertSame([1, 2, 3], $result['attributes']['existing']);
    }

    public function testArrayWithUnless(): void
    {
        $model = Stub::model([
            'id' => 17,
            'tags' => ['php', 'laravel']
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'unless';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'visible' => $this->arrayOf($this->string(), 'tags')->unless(false),
                    'hidden' => $this->arrayOf($this->string(), 'tags')->unless(true)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('unless', $result['type']);
        $this->assertEquals('17', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('visible', $result['attributes']);
        $this->assertArrayNotHasKey('hidden', $result['attributes']);
        $this->assertSame(['php', 'laravel'], $result['attributes']['visible']);
    }

    public function testArrayWithClosureCondition(): void
    {
        $model = Stub::model([
            'id' => 18,
            'admin_tags' => ['admin', 'super'],
            'user_tags' => ['user', 'basic'],
            'is_admin' => true
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'closure-condition';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'admin_tags' => $this->arrayOf(
                        $this->string(),
                        'admin_tags'
                    )->when(fn() => $this->is_admin),
                    'user_tags' => $this->arrayOf(
                        $this->string(),
                        'user_tags'
                    )->unless(fn() => $this->is_admin)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('closure-condition', $result['type']);
        $this->assertEquals('18', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('admin_tags', $result['attributes']);
        $this->assertArrayNotHasKey('user_tags', $result['attributes']);
        $this->assertSame(['admin', 'super'], $result['attributes']['admin_tags']);
    }

    public function testArrayWithMultipleConditions(): void
    {
        $model = Stub::model([
            'id' => 19,
            'tags' => ['php', 'laravel'],
            'is_visible' => true,
            'has_permission' => true
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'multiple-conditions';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'tags' => $this->arrayOf($this->string(), 'tags')
                        ->when(fn() => $this->is_visible)
                        ->when(fn() => $this->has_permission)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('multiple-conditions', $result['type']);
        $this->assertEquals('19', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('tags', $result['attributes']);
        $this->assertSame(['php', 'laravel'], $result['attributes']['tags']);
    }

    public function testArrayWithMultipleConditionsOneFalse(): void
    {
        $model = Stub::model([
            'id' => 20,
            'tags' => ['php', 'laravel'],
            'is_visible' => true,
            'has_permission' => false
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'multiple-conditions-false';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'tags' => $this->arrayOf($this->string(), 'tags')
                        ->when(fn() => $this->is_visible)
                        ->when(fn() => $this->has_permission)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('multiple-conditions-false', $result['type']);
        $this->assertEquals('20', $result['id']);
        $this->assertArrayNotHasKey('attributes', $result);
    }

    // Alternative syntax: array()->of()

    public function testArrayWithOfSyntaxNoAttribute(): void
    {
        $model = Stub::model([
            'id' => 21,
            'flags' => [true, false, 1, 0]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-no-attr';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    // Using array()->of() syntax without specifying attribute
                    // Should use the key 'flags' from the return array
                    'flags' => $this->array()->of($this->bool())
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        // Since no attribute is specified and array() returns null by default,
        // this should result in an empty array or the behavior depends on implementation
        $this->assertEquals('of-syntax-no-attr', $result['type']);
        $this->assertEquals('21', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('flags', $result['attributes']);
    }

    public function testArrayWithOfSyntaxWithAttribute(): void
    {
        $model = Stub::model([
            'id' => 22,
            'abc' => ['php', 'laravel', 'json-api', 1, true]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-with-attr';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'abc' => $this->array('abc')->of($this->string())
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('of-syntax-with-attr', $result['type']);
        $this->assertEquals('22', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('abc', $result['attributes']);
        $this->assertIsArray($result['attributes']['abc']);
        $this->assertSame(['php', 'laravel', 'json-api', '1', '1'], $result['attributes']['abc']);
    }

    public function testArrayWithOfSyntaxWithClosure(): void
    {
        $model = Stub::model([
            'id' => 23,
            'def' => [1, 2, 3, 4, 5]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-with-closure';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'def' => $this->array(fn() => $this->def)->of($this->integer())
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('of-syntax-with-closure', $result['type']);
        $this->assertEquals('23', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('def', $result['attributes']);
        $this->assertIsArray($result['attributes']['def']);
        $this->assertSame([1, 2, 3, 4, 5], $result['attributes']['def']);
    }

    public function testArrayWithOfSyntaxAndTransformation(): void
    {
        $model = Stub::model([
            'id' => 24,
            'numbers' => [1, 2, 3]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-transformation';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'doubled' => $this->array(fn() => array_map(fn($n) => $n * 2, $this->numbers))
                        ->of($this->integer())
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('of-syntax-transformation', $result['type']);
        $this->assertEquals('24', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('doubled', $result['attributes']);
        $this->assertIsArray($result['attributes']['doubled']);
        $this->assertSame([2, 4, 6], $result['attributes']['doubled']);
    }

    public function testArrayWithOfSyntaxWithConditions(): void
    {
        $model = Stub::model([
            'id' => 25,
            'scores' => [95.5, '87', 92.3, '78'],
            'show_scores' => true
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-conditions';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'scores' => $this->array('scores')
                        ->of($this->integer())
                        ->when(fn() => $this->show_scores)
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('of-syntax-conditions', $result['type']);
        $this->assertEquals('25', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('scores', $result['attributes']);
        $this->assertIsArray($result['attributes']['scores']);
        $this->assertSame([95, 87, 92, 78], $result['attributes']['scores']);
    }

    public function testArrayWithOfSyntaxNested(): void
    {
        $model = Stub::model([
            'id' => 26,
            'matrix' => [
                [1.5, '2', 3],
                ['4', 5.7, 6],
            ]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'of-syntax-nested';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    'matrix' => $this->array('matrix')->of(
                        $this->array()->of($this->integer())
                    )
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('of-syntax-nested', $result['type']);
        $this->assertEquals('26', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('matrix', $result['attributes']);
        $this->assertIsArray($result['attributes']['matrix']);
        $this->assertSame([
            [1, 2, 3],
            [4, 5, 6],
        ], $result['attributes']['matrix']);
    }

    public function testArrayWithOfSyntaxMixedWithArrayOf(): void
    {
        $model = Stub::model([
            'id' => 27,
            'tags' => ['php', 'laravel'],
            'scores' => [95, 87, 92]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'mixed-syntax';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    // Using arrayOf() syntax
                    'tags' => $this->arrayOf($this->string(), 'tags'),
                    // Using array()->of() syntax
                    'scores' => $this->array('scores')->of($this->integer())
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('mixed-syntax', $result['type']);
        $this->assertEquals('27', $result['id']);
        $this->assertArrayHasKey('attributes', $result);

        $this->assertArrayHasKey('tags', $result['attributes']);
        $this->assertSame(['php', 'laravel'], $result['attributes']['tags']);

        $this->assertArrayHasKey('scores', $result['attributes']);
        $this->assertSame([95, 87, 92], $result['attributes']['scores']);
    }

    /**
     * Test demonstrating that when() conditions on item types within array()->of()
     * are not evaluated per-item. The condition affects the entire array descriptor,
     * not individual items.
     */
    public function testArrayWithOfSyntaxItemTypeCondition(): void
    {
        $model = Stub::model([
            'id' => 28,
            'numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
        ]);

        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'conditional-items';
            }

            public function toAttributes(Request $request): iterable
            {
                return [
                    // Note: when() on the item type doesn't filter individual items
                    // It would apply to the entire descriptor, not per-item
                    'even-numbers' => $this->array('numbers')->of(
                        $this->integer()->when(fn($request, $model, $attr) => $attr % 2 === 0)
                    )
                ];
            }
        };

        $request = new Request();
        $result = $resource->toArray($request);

        $this->assertEquals('conditional-items', $result['type']);
        $this->assertEquals('28', $result['id']);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('even-numbers', $result['attributes']);

        // All items are included, even though we specified a condition on the item type
        // The when() doesn't filter individual items, it's applied at the descriptor level
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $result['attributes']['even-numbers']);
    }
}
