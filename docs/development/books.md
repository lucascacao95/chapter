# Livros

O CRUD de livros utiliza `/api/books`, com os mesmos middleware `web` e `auth` das coleções. Exige a sessão do guard `web` e preserva a proteção CSRF nas escritas. Envie o cookie de sessão, `Accept: application/json` e, ao enviar JSON, `Content-Type: application/json`. Nas escritas, forneça também um token CSRF válido, por exemplo pelo header `X-XSRF-TOKEN`.

| Método | Rota | Resposta |
| --- | --- | --- |
| GET | `/api/books` | `200`, lista paginada dos livros do usuário autenticado. |
| POST | `/api/books` | `201`, livro criado. |
| GET | `/api/books/{book}` | `200`, livro consultado. |
| PATCH | `/api/books/{book}` | `200`, livro atualizado parcialmente. |
| DELETE | `/api/books/{book}` | `204`, sem corpo; exclusão permanente. |

## Escrita

Somente `title` é obrigatório na criação. Campos aceitos e limites estão no [modelo de dados](../data-model/book.md). Não há unicidade de título nem de ISBN. Autores são uma lista de nomes; não existe cadastro separado de autores ou editoras.

```json
{
  "title": "Leituras brasileiras",
  "authors": ["Autora Um", "Autor Dois"],
  "page_count": 320,
  "publisher": "Editora Exemplo",
  "edition": "2ª edição revisada",
  "description": "Uma descrição do livro.",
  "language": "pt-BR",
  "published_year": 2024,
  "isbn": "978-85-00000-00-0",
  "collection_id": 1
}
```

`PATCH` modifica apenas os campos enviados. Campos omitidos são preservados; envie `null` para limpar um metadado opcional ou remover o vínculo com uma coleção. Se `title` for enviado, ele deve permanecer preenchido. Uma atualização sem campos aceitos é uma operação sem alteração.

`collection_id`, quando preenchido, deve identificar uma coleção do mesmo usuário. A propriedade do livro é definida pela sessão; campos extras como `user_id`, `id`, `cover_path` e timestamps são ignorados na escrita.

## Leitura e erros

Criação, consulta e atualização retornam `{"data": {...}}`, contendo `id`, `title`, `authors`, `page_count`, `publisher`, `edition`, `description`, `language`, `published_year`, `isbn`, `collection_id`, `created_at` e `updated_at`. Metadados ausentes são `null`. Os timestamps são serializados em ISO 8601; campos numéricos são serializados como números, inclusive quando enviados como strings numéricas válidas.

A lista usa o formato padrão dos API Resources do Laravel: `data`, `links` e `meta`, com 25 livros por página e ID decrescente. Navegue com `?page=2`. Somente livros do usuário autenticado aparecem, sem inclusão automática de dados de usuários ou coleções. Lista vazia retorna `data: []` e `meta.total: 0`.

O filtro opcional `collection_id` aceita um ID de coleção do usuário ou o valor `none` para livros sem coleção. Por exemplo, `/api/books?collection_id=12&page=2` pagina somente os livros dessa coleção; `/api/books?collection_id=none` lista os não agrupados. Sem o parâmetro, a listagem continua incluindo todos os livros. Filtros vazios, inválidos, inexistentes ou de terceiros retornam `422` com a mesma mensagem em `errors.collection_id`. Os links de paginação preservam o filtro.

Respostas de erro: `401` sem autenticação, `404` para livro inexistente ou de terceiros e `422` para validação, com mensagens em português em `errors`. Coleções inexistentes ou de terceiros geram a mesma mensagem em `errors.collection_id`, sem revelar seu proprietário. A proteção CSRF pode retornar `419` antes da execução do controller.

O CRUD não fornece caminhos de armazenamento nem URLs permanentes. O [upload](book-uploads.md) usa um endpoint próprio; download não foi incluído nesta etapa. A capa possui uma referência interna privada no banco; a API expõe somente `cover_url`, uma rota autenticada ou `null`. As respostas agora incluem o resumo opcional de `file`, descrito no contrato de upload.

## Interface da biblioteca

As rotas autenticadas `/books` e `/books/create` renderizam as páginas Inertia da biblioteca e do cadastro. Elas consomem a API JSON existente com `useHttp`, sem modificar suas regras de negócio.

A biblioteca apresenta cartões com título, autores e, quando conhecidos, ano e quantidade de páginas. Cada cartão mostra a capa extraída quando disponível e usa uma capa tipográfica identificada como “Sem capa” quando não há imagem. Durante a fila e a extração, mostra andamento; título, autoria e capa são atualizados pela consulta periódica da página ativa. A listagem mostra carregamento, estado vazio, erros com nova tentativa e paginação de 25 livros. A página atual fica em `/books?page=N` e participa do histórico do navegador.

Adicionar livro abre um modal com abas shadcn-vue, iniciando em Enviar arquivo. `/books/create` reutiliza o mesmo painel. Na aba Cadastro manual, o formulário exibe título e autores primeiro. “Mais detalhes” expande os demais metadados opcionais, abrindo automaticamente quando há erros nessa seção. O formulário preserva os valores em falhas, leva o foco ao primeiro campo inválido e bloqueia submissões enquanto aguarda a resposta. Após sucesso, retorna à primeira página da biblioteca com confirmação.

O cadastro oferece um seletor opcional de coleção, com Sem coleção como padrão. O seletor carrega opções em lotes de 25 e permite criar uma coleção sem perder o formulário. A coleção recém-criada é selecionada automaticamente; após cadastrar um livro com coleção, a interface abre esse grupo na biblioteca.

Os cartões oferecem Ações → Organizar, que permite escolher uma coleção ou remover o vínculo pelo `PATCH` existente. A biblioteca oferece os modos Todos os livros e Por coleção, detalhados na [documentação de coleções](collections.md). Edição dos demais metadados e exclusão pela interface ficam para etapas posteriores. O upload e a extração básica estão descritos no documento de upload.

Para conferir a interface: cadastre um livro somente com título e outro com autores e metadados; verifique as mensagens de campos inválidos, os cartões, a paginação e o layout em celular. Falhas HTTP e de conexão devem permitir nova tentativa sem apresentar sucesso indevido.

## Verificação

Os testes usam exclusivamente SQLite em memória, com a trava existente em `Tests\TestCase` antes das migrations de teste:

```sh
docker compose exec app php artisan test --filter=BookTest
docker compose exec app php artisan test
```

Aplique a nova migration no PostgreSQL local sem recriar as tabelas existentes:

```sh
docker compose exec app php artisan migrate
```


## Envio ao Kindle

Os cartões oferecem uma ação com confirmação para a [POC de envio por e-mail](kindle-poc.md). As respostas de livros incluem `kindle.configured`, `kindle.max_attachment_bytes` e o resumo de `kindle.delivery`. A listagem acompanha os envios ativos sem recarregar a página. O aceite SMTP não é apresentado como entrega ao dispositivo.


## Avaliações e menu de ações

Os cards exibem a avaliação mais recente e reúnem Avaliar livro, Organizar e Enviar ao Kindle no menu Ações. `latest_rating` integra os recursos de livro como objeto ou null. O filtro `rating_band` e o modo Por classificação agrupam pela nota mais recente, sem usar versões anteriores. Contratos, faixas e regras estão em [avaliações e classificação](book-ratings.md).
