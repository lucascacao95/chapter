<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveBookRequest extends FormRequest
{
    public function authorize(): Response
    {
        $book = $this->route('book');

        return $book instanceof Book
            ? Gate::inspect('update', $book)
            : Gate::inspect('create', Book::class);
    }

    public function rules(): array
    {
        return [
            'share_to_feed' => $this->isMethod('PATCH') ? ['prohibited'] : ['sometimes', 'boolean'],
            'title' => $this->isMethod('PATCH')
                ? ['sometimes', 'required', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'authors' => ['nullable', 'array', 'list', 'max:100'],
            'authors.*' => ['required', 'string', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'language' => ['nullable', 'string', 'max:35'],
            'published_year' => ['nullable', 'integer', 'between:1,9999'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'collection_id' => [
                'bail', 'nullable', 'integer',
                Rule::exists('collections', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'share_to_feed.boolean' => 'Escolha se deseja compartilhar esta atividade.',
            'share_to_feed.prohibited' => 'O compartilhamento deve ser escolhido no cadastro.',
            'title.required' => 'Informe o título do livro.',
            'authors.array' => 'Os autores devem ser uma lista de nomes.',
            'authors.list' => 'Os autores devem ser uma lista de nomes.',
            'authors.max' => 'Informe no máximo 100 autores.',
            'authors.*.required' => 'Informe o nome do autor.',
            'authors.*.string' => 'O nome do autor deve ser um texto.',
            'authors.*.max' => 'O nome do autor deve ter no máximo 255 caracteres.',
            'page_count.integer' => 'A quantidade de páginas deve ser um número inteiro.',
            'page_count.min' => 'A quantidade de páginas deve ser maior que zero.',
            'page_count.max' => 'A quantidade de páginas deve ser no máximo 2147483647.',
            'published_year.integer' => 'O ano de publicação deve ser um número inteiro.',
            'published_year.between' => 'O ano de publicação deve estar entre 1 e 9999.',
            'collection_id.integer' => 'Selecione uma coleção válida.',
            'collection_id.exists' => 'Selecione uma coleção válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'authors' => 'autores',
            'page_count' => 'quantidade de páginas',
            'publisher' => 'editora',
            'edition' => 'edição',
            'description' => 'descrição',
            'language' => 'idioma',
            'published_year' => 'ano de publicação',
            'isbn' => 'ISBN',
            'collection_id' => 'coleção',
        ];
    }
}
