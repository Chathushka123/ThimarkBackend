<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates input for the rework team recording how much of an outstanding
 * rework qty came back good (return_qty) vs was permanently rejected after
 * rework (reject_qty).
 */
class ReturnFromReworkRequest extends FormRequest
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
            'bundle_ticket_id' => ['required', 'integer', 'exists:bundle_tickets,id'],
            'daily_shift_team_id' => ['required', 'integer', 'exists:daily_shift_teams,id'],
            'return_qty' => ['nullable', 'integer', 'min:0'],
            'reject_qty' => ['nullable', 'integer', 'min:0'],
            'reason_id' => ['nullable', 'integer', 'exists:reasons,id'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Add cross-field checks that a static rules array can't express: at
     * least one positive qty, and a reason once anything comes back
     * rejected.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $returnQty = (int) $this->input('return_qty', 0);
            $rejectQty = (int) $this->input('reject_qty', 0);

            if ($returnQty <= 0 && $rejectQty <= 0) {
                $validator->errors()->add('return_qty', 'Enter a returned or rejected quantity.');
            }

            if ($rejectQty > 0 && !$this->filled('reason_id')) {
                $validator->errors()->add('reason_id', 'A reason is required when rejecting a quantity.');
            }
        });
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
