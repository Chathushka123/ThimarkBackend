<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates input for updating an existing OperationMaster record.
 */
class UpdateOperationMasterRequest extends FormRequest
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
            'operation_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_masters', 'operation_code')->ignore($this->route('id')),
            ],
            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_masters', 'description')->ignore($this->route('id')),
            ],
            'active' => ['sometimes', 'boolean'],
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
