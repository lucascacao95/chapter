# Avaliações e classificação da biblioteca

A interface de `/books` permite avaliar pelo menu **Ações → Avaliar livro**, com notas entre 0 e 5 em passos de meia estrela e comentário opcional. A aba **Histórico** lista todas as versões, da mais recente para a mais antiga, com nota, comentário e data. Salvar sempre registra uma nova versão. O formulário começa com a avaliação atual; não há edição ou exclusão de versões individuais.

As estrelas podem ser selecionadas por mouse, toque ou setas do teclado nos controles de rádio. O zero tem uma opção explícita. O card mostra estrelas e nota numérica localizada; livros nunca avaliados mostram **Sem avaliação**. Livros cadastrados manualmente também podem ser avaliados.

## API privada

Os endpoints usam sessão `web`, autenticação e CSRF nas escritas, como os endpoints de livros. Envie `Accept: application/json` e JSON nas escritas.

| Método | Rota | Resposta |
| --- | --- | --- |
| POST | `/api/books/{book}/ratings` | 201, `{data: BookRating}`. |
| GET | `/api/books/{book}/ratings?page=2` | 200, histórico paginado, 25 registros por página. |
| GET | `/api/book-rating-groups` | 200, sete faixas com contadores privados. |

O campo opcional `share_to_feed` solicita compartilhamento desta versão e começa falso. Os recursos privados incluem `feed_activity` com ID e estado ou null. Regras de publicação e privacidade estão em [feed](feed.md).

POST aceita `rating` obrigatório, numérico, entre 0 e 5 e múltiplo de 0,5; `review` é texto nullable com até 10.000 caracteres. Comentários vazios são normalizados para null. `book_id`, `book_file_id` e `user_id` são definidos no servidor e não podem ser escolhidos no payload.

Exemplo: `{"rating":4.5,"review":"Uma ótima leitura."}`. O recurso da avaliação contém `id`, `book_id`, `book_file_id`, `user_id`, `rating`, `review` e `created_at`. Notas são números JSON. Os recursos de livro passam a conter `latest_rating`, com essa estrutura ou null.

Respostas de erro: 401 para sessão não autenticada, 404 para livro inexistente ou de outro usuário, 422 para validação em português e 419 para CSRF inválido. A interface bloqueia salvamentos simultâneos; em falha de rede ou erro de servidor de resultado desconhecido, mantém os campos e solicita conferir o histórico antes de nova avaliação, sem repetição automática. Duas solicitações deliberadas com o mesmo conteúdo representam duas versões.

## Por classificação

Ao lado de **Todos os livros** e **Por coleção**, **Por classificação** apresenta um accordion com um grupo aberto por vez:

| `rating_band` | Faixa |
| --- | --- |
| `5` | 5 estrelas |
| `4` | 4 a 4,5 estrelas |
| `3` | 3 a 3,5 estrelas |
| `2` | 2 a 2,5 estrelas |
| `1` | 1 a 1,5 estrelas |
| `0` | 0 a 0,5 estrela |
| `none` | Sem avaliação |

Os cabeçalhos aparecem nessa ordem, inclusive vazios. A primeira abertura seleciona **Sem avaliação**. Somente a versão mais recente decide a faixa; cada livro aparece em um grupo. Os contadores são calculados em consulta agregada, sem multiplicação pelas versões anteriores. `/api/book-rating-groups` retorna `data` com `key`, `label` e `books_count`, e `meta.total_books_count`.

A listagem existente aceita `/api/books?rating_band=4&page=2`. O parâmetro deve usar um dos valores da tabela; ausente, não filtra por nota. Pode ser combinado com `collection_id` na API, aplicando a interseção. A interface oferece os modos separadamente, sem filtros combinados.

A URL preserva modo, faixa e página, por exemplo `/books?view=ratings&rating_band=4&page=2`, incluindo atualização da tela e voltar/avançar. Cada grupo carrega seus livros quando aberto, com 25 por página e ordenação por ID do livro decrescente. A tela conserva páginas independentes de coleções e classificações em memória.

Salvar uma avaliação invalida as listas e atualiza os contadores. Se o livro mudar de faixa, sai da lista anterior sem trocar o grupo aberto; páginas que deixarem de existir retornam à última válida. Adicionar livro segue o cadastro normal, sem atribuir nota. As ações **Organizar** e **Enviar ao Kindle** ficam no mesmo menu do card; o andamento e o resultado do envio continuam visíveis fora dele.

## Desenvolvimento

Aplicar a migration com `docker compose exec app php artisan migrate`. Livros anteriores começam sem avaliação. O esquema e as regras de exclusão estão em [BookRating](../data-model/book-rating.md).

Testes: `docker compose exec app php artisan test --filter=BookRatingTest`. A suíte usa SQLite em memória, sem credenciais ou serviços externos. Para validação de interface, confira seleção por teclado, histórico, mudança de faixa, contadores, navegação por URL, paginação e ações existentes, sem confirmar envio real ao Kindle.
