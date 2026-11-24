# Entidade BookRating

Avaliação pessoal versionada de um livro. Model: `App\Models\BookRating`. Tabela: `book_ratings`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Identificador e ordem das versões. |
| `book_id` | FK books | Livro avaliado, obrigatório. |
| `book_file_id` | FK book_files nullable | Arquivo associado ao livro no momento da avaliação, quando existir. |
| `user_id` | FK users | Proprietário do livro, definido no servidor. |
| `rating` | decimal(2,1) | Nota de 0 a 5, em passos de 0,5. |
| `review` | text nullable | Comentário opcional, até 10.000 caracteres na API. |
| `created_at` | timestamp | Momento do registro. |

Cada salvamento cria uma linha, inclusive quando o conteúdo é igual. Não existe `updated_at`, rota de edição ou exclusão individual. O model recusa atualização e exclusão por instância; operações SQL diretas não passam por essa proteção. O serviço de criação valida pela Form Request e grava em transação, bloqueando o livro durante a associação do arquivo. Nunca aceita os vínculos enviados pelo cliente.

`Book::ratings()` contém todas as versões; `Book::latestRating()` seleciona o maior ID. O índice `(book_id, id)` atende o histórico e a relação atual. Datas iguais não tornam a versão atual ambígua. Zero é uma nota válida; um livro nunca avaliado não tem registros.

Excluir livro ou usuário remove o histórico por cascata. A FK do arquivo usa `NO ACTION`: excluí-lo isoladamente enquanto referenciado é rejeitado; a exclusão do livro inteiro remove arquivo e avaliações na mesma operação. Não há troca ou exclusão isolada de arquivos na interface atual.

A avaliação e seu histórico são privados, sujeitos à autorização do livro. Uma versão pode ser compartilhada explicitamente em uma [atividade do feed](feed.md), com snapshot separado; isso não abre o acesso ao histórico privado. Não representam notas de outros usuários nem médias entre versões.
