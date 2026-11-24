## Entidade Coleção

Agrupador privado de livros e documentos, pertencente a um usuário. Uma coleção pode conter vários livros; os vínculos com arquivos serão adicionados posteriormente.

Tabela: `collections`. Model: `App\Models\Collection`.

| Campo | Definição |
| --- | --- |
| `id` | Identificador numérico gerado pelo banco. |
| `name` | Nome obrigatório, com até 255 caracteres e sem espaços externos. Nomes repetidos são permitidos, inclusive para o mesmo usuário. |
| `user_id` | Proprietário obrigatório, referenciando `users.id`, com índice. Definido pela sessão autenticada. |
| `created_at`, `updated_at` | Timestamps padrão do Laravel. |

Um usuário possui várias coleções; cada coleção pertence a um único usuário. Somente o proprietário pode listar, consultar, renomear ou excluir suas coleções. Tentativas de acessar coleções de terceiros retornam `404`.

A exclusão de uma coleção é permanente e preserva seus livros, deixando-os sem coleção (`books.collection_id = null`). A exclusão do usuário remove suas coleções por chave estrangeira com cascata.

O [contrato HTTP](../development/collections.md) documenta os endpoints JSON.
