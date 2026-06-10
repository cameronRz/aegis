<?php

namespace App\Http\Requests;

use App\Enum\BillingInterval;
use App\Enum\PriceType;
use App\Enum\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSubscription = $this->input('type') === ProductType::Subscription->value;
        $tracksInventory = $this->boolean('track_inventory');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', 'string', Rule::enum(ProductType::class)],
            'is_active' => ['boolean'],
            'price' => ['required', 'integer', 'min:0'],
            'price_type' => ['required', 'string', Rule::enum(PriceType::class)],
            'billing_interval' => [
                Rule::requiredIf($isSubscription),
                'nullable',
                Rule::enum(BillingInterval::class),
            ],
            'billing_interval_count' => [
                Rule::requiredIf($isSubscription),
                'nullable',
                'integer',
                'min:1',
            ],
            'trial_period_days' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['boolean'],
            'stock_quantity' => [
                Rule::requiredIf($tracksInventory),
                'nullable',
                'integer',
                'min:0',
            ],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
