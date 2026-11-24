# Início e feed de atividades

`/dashboard` é o início após autenticação e cadastro. A raiz `/` mantém a apresentação para visitantes e redireciona usuários autenticados para o início.

O box **Minha biblioteca** lista até três livros próprios, ordenados pelo maior valor entre `books.updated_at` e a data das avaliações. Cadastro, extração de metadados, edição, organização e avaliações privadas ou compartilhadas influenciam a ordem; Kindle não influencia. Empates usam o ID do livro decrescente. O cálculo não modifica os timestamps originais.

## Compartilhamento por atividade

Os formulários de upload, cadastro manual e avaliação têm um switch desligado por padrão. O campo opcional `share_to_feed` aceita booleanos; ausente significa privado. No multipart do upload, use `1`/`0`. O `PATCH` de metadados não permite esse campo: não existe uma configuração de livro público nem autorização automática de avaliações futuras.

Consentir cria uma atividade pendente na transação do registro original. A fila `database` usa a mesma conexão e transação da aplicação: o job só fica visível ao worker após o commit. O processamento/publicação não precisa de e-mails ou serviços externos.

As atividades permitidas são `book_added` e `book_rated`. Não existe endpoint genérico para publicar conteúdo. Cada avaliação compartilhada é uma atividade independente; uma versão privada posterior não remove as anteriores. Dados anteriores à implantação não são publicados retroativamente.

Livros manuais podem ser publicados pelo job imediatamente. Quando há arquivo, tanto a adição quanto as avaliações aguardam extração bem-sucedida. A conclusão da extração agenda a publicação das atividades aguardando o livro. Falha definitiva deixa o registro privado, com compartilhamento `failed`; uma execução posterior bem-sucedida pode retomar essa publicação, respeitando o consentimento original. Uma retirada nunca é retomada.

Os recursos privados de livro e avaliação incluem `feed_activity: null | {id, status}`. No livro, representa a atividade de adição. Estados:

- `pending`: aguardando processamento ou publicação.
- `published`: compartilhado no feed.
- `failed`: publicação não concluída; o registro continua na biblioteca.
- `withdrawn`: retirado, sem apagar o livro ou a avaliação.

A biblioteca acompanha estados pendentes com seu polling existente; o histórico de avaliações consulta os estados ao abrir a aba. O switch de uma nova avaliação sempre começa desligado.

## Feed e autorização

O feed inclui atividades publicadas pelo próprio usuário e pelos usuários que ele segue atualmente, inclusive as compartilhadas antes do vínculo. Não inclui atividades de quem apenas o segue, desconhecidos ou registros privados. Não há aprovação, busca, perfis ou operações HTTP de seguir nesta etapa: os modelos e o seeder preparam a estrutura direcional.

Os dados expostos são ID/tipo da atividade, autor com ID e nome, livro com título/autoria/capa, nota/comentário da versão compartilhada, datas e permissão de retirada. O recurso não reutiliza o `BookResource` privado: não contém e-mail, ID do livro, arquivo, nome original, localização do objeto ou dados de Kindle.

Na publicação, título, autoria, nota e comentário são copiados para um snapshot. A capa recebe uma cópia privada independente. Edições posteriores não alteram itens publicados. Comentários são renderizados como texto, com **Ler mais** para conteúdos longos.

| Método | Rota | Resultado |
| --- | --- | --- |
| GET | `/api/feed` | 200, `data` e `links`/`meta` de paginação por cursor Laravel. |
| GET | `/api/feed-activities/{activity}/cover` | 200, JPEG autorizado; sem URL pública permanente. |
| DELETE | `/api/feed-activities/{activity}` | 204, retirada pelo autor; repetir é seguro. |

Endpoints usam sessão `web`; DELETE exige CSRF. Use `Accept: application/json`. Visitantes recebem 401 nos endpoints JSON. Atividades indisponíveis ou operações de terceiros retornam 404. Cursor inválido retorna 422 em português.

A consulta traz 20 itens por página, ordenados por `published_at DESC, id DESC`. Use `links.next` para continuar; não crie cursores manualmente. A paginação aplica a visibilidade em toda requisição e não duplica itens quando novas publicações entram no topo.

O scroll infinito tem alternativa **Carregar mais**, erro com nova tentativa e indicação de fim. **Atualizar feed** reinicia a consulta. Não há atualizações em tempo real. Retirar exige confirmação; somente o autor vê a ação. A retirada preserva a origem e o registro de publicação, impedindo republicação por retry.

Excluir livro/conta remove suas atividades. Retirar ou excluir agenda limpeza das cópias das capas. Cada solicitação de capa revalida visibilidade; deixar de seguir revoga novas consultas e downloads de capa. Conteúdo já exibido em um navegador não é apagado remotamente; não há push ou invalidação em tempo real.

## Demonstração local

```sh
docker compose exec app php artisan migrate
docker compose restart queue
docker compose exec app php artisan db:seed --class=FeedDemoSeeder
```

O seeder específico só aceita ambientes `local` e `testing`; não faz parte do `DatabaseSeeder`. Não limpa o banco, não redefine senhas existentes, não modifica contas reais e recusa colisões com os nomes de demonstração. Executá-lo novamente com os registros originais não duplica dados, mesmo antes do processamento concluir. Não restaura atividades retiradas durante testes.

Todas as contas usam a senha de demonstração **`FeedDemo123!`**:

| Login | Relação com Ana |
| --- | --- |
| `ana@feed-demo.example.test` | Conta principal. |
| `bruno@feed-demo.example.test` | Ana segue Bruno. |
| `clara@feed-demo.example.test` | Ana segue Clara. |
| `diego@feed-demo.example.test` | Diego segue Ana; Ana não segue Diego. |
| `eva@feed-demo.example.test` | Sem vínculo com Ana. |

São cinco contas, 20 livros, 100 avaliações e 95 atividades solicitadas. Após os jobs concluírem: 90 atividades publicadas, cinco retiradas e 54 visíveis para Ana. Há avaliações privadas posteriores às compartilhadas, nota zero, meias estrelas, textos longos e livros manuais.

Os cinco arquivos são EPUB/PDF pequenos gerados pelo próprio seeder, com texto e capas sintéticos. Passam pelo armazenamento e pelos jobs reais de extração. Não incluem livros pessoais e não solicitam envio ao Kindle. O worker precisa permanecer ativo para finalizar as publicações.

## Verificação

```sh
docker compose exec app php artisan test --filter=Feed
docker compose exec app vendor/bin/pint --test
docker compose exec vite npm run typecheck
docker compose exec vite npm run build
```

Os testes usam SQLite em memória, armazenamento isolado, filas/e-mail controlados e processamento local real dos arquivos sintéticos. No navegador, verificar consentimento desligado, feed vazio, carregamento/erro, paginação automática e manual, retirada, acesso às capas, teclado, mobile e publicação após extração. Não é necessário confirmar envio real ao Kindle.
