# Upload de livros

A biblioteca oferece **Adicionar livro**, um modal shadcn-vue com **Enviar arquivo** como aba inicial e **Cadastro manual** como alternativa. `/books/create` reutiliza o mesmo formulário. Alternar as abas preserva os campos durante a abertura; fechar o modal descarta o formulário. Durante o envio, as abas, o fechamento e a submissão duplicada ficam bloqueados.

## Contrato HTTP

`POST /api/books/upload`, com sessão autenticada, proteção CSRF e `Accept: application/json`. Envie `multipart/form-data` com:

- `file`: um arquivo EPUB ou PDF obrigatório, não vazio, com até 50 MiB (52.428.800 bytes), apresentado na interface como 50 MB.
- `collection_id`: coleção opcional do mesmo proprietário.

A resposta `201` contém `data` com o livro criado e o resumo de seu `file`. Metadados ausentes são opcionais; o título inicial usa o nome sem a extensão. O arquivo não é anexado a um livro manual existente: este endpoint cria um novo livro.

Há limite de 30 solicitações por minuto. Erros usuais: `401` sem sessão, `419` para CSRF, `422` para arquivo ou coleção inválidos, `413` acima do limite de corpo HTTP e `429` para excesso de solicitações. A API não aceita nome de disco, chave de armazenamento, proprietário ou estados definidos pelo cliente.

A listagem, consulta e escrita de livros incluem `file: null` nos cadastros manuais ou o objeto com `id`, `original_name`, `format`, `size_bytes`, `processing_status`, `processing_error_code` e `processed_at`. Disco, chave, MIME interno e campos de controle não são expostos. As respostas também incluem `cover_url`, uma rota autenticada da capa ou `null`. Nenhuma URL de download do original é criada nesta etapa.

## Validação e armazenamento

A extensão aceita deve combinar com o MIME identificado no servidor e a estrutura lida pelo parser. PDF usa `pdfinfo` com tempo máximo de 15 segundos; arquivos criptografados são rejeitados. EPUB exige ZIP consistente, marcador `mimetype`, container, pacote OPF, manifesto e spine. Há limites de 10.000 entradas, 200 MiB declarados descompactados, 8 MiB por entrada e 2 MiB por XML lido. Os arquivos XML/OPF são verificados antes da biblioteca de extração. Referências do manifesto devem existir dentro do ZIP. Nenhuma entrada é extraída para um caminho controlado pelo arquivo.

XML não aceita DTD ou entidades e é lido sem acesso à rede. EPUB com entradas criptografadas ou `META-INF/encryption.xml` é rejeitado conservadoramente, incluindo arquivos que usam ofuscação de fontes. Isso não é uma certificação completa de conformidade EPUB nem uma verificação antivírus.

Conforme a decisão para desenvolvimento, originais usam o disco privado `local`, em `storage/app/private/originals/{user_id}/{uuid}.{format}`. Não criar link público para essa pasta. `BOOKS_DISK` pode selecionar outro disco privado; usar S3 posteriormente exige configurar o disco, credenciais e seu adaptador Flysystem. A migração de objetos locais existentes não é automática.

A gravação usa streams. Erros de armazenamento ou da transação removem o objeto enviado. Livro, arquivo e trabalho da fila são inseridos na mesma transação PostgreSQL, pela conexão de fila `database` existente. A configuração dessa conexão deve continuar usando o mesmo banco da aplicação e não adiar a inserção para depois do commit.

## Extração em fila

O serviço Docker `queue` executa `queue:work database`, com timeout de 60 segundos. Não é necessário iniciar manualmente um worker para o fluxo local.

```bash
docker compose up --build -d
docker compose exec app php artisan migrate
docker compose logs -f queue
```

EPUB usa `kiwilan/php-ebook` (versão instalada 3.0.09) para preencher os valores disponíveis de título, autores, editora, descrição, idioma, ano, ISBN e capa. A validação estrutural própria permanece antes da biblioteca. Colaboradores com papéis explícitos diferentes de autor não são adicionados automaticamente à autoria. A estimativa de páginas de EPUB oferecida pela biblioteca não é gravada em `page_count`. PDF mantém o leitor `pdfinfo` para título, autor e quantidade de páginas, e utiliza `pdftoppm` para uma miniatura da primeira página. Valores incompatíveis com os limites do cadastro são descartados individualmente. Arquivos sem metadados úteis concluem normalmente, mantendo o título de reserva.

O job `ExtractBookMetadata` recebe um `BookFile` e guarda seu ID e um retrato inicial dos metadados no payload, sem serializar o binário. O upload o insere na fila `database` na mesma transação dos registros; o worker só pode consumi-lo depois do commit.

Há até três tentativas de extração, com intervalos de 10 e 30 segundos. Entre tentativas, o estado retorna a `pending`; uma falha definitiva registra `failed` e `metadata_extraction_failed`, mantendo o original e o livro. Trabalhos concluídos não reaplicam metadados. Um lock por arquivo impede processamento simultâneo.

A lista atualiza silenciosamente a página ativa a cada três segundos enquanto houver arquivos pendentes ou em processamento. O cartão mostra o formato e um indicador visual distinto para fila e processamento, além de erros quando ocorrerem. A consulta atualiza título, autoria e `cover_url` sem recarregar a página. Se a imagem não puder ser carregada, o cartão preserva sua capa tipográfica. Não há atualização automática de grupos inativos até sua próxima consulta.

Campos bibliográficos enviados explicitamente no `PATCH` são registrados no controle interno `metadata_overrides`, mesmo quando enviados como `null`. A extração não sobrescreve esses campos. A atualização usa locks no livro e arquivo para resolver concorrência com o processamento. Alterar somente a coleção não bloqueia a extração bibliográfica.

A exclusão de livros agenda a remoção dos originais e capas em fila, com até cinco tentativas. A exclusão do usuário pelos models também preserva as referências antes da cascata para agendar a limpeza. Exclusões diretas por SQL ou query builder não disparam observers e exigem limpeza explícita. Na aplicação, operações de exclusão devem usar transação, como o endpoint de livros já faz.

## Capas privadas

O campo `books.cover_path`, que já existia, referencia a capa gerada no mesmo disco privado do original. Não houve alteração de schema. As capas usam chaves geradas pelo servidor em `covers/{user_id}/{uuid}.jpg` e são removidas junto com o original na exclusão do livro ou usuário.

JPEG, PNG e WebP incorporados ao EPUB são aceitos para capa quando têm até 8 MiB e 12 milhões de pixels. O GD decodifica e regrava a imagem como JPEG, com dimensão máxima de 1200 pixels. SVG e outros formatos não são servidos diretamente. Uma capa ausente ou inválida não impede a conclusão dos metadados.

`GET /api/books/{book}/cover` exige sessão e a policy de leitura do livro. Responde com JPEG privado, `nosniff` e `Cache-Control: private, no-store`; sem capa retorna `404`, assim como o acesso de outro proprietário. `cover_url` aponta para essa rota, sem revelar o caminho do objeto. Falhas ao gravar uma capa reexecutam o trabalho; objetos de tentativas revertidas são encaminhados para limpeza.

## Limites desta entrega

OCR, conversão de livros, associação de arquivo a livro manual existente, substituição do original, download, combinação de duplicados e botão para reprocessamento não fazem parte deste incremento. Livros sem capa disponível continuam usando a capa tipográfica. Arquivos que já estavam concluídos antes desta integração não são reprocessados automaticamente. O arquivo original é preservado sem alterações.

O Docker inclui GD com suporte a JPEG/PNG/WebP, `poppler-utils` e limites PHP de `upload_max_filesize=50M` e `post_max_size=52M`. Proxies usados fora do desenvolvimento também precisam permitir o tamanho escolhido.

Referências técnicas: [API ZipArchive do PHP](https://www.php.net/manual/en/class.ziparchive.php) e [leitura de XML no PHP](https://www.php.net/manual/en/domdocument.loadxml.php).
