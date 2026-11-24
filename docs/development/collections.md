# Coleções

O CRUD de coleções usa endpoints JSON em `/api/collections`, registrados em `routes/web.php` com o middleware `web` e autenticação `auth`. Utiliza o guard `web` e a sessão existente, sem autenticação por token. As operações de escrita preservam a proteção CSRF do Laravel; clientes devem enviar o cookie de sessão e um token CSRF válido, como `X-XSRF-TOKEN`.

Clientes devem enviar `Accept: application/json` e, ao enviar JSON, `Content-Type: application/json`. Estes endpoints não renderizam páginas Inertia.

| Método | Rota | Resposta de sucesso |
| --- | --- | --- |
| GET | `/api/collections` | `200`, lista paginada do usuário autenticado. |
| POST | `/api/collections` | `201`, coleção criada. |
| GET | `/api/collections/{collection}` | `200`, coleção consultada. |
| PATCH | `/api/collections/{collection}` | `200`, coleção renomeada. |
| DELETE | `/api/collections/{collection}` | `204`, sem corpo. |

Criação e atualização aceitam somente o campo `name`, obrigatório, textual, com até 255 caracteres. Espaços externos são removidos antes da validação. Nomes repetidos são permitidos. Campos adicionais, como `user_id` e `id`, são ignorados; o proprietário é sempre o usuário autenticado.

```json
{"name": "Ficção brasileira"}
```

Criação, consulta e atualização retornam somente o ID e o nome:

```json
{"data": {"id": 1, "name": "Ficção brasileira"}}
```

A listagem retorna `data` como array, acompanhado de `links` e `meta` no formato padrão dos API Resources do Laravel. Usa 25 itens por página, ordenados por ID decrescente, com navegação por `?page=2`. Quando não há coleções, `data` é um array vazio e `meta.total` é zero.

Na listagem, cada item inclui também `books_count`, calculado no banco e restrito aos livros do proprietário. `meta` inclui `total_books_count` e `uncollected_books_count`, representando todos os livros do usuário e aqueles sem coleção. As contagens não executam uma consulta por coleção. Respostas de criação, consulta individual e atualização preservam o formato com ID e nome.

Respostas de erro: `401` para sessão não autenticada, `404` para coleção inexistente ou pertencente a outro usuário e `422` para validação, com mensagens em português em `errors.name`. Requisições de escrita sem a proteção CSRF válida podem retornar `419` antes da execução do controller.

A exclusão é permanente e preserva os livros associados, deixando-os sem coleção. A associação de livros é feita pelo campo `collection_id` do [CRUD de livros](books.md); vínculos com arquivos ainda não estão implementados.

## Interface integrada

O modo Por classificação também está disponível, conforme [avaliações e classificação](book-ratings.md). Em `/books`, Todos os livros mantém a grade completa e Por coleção mostra um accordion com uma coleção aberta por vez. O grupo especial Sem coleção não é uma entidade no banco e reúne os livros com `collection_id` nulo. Coleções vazias também aparecem.

Cada grupo carrega os livros apenas quando é aberto, com 25 por página, carregamento, erro com nova tentativa e estado vazio próprios. A página e os dados de cada grupo são mantidos em memória enquanto a tela permanece aberta. Mover um livro invalida os dados em cache; grupos fechados são recarregados somente quando reabertos. Se uma movimentação esvaziar a última página, a interface retorna à última página válida.

A URL identifica modo, grupo e página: `/books?view=collections&collection=12&page=2`. Para o grupo Sem coleção, use `collection=none`. Atualizar a página restaura o grupo ativo; o histórico permite voltar e avançar. Ao alternar para Por coleção sem um grupo anterior, Sem coleção é aberto. Os cabeçalhos são carregados em lotes de 25 por Carregar mais coleções.

Nova coleção abre um modal com nome obrigatório. Na biblioteca, a criação abre o novo grupo; no cadastro de livros, seleciona a nova coleção preservando os campos preenchidos. Nomes repetidos são permitidos, com IDs apresentados nos controles de escolha. Os seletores também permitem carregar mais de 25 opções.

Ações → Organizar, em cada cartão, abre um modal que salva apenas `collection_id`. Selecionar Sem coleção remove a associação sem excluir o livro. A operação atualiza contadores e listas afetadas somente após confirmação do servidor. Os modais preservam os campos em falhas e bloqueiam submissões duplicadas. Renomear e excluir coleções não fazem parte desta interface nesta etapa.

## Verificação

Os testes usam PHPUnit e SQLite em memória, sem acesso aos dados de desenvolvimento:

O `phpunit.xml` força as variáveis de conexão tanto no ambiente quanto em `$_SERVER`, pois as variáveis definidas pelo Docker têm precedência na leitura do Laravel. O `Tests\TestCase` também verifica a conexão efetiva antes de executar as migrations dos testes e interrompe a execução se não estiver em SQLite `:memory:` no ambiente `testing`.

```sh
docker compose exec app php artisan test --filter=CollectionTest
docker compose exec app php artisan test
```

A migration deve ser aplicada no PostgreSQL local com `docker compose exec app php artisan migrate`.
