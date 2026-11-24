# Entidade Book

Cadastro privado de um livro e seus metadados. Model: `App\Models\Book`. Tabela: `books`. O CRUD dos metadados é complementado pelo [BookFile](book-file.md), que representa o arquivo original opcional e acompanha sua extração. A capa extraída usa o campo `cover_path` existente.

Somente `title` é obrigatório entre os metadados. Os demais campos opcionais começam como `null`. Textos têm espaços externos removidos pela validação HTTP padrão do Laravel.

| Campo | Definição e finalidade |
| --- | --- |
| `id` | Identificador numérico gerado pelo banco. |
| `user_id` | Proprietário obrigatório, definido pela sessão e com índice e chave estrangeira para `users`. |
| `title` | Título obrigatório, até 255 caracteres. Identifica o livro para o leitor; títulos repetidos são permitidos. |
| `authors` | Lista JSON opcional de até 100 nomes, cada um textual, preenchido e com até 255 caracteres. Permite múltiplos autores sem uma entidade adicional. Aceita também lista vazia. |
| `page_count` | Quantidade de páginas, inteiro opcional entre 1 e 2147483647. Pode ser desconhecida. |
| `publisher` | Editora, texto opcional de até 255 caracteres. |
| `edition` | Edição, texto opcional de até 255 caracteres, permitindo qualificações como “2ª edição revisada”. |
| `description` | Sinopse ou descrição, texto opcional de até 10000 caracteres. |
| `language` | Idioma, texto opcional de até 35 caracteres, por exemplo `pt-BR`. Sem catálogo ou validação de códigos nesta etapa. |
| `published_year` | Ano de publicação, inteiro opcional de 1 a 9999. Não exige conhecer uma data completa. |
| `isbn` | Identificador editorial opcional, texto de até 32 caracteres. Aceita hífens e espaços; não valida checksum nem exige unicidade nesta etapa. |
| `collection_id` | Coleção opcional do mesmo proprietário, com índice e chave estrangeira para `collections`. |
| `cover_path` | Referência interna opcional para armazenamento privado da capa, até 1024 caracteres. Preenchida pelo processamento no mesmo disco privado do original; não é aceita na escrita nem exposta diretamente. A API oferece `cover_url` com autorização. |
| `created_at`, `updated_at` | Timestamps padrão do Laravel, para inclusão e última alteração. |

Um usuário possui vários livros. Cada livro pertence a um único usuário e pode pertencer a uma coleção. Todos os acessos são restritos ao proprietário. A API retorna `404` para livros inexistentes ou de terceiros. A associação com coleção inexistente ou de terceiros retorna `422` com a mesma mensagem.

A exclusão de um livro é permanente e preserva sua coleção. Excluir uma coleção preserva seus livros com `collection_id = null`, por chave estrangeira `ON DELETE SET NULL`. Excluir o usuário remove seus livros por cascata.

O [contrato HTTP](../development/books.md) descreve os endpoints, a paginação e a atualização parcial.


## Avaliações

Um livro tem várias [BookRating](book-rating.md), imutáveis pela aplicação. `latestRating()` representa a avaliação de maior ID; livros sem avaliações retornam null. Excluir o livro remove também seu histórico.
