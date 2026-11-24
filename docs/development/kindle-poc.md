# POC de envio ao Kindle por e-mail

O cartão oferece **Enviar ao Kindle** e pede confirmação em um modal shadcn-vue. A aplicação envia o original EPUB ou PDF como anexo real por SMTP do Gmail, em um job da fila `database`. Não há transporte de teste ou fallback para Mailpit nesse fluxo.

## Configuração

`KINDLE_EMAIL` no `.env` define o único destino global desta POC. O endereço informado pelo usuário foi configurado somente no `.env` local, ignorado pelo Git. Esta é uma limitação deliberada do ambiente pessoal: todos os usuários autenticados usam esse mesmo destino para enviar seus próprios livros. Não há cadastro de dispositivos nem destinos personalizados por usuário.

O mailer separado `kindle` usa `smtp.gmail.com:587`, STARTTLS obrigatório e timeout SMTP de 30 segundos. O remetente é o próprio usuário autenticado no SMTP. E-mails locais de autenticação continuam usando o transporte existente.

```dotenv
KINDLE_EMAIL=seu-dispositivo@kindle.com
KINDLE_SMTP_USERNAME=seu-email@gmail.com
KINDLE_SMTP_PASSWORD=sua-senha-de-app
```

Quando `KINDLE_SMTP_USERNAME` e `KINDLE_SMTP_PASSWORD` não estão definidos, o mailer aproveita `MAIL_USERNAME` e `MAIL_PASSWORD` do `.env`. Defini-los como vazios desabilita esse fallback. Não colocar credenciais no Git, em logs ou no chat.

O Gmail exige autenticação compatível, como senha de aplicativo com verificação em duas etapas. O mesmo endereço remetente precisa estar na lista de e-mails aprovados para documentos pessoais da Amazon. O cadastro dessa autorização não é automatizado pela aplicação.

Depois de alterar credenciais ou destino:

```bash
docker compose exec app php artisan config:clear
docker compose restart queue
```

A POC permite anexos de até **18.000.000 bytes (18 MB)**, um por mensagem. É um limite conservador da aplicação, para reservar espaço para MIME/Base64 diante do limite de 25 MB documentado para anexos no Gmail pessoal. O limite de upload de 50 MiB da biblioteca permanece independente. Não são usados links do Google Drive ou URLs públicas de livros.

## Persistência e API

Nova tabela `kindle_deliveries`, model `KindleDelivery`:

| Campo | Finalidade |
| --- | --- |
| `id` | Identificador numérico do envio. |
| `book_file_id` | Arquivo obrigatório, com índice e chave estrangeira com cascata. |
| `idempotency_key` | UUID único fornecido pelo cliente para identificar uma solicitação e repetir a consulta sem reenviar. Não exposto na leitura. |
| `recipient` | Destino capturado na solicitação. Não exposto na API; mudar o `.env` não redireciona trabalhos já enfileirados. |
| `status` | `queued`, `processing`, `submitted` ou `failed`. |
| `error_code` | Código controlado opcional: `send_unavailable` ou `submission_unknown`. |
| `submitted_at` | Aceite do e-mail pelo SMTP, quando conhecido. |
| `created_at`, `updated_at` | Timestamps padrão. |

`POST /api/books/{book}/kindle`, autenticado e protegido por CSRF, recebe `{"idempotency_key":"UUID"}` e responde `202` com `data: {id, status, error_code, submitted_at}`. A policy do livro retorna `404` para terceiros. Não aceita um endereço de destino do cliente. Há limite de dez solicitações por minuto por usuário.

Erros: `401` sem sessão, `419` para CSRF, `422` para solicitação inválida ou envio indisponível e `429` para excesso de solicitações. Coleções não interferem no envio.

Livro e arquivo são bloqueados na transação antes da criação do envio. Repetir uma chave para o mesmo arquivo retorna o registro existente, inclusive se já terminou. Uma nova chave enquanto há envio ativo retorna o envio ativo. Uma solicitação após o término, com nova chave e confirmação do usuário, cria um novo envio. Usar a chave de outro arquivo gera erro genérico de validação.

A criação do registro e a inclusão do job usam a mesma transação e conexão de banco da fila existente. O worker somente vê o trabalho após o commit. O job revalida configuração, arquivo, formato, processamento e tamanho antes de submeter o anexo. Arquivos ausentes, sem processamento concluído ou acima do limite não são enviados.

As respostas de livros acrescentam `kindle: {configured, max_attachment_bytes, delivery}`. `delivery` é o resumo do envio mais recente ou `null`. Não são retornados destinatário nem credenciais. A listagem carrega essa relação antecipadamente, sem uma consulta adicional por cartão. O endpoint existente `GET /api/books/{book}` também permite consultar o andamento.

## Execução e estados

O serviço Docker `queue` existente executa o job `SendBookToKindle`; não é necessário iniciar um worker adicional. O original é copiado por stream para um temporário privado de tamanho limitado, anexado como EPUB/PDF e removido após a tentativa. O arquivo da biblioteca permanece intacto.

- `queued`: solicitação aceita e aguardando o worker.
- `processing`: o worker assumiu a tentativa de envio.
- `submitted`: Gmail aceitou o e-mail. A interface pede que o usuário confira a chegada ao Kindle.
- `failed`: não foi possível concluir ou confirmar a tentativa. `submission_unknown` indica que pode ter havido aceite pelo SMTP; conferir o Kindle antes de solicitar um novo envio.

O job tem uma única tentativa e timeout de 60 segundos. Não há repetição automática de SMTP, pois um timeout pode ocorrer depois do aceite e causar duplicatas se houver reenvio. Entregas já assumidas ou encerradas não são submetidas outra vez pelo mesmo job. Uma falha definitiva antes do envio exige nova solicitação explícita. O recebimento pela Amazon e a chegada ao dispositivo não são confirmados automaticamente por esta POC.

Os cartões bloqueiam novas solicitações enquanto há um envio ativo e atualizam o andamento pela consulta periódica de três segundos da página ativa. O cadastro manual sem arquivo mantém a ação indisponível com explicação. A chave da solicitação é preservada em falhas de rede enquanto o componente permanece montado.

A exclusão do arquivo/livro remove os registros de envio por cascata. Um job que não encontra mais o registro é ignorado. Um envio que já foi submetido ao SMTP não pode ser cancelado por exclusão local. Não há histórico de envios na interface, webhooks, leitura de respostas da Amazon ou cadastro de dispositivos neste incremento.

## Preparação e verificações

```bash
docker compose exec app php artisan migrate
docker compose logs -f queue
```

A criação da estrutura não dispara nenhum e-mail. O envio real depende da confirmação do usuário pelo cartão. Verificações de comportamento futuras devem usar transportes falsos para autorização, tamanho, concorrência, repetição da chave e falhas; um teste real deve ser solicitado explicitamente e conferido no Kindle. Nunca usar credenciais reais na suíte automatizada.

Referências: [SMTP do Gmail](https://support.google.com/mail/answer/7104828), [senhas de aplicativo](https://support.google.com/accounts/answer/185833), [limites de anexos do Gmail](https://support.google.com/mail/answer/6584) e [requisitos do Send to Kindle](https://digprjsurvey.amazon.co.uk/csad/help/node/G7NECT4B4ZWHQ8WV).
