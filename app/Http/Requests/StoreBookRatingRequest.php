<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreBookRatingRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('book'));
    }

    public function rules(): array
    {
        return [
            'share_to_feed' => ['sometimes', 'boolean'],
            'rating' => ['bail', 'required', 'numeric', 'between:0,5', 'multiple_of:0.5'],
            'review' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'share_to_feed.boolean' => 'Escolha se deseja compartilhar esta atividade.',
            'share_to_feed.prohibited' => 'O compartilhamento deve ser escolhido no cadastro.',
            'rating.required' => 'Selecione uma nota para o livro.',
            'rating.numeric' => 'Selecione uma nota entre 0 e 5.',
            'rating.between' => 'Selecione uma nota entre 0 e 5.',
            'rating.multiple_of' => 'Selecione uma nota em passos de meia estrela.',
            'review.string' => 'O comentário deve ser um texto.',
            'review.max' => 'O comentário deve ter no máximo 10.000 caracteres.',
        ];
    }
}
