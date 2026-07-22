<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates input for recording a bundle scan on the Production WIP
 * Scanning screen.
 */
class ScanBundleTicketRequest extends FormRequest
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
            'scan_qty' => ['nullable', 'integer', 'min:0'],
            'reject_qty' => ['nullable', 'integer', 'min:0'],
            'reject_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Add cross-field checks that a static rules array can't express: a
     * reason is required once a reject qty is given, and the request must
     * carry at least one positive quantity.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rejectQty = (int) $this->input('reject_qty', 0);
            $scanQty = (int) $this->input('scan_qty', 0);

            if ($rejectQty > 0 && !$this->filled('reject_reason')) {
                $validator->errors()->add('reject_reason', 'A reason is required when rejecting a quantity.');
            }

            if ($rejectQty <= 0 && $scanQty <= 0 && $this->filled('scan_qty') && $this->filled('reject_qty')) {
                $validator->errors()->add('scan_qty', 'Enter a scanned or rejected quantity.');
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
