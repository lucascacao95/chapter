# Seguidores e atividades

## user_follows

Relação direcional entre usuários. `follower_id` identifica quem segue; `followed_id`, quem é seguido. A chave primária composta impede duplicação; CHECK impede auto-seguimento; `created_at` registra o início. O índice em `followed_id` atende a relação inversa. Ambas as FKs usam cascata na exclusão da conta.

`User::following()` e `User::followers()` usam a mesma tabela, com sentidos opostos. Não há reciprocidade implícita, aprovação ou endpoints de gerenciamento nesta etapa.

## feed_activities

Model `App\Models\FeedActivity`, separado dos registros privados e das notificações de e-mail do Laravel.

| Campo | Regra |
| --- | --- |
| `id` | Identificador interno. |
| `user_id` | Autor, FK users com cascata. |
| `book_id` | Origem, FK books com cascata. |
| `book_rating_id` | Versão avaliada; nullable para adição; FK com cascata. |
| `type` | `book_added` ou `book_rated`. |
| `status` | `pending`, `published`, `failed`, `withdrawn`. |
| `source_key` | Chave única gerada no servidor, tipo + ID da origem. |
| `snapshot` | JSON preenchido na publicação, com título, autoria e nota/comentário opcionais. |
| `cover_disk`, `cover_path` | Cópia privada da capa; nunca expostos na API. |
| `occurred_at` | Data do cadastro/avaliação original. |
| `published_at` | Data efetiva da publicação; nullable enquanto não publicado. |
| `withdrawn_at` | Data da retirada, quando existir. |
| `created_at`, `updated_at` | Auditoria do estado da publicação. |

Índices em `(status, published_at, id)` e `(user_id, status, published_at, id)` atendem o feed. A chave única da origem e bloqueios de livro/atividade tornam retries e publicação concorrente idempotentes. A ordem dos bloqueios é livro, depois atividade, compartilhada pela publicação, retirada e exclusão do livro. A exclusão por instância de Book/User é transacional; observers bloqueiam os livros antes de coletar as referências das capas, evitando perder uma cópia publicada concorrentemente.

Não há alteração de `BookRating`: consentimento e estado de compartilhamento ficam na atividade associada. Não criar essa linha equivale a manter a ação privada. Publicação e retirada não sobrescrevem notas/comentários originais.

A retirada mantém um registro invisível para impedir republicação por retries. A exclusão de uma origem remove suas atividades por FK; observers preservam antes as referências de capas para limpeza posterior em fila. A limpeza usa chaves privadas geradas pelo servidor, independentes dos arquivos originais.

Contratos e demonstração: [feed](../development/feed.md).
