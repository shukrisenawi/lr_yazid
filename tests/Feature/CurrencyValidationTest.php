<?php

namespace Tests\Feature;

use App\Http\Requests\MembershipPlanRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\ShippingSettingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CurrencyValidationTest extends TestCase
{
    public function test_money_validation_limits_are_presented_in_ringgit(): void
    {
        foreach ([
            [new ProductRequest, 'price_sen', 100000001, 'RM 1,000,000.00'],
            [new ProductRequest, 'variants.0.price_sen', 100000001, 'RM 1,000,000.00'],
            [new ShippingSettingsRequest, 'shipping_fee_sen', 1000001, 'RM 10,000.00'],
            [new MembershipPlanRequest, 'price_sen', 99, 'RM 1.00'],
            [new MembershipPlanRequest, 'price_sen', 100000001, 'RM 1,000,000.00'],
        ] as [$request, $field, $amount, $expected]) {
            $data = [];
            data_set($data, $field, $amount);
            $errors = Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->errors();
            $this->assertStringContainsString($expected, $errors->first($field));
            $this->assertStringNotContainsString('sen', $errors->first($field));
        }
    }
}
