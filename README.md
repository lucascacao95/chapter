# Minha Biblioteca

Aplicação inicial do projeto, construída com Laravel, Inertia.js, Vue 3, TypeScript e Tailwind CSS. O ambiente local usa Docker Compose com PostgreSQL e Redis.

## Desenvolvimento local

Requisitos: Docker Desktop com Docker Compose.

```bash
docker compose up --build -d
```

A aplicação fica em [http://localhost:8001](http://localhost:8001). O Vite serve os assets em `http://localhost:5173`.

Os serviços de banco e Redis ficam acessíveis apenas pela rede interna do Compose. Para aplicar migrations:

```bash
docker compose exec app php artisan migrate
```

Para rodar os testes:

```bash
docker compose exec app php artisan test
```

Para encerrar os containers sem remover os dados locais:

```bash
docker compose down
```

## Autenticação

- Cadastro: http://localhost:8001/register (nome, e-mail e senha de pelo menos 8 caracteres).
- Login: http://localhost:8001/login. Após entrar, a área pessoal está em `/dashboard`.
- Recuperação: http://localhost:8001/forgot-password.
- E-mails locais: http://localhost:8025 (Mailpit). O SMTP usa `mailpit:1025` dentro do Docker e não envia e-mails para destinatários externos.

O cadastro autentica o usuário automaticamente, sem confirmação de e-mail. O link de recuperação expira em 60 minutos, é de uso único e só pode ser emitido novamente após 60 segundos. A solicitação exibe a mesma mensagem para endereços cadastrados e desconhecidos.

As sessões e os tokens de recuperação usam as tabelas existentes no PostgreSQL. Os testes usam SQLite em memória, isolado dos dados locais. A imagem PHP oficial já inclui `pdo_sqlite`.

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec vite npm run typecheck
docker compose exec vite npm run build
```

Para conferir o fluxo local: crie uma conta, saia, entre novamente, solicite recuperação, abra o e-mail no Mailpit, redefina a senha e entre com a nova senha.

Detalhes dos fluxos e limites: [autenticação](docs/development/authentication.md).

## Início e feed

A página inicial autenticada em [http://localhost:8001/dashboard](http://localhost:8001/dashboard) mostra os três livros mais recentemente atualizados e as atividades compartilhadas por você e por quem segue, com scroll infinito. Uploads, cadastros manuais e avaliações só aparecem no feed quando o switch de compartilhamento é ligado. Arquivos e dados não compartilhados continuam privados.

Para carregar a demonstração local, após aplicar as migrations:

```bash
docker compose restart queue
docker compose exec app php artisan db:seed --class=FeedDemoSeeder
```

Entre com **`ana@feed-demo.example.test`**, senha **`FeedDemo123!`**. O worker processará os EPUB/PDF sintéticos e publicará as atividades. O seeder cria apenas contas de demonstração, pode ser repetido sem duplicar os registros originais e não envia e-mails. Seguidores, contratos, estados e demais contas: [feed e demonstração](docs/development/feed.md).

## Backend da biblioteca

Os endpoints JSON de [coleções](docs/development/collections.md) e [livros](docs/development/books.md) usam a sessão autenticada e proteção CSRF. Cada usuário acessa somente seus registros. O CRUD de livros permite manter os metadados e associá-los a uma coleção própria; o [upload de EPUB/PDF](docs/development/book-uploads.md) recebe um arquivo por envio, até 50 MB, e extrai metadados em fila.

A interface de livros está em [Meus livros](http://localhost:8001/books), com biblioteca em cartões e [adição de livros](http://localhost:8001/books/create), com upload como opção principal e cadastro manual em uma aba secundária. Os cartões mostram o andamento do processamento e atualizam título, autoria e capa automaticamente. Quando não há capa disponível, usam uma substituta tipográfica. A biblioteca permite criar coleções por modal, navegar por accordion com paginação por grupo e organizar individualmente os livros. Também é possível escolher ou criar uma coleção durante o cadastro.


## Avaliações de livros

O menu **Ações** de cada card reúne **Avaliar livro**, **Organizar** e **Enviar ao Kindle**. Avaliações aceitam meias estrelas de 0 a 5 e comentário opcional. Cada salvamento cria uma versão, consultável na aba **Histórico**. O modo **Por classificação** agrupa os livros por faixa da nota mais recente, com contadores e paginação, incluindo **Sem avaliação**. Aplique as migrations antes de usar. Detalhes: [avaliações e classificação](docs/development/book-ratings.md).

## Arquivos e processamento local

Originais são armazenados no disco privado local (`storage/app/private`), conforme a decisão inicial de desenvolvimento. O serviço `queue` processa os metadados e a limpeza de arquivos excluídos; ele inicia pelo Docker Compose. EPUBs usam `kiwilan/php-ebook`; a imagem PHP inclui GD para tratar capas e `poppler-utils` para PDF.

Depois de atualizar este incremento:

```bash
docker compose up --build -d
docker compose exec app php artisan migrate
```

Detalhes, estados e limitações: [upload de livros](docs/development/book-uploads.md).


## POC Send to Kindle

Os cartões permitem solicitar o envio do arquivo original ao Kindle por SMTP real do Gmail, em fila. O destino temporário usa `KINDLE_EMAIL` no `.env`; as credenciais usam `KINDLE_SMTP_USERNAME` e `KINDLE_SMTP_PASSWORD`, com fallback para `MAIL_USERNAME` e `MAIL_PASSWORD`. O Gmail remetente precisa estar autorizado na conta Amazon.

O limite desta POC é de 18 MB por arquivo. A interface acompanha fila, tentativa e aceite SMTP, sem afirmar entrega ao dispositivo. Uma nova tabela registra as tentativas; aplique as migrations antes de usar a ação. Não há envio automático ao instalar. Detalhes: [POC Kindle](docs/development/kindle-poc.md).
