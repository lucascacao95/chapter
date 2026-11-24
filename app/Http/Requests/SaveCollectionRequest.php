<?php

namespace App\Http\Requests;

use App\Models\Collection;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveCollectionRequest extends FormRequest
{
    public function authorize(): Response
    {
        $collection = $this->route('collection');

        return $collection instanceof Collection
            ? Gate::inspect('update', $collection)
            : Gate::inspect('create', Collection::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da coleção.',
            'name.string' => 'O nome da coleção deve ser um texto.',
            'name.max' => 'O nome da coleção deve ter no máximo 255 caracteres.',
        ];
    }
}
