<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

class ListFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['cursor' => ['sometimes', 'required', 'string', 'max:1024', function (string $attribute, mixed $value, Closure $fail) {
            try {
                $cursor = Cursor::fromEncoded($value);
                $parameters = $cursor?->toArray();
                if (! $parameters || Validator::make($parameters, ['id' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX], 'published_at' => ['required', 'date_format:Y-m-d H:i:s']])->fails()) {
                    $fail('A página do feed é inválida. Atualize a página.');
                }
            } catch (\Throwable) {
                $fail('A página do feed é inválida. Atualize a página.');
            }
        }]];
    }

    public function messages(): array
    {
        return ['cursor.required' => 'A página do feed é inválida. Atualize a página.', 'cursor.string' => 'A página do feed é inválida. Atualize a página.', 'cursor.max' => 'A página do feed é inválida. Atualize a página.'];
    }
}
