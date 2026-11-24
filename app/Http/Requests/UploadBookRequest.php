<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Services\Books\EpubReader;
use App\Services\Books\PdfReader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class UploadBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Book::class);
    }

    public function rules(): array
    {
        return [
            'share_to_feed' => ['sometimes', 'boolean'],
            'file' => ['required', 'file', 'max:'.config('books.max_upload_kb')],
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $file = $this->file('file');
            if ($file->getSize() < 1) {
                $validator->errors()->add('file', 'O arquivo não pode estar vazio.');

                return;
            }
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedMimes = $extension === 'pdf' ? ['application/pdf'] : ['application/epub+zip', 'application/zip', 'application/x-zip'];
            if (! in_array($extension, ['epub', 'pdf'], true) || ! in_array($file->getMimeType(), $allowedMimes, true)
                || mb_strlen($file->getClientOriginalName()) > 255) {
                $validator->errors()->add('file', 'Envie um arquivo EPUB ou PDF válido, com nome de até 255 caracteres.');

                return;
            }
            try {
                app($extension === 'epub' ? EpubReader::class : PdfReader::class)->read($file->getRealPath());
            } catch (Throwable) {
                $validator->errors()->add('file', 'Não foi possível ler este arquivo. Envie um EPUB ou PDF válido e sem proteção.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'share_to_feed.boolean' => 'Escolha se deseja compartilhar esta atividade.',
            'share_to_feed.prohibited' => 'O compartilhamento deve ser escolhido no cadastro.',
            'file.required' => 'Selecione um arquivo EPUB ou PDF.',
            'file.file' => 'Não foi possível receber o arquivo. Selecione-o novamente.',
            'file.uploaded' => 'Não foi possível receber o arquivo. O limite é de 50 MB.',
            'file.max' => 'O arquivo deve ter no máximo 50 MB.',
            'collection_id.integer' => 'Selecione uma coleção válida.',
            'collection_id.exists' => 'Selecione uma coleção válida.',
        ];
    }
}
