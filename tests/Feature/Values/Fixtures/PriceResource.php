<?php

declare(strict_types=1);

namespace Test\Feature\Values\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Values\MoneySchema;

/**
 * Uses the type `money` the application registers.
 *
 * @extends JsonApiResource<object{id: int, price: int, prices: list<int>}>
 *
 * @method MoneySchema money(string|\Closure|null $source = null)
 */
final class PriceResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'price';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'price' => $this->money(),
            'prices' => $this->arrayOf($this->money()),
        ];
    }
}
