<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates input for adding a new Bundle (size/qty lot) to a WorkOrder.
 */
class StoreWorkOrderBundleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'size' => ['nullable', 'string', 'max:100'],
            'qty' => ['required', 'integer', 'min:1'],
            'trolly_master_id' => ['nullable', 'integer', 'exists:trolly_masters,id'],
        ];
    }

    /**
     * Return validation failures using the API's standard error envelope.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
