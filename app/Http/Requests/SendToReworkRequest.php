<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates input for pulling a qty out of the normal scan flow and sending
 * it to the rework team, on the Production WIP Scanning screen.
 */
class SendToReworkRequest extends FormRequest
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
            'ticket_code' => ['required', 'string', 'max:255'],
            'operation_id' => ['required', 'integer', 'exists:operation_masters,id'],
            'daily_shift_team_id' => ['required', 'integer', 'exists:daily_shift_teams,id'],
            'direction' => ['nullable', Rule::in(['IN', 'OUT', 'in', 'out'])],
            'rework_qty' => ['required', 'integer', 'min:1'],
            'reason_id' => ['required', 'integer', 'exists:reasons,id'],
            'remarks' => ['nullable', 'string', 'max:255'],
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
