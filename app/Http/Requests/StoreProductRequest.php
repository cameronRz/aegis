<?php

namespace App\Http\Requests;

use App\Concerns\ProductValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ProductValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productRules(),
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
        ];
    }
}
