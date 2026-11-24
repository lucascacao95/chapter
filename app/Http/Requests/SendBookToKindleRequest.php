<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendBookToKindleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('book'));
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid']];
    }

    public function messages(): array
    {
        return ['idempotency_key.required' => 'Reabra a opção de envio e tente novamente.', 'idempotency_key.uuid' => 'Reabra a opção de envio e tente novamente.'];
    }
}
