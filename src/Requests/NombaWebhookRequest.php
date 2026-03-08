<?php

namespace Emmy\Ego\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NombaWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string'],
            'requestId' => ['required', 'string'],
            'data' => ['required', 'array'],
            'data.merchant' => ['required', 'array'],
            'data.merchant.userId' => ['required', 'string'],
            'data.merchant.walletId' => ['required', 'string'],
            'data.transaction' => ['required', 'array'],
            'data.transaction.transactionId' => ['required', 'string'],
            'data.transaction.type' => ['required', 'string'],
            'data.transaction.time' => ['required'],
            'data.transaction.responseCode' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_type.required' => 'Missing event_type in webhook payload',
            'requestId.required' => 'Missing requestId in webhook payload',
            'data.merchant.userId.required' => 'Missing data.merchant.userId in webhook payload',
            'data.merchant.walletId.required' => 'Missing data.merchant.walletId in webhook payload',
            'data.transaction.transactionId.required' => 'Missing data.transaction.transactionId in webhook payload',
            'data.transaction.type.required' => 'Missing data.transaction.type in webhook payload',
            'data.transaction.time.required' => 'Missing data.transaction.time in webhook payload',
            'data.transaction.responseCode.required' => 'Missing data.transaction.responseCode in webhook payload',
        ];
    }
}
