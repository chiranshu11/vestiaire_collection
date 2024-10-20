<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PayoutRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true; // Can add additional authorization logic here if needed
    }

    /**
     * Validation rules for the payout request.
     */
    public function rules()
    {
        return [
            'sold_items' => 'required|array',
            'sold_items.*.seller_reference' => 'required|integer|exists:sellers,id',//performs query
            'sold_items.*.channel_item_code' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'sold_items.required' => 'The sold_items field is required.',
            'sold_items.array' => 'The sold_items must be an array.',
            'sold_items.*.seller_reference.required' => 'The seller reference is required for each item.',
            'sold_items.*.seller_reference.integer' => 'The seller reference must be an integer.',
            'sold_items.*.seller_reference.exists' => 'The seller reference does not exist in the system.',
            'sold_items.*.channel_item_code.required' => 'The channel item code is required for each item.',
            'sold_items.*.channel_item_code.exists' => 'The provided channel item code does not exist in the system.',
            'sold_items.*.channel_item_code.max' => 'The channel item code must not exceed 255 characters.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
