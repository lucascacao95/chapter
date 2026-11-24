<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListBooksRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', Book::class);
    }

    public function rules(): array
    {
        return [
            'rating_band' => ['sometimes', 'required', 'string', Rule::in(['0', '1', '2', '3', '4', '5', 'none'])],
            'collection_id' => [
                'sometimes', 'bail', 'required',
                Rule::when($this->input('collection_id') !== 'none', [
                    'integer',
                    Rule::exists('collections', 'id')->where('user_id', $this->user()->id),
                ], ['in:none']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating_band.required' => 'Selecione uma classificação válida.',
            'rating_band.string' => 'Selecione uma classificação válida.',
            'rating_band.in' => 'Selecione uma classificação válida.',
            'collection_id.required' => 'Selecione uma coleção válida.',
            'collection_id.integer' => 'Selecione uma coleção válida.',
            'collection_id.exists' => 'Selecione uma coleção válida.',
            'collection_id.in' => 'Selecione uma coleção válida.',
        ];
    }
}
