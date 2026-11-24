# Entidade BookFile

**Status: modelo aprovado e implementado para upload inicial.**

Representa o arquivo original EPUB ou PDF associado a um livro. `Book` continua responsável pelos dados bibliográficos e pela coleção; `BookFile` guarda as informações técnicas do arquivo e o acompanhamento da extração de metadados.

Tabela: `book_files`. Model: `App\Models\BookFile`.

## Relacionamentos e escopo

- Um `Book` pode ter zero ou um `BookFile` nesta etapa. Livros cadastrados manualmente continuam válidos sem arquivo.
- Cada `BookFile` pertence obrigatoriamente a um `Book`.
- `book_id` terá restrição de unicidade para garantir um arquivo por livro no banco.
- O proprietário é obtido por `Book.user_id`; não duplicar `user_id` nesta tabela.
- A coleção pertence ao livro. O arquivo não recebe um vínculo próprio com coleção.
- A entidade representa somente o original enviado. Capa extraída continua usando a referência `books.cover_path` já existente.

## Campos propostos

| Campo | Tipo / obrigatoriedade | Finalidade |
| --- | --- | --- |
| `id` | Identificador numérico, obrigatório | Seguir o padrão das entidades existentes. |
| `book_id` | Chave estrangeira, obrigatória e única | Referenciar `books.id` e garantir um único arquivo por livro. |
| `original_name` | Texto de até 255 caracteres, obrigatório | Preservar o nome recebido, incluindo a extensão, apenas para apresentação e referência. Nunca usar como caminho de armazenamento. |
| `disk` | Texto de até 64 caracteres, obrigatório | Nome do disco Laravel que contém o arquivo privado. Permite localizar o objeto independentemente do disco padrão da aplicação. |
| `storage_key` | Texto de até 1024 caracteres, obrigatório | Chave interna gerada pelo servidor para o original. Não guardar uma URL pública ou temporária. |
| `format` | Texto de até 10 caracteres, obrigatório | Formato validado: `epub` ou `pdf`. Não confiar apenas na extensão enviada. |
| `mime_type` | Texto de até 255 caracteres, obrigatório | MIME identificado pelo servidor como parte da validação; não é suficiente sozinho para aceitar o arquivo. |
| `size_bytes` | Inteiro de 64 bits, obrigatório e maior que zero | Tamanho real do objeto recebido, em bytes. |
| `processing_status` | Texto de até 20 caracteres, obrigatório | Estado da extração: `pending`, `processing`, `completed` ou `failed`. Valor inicial: `pending`. |
| `processing_error_code` | Texto de até 64 caracteres, opcional | Código controlado da última falha de extração. A interface traduz o código para uma mensagem em português; não persistir stack traces ou conteúdo do arquivo neste campo. |
| `metadata_overrides` | JSON opcional, uso interno | Lista de campos bibliográficos editados explicitamente pelo usuário; impede que a extração sobrescreva essas escolhas, inclusive valores limpos com `null`. |
| `processed_at` | Timestamp opcional | Data da última conclusão da extração com sucesso, mesmo quando nenhum metadado útil foi encontrado. |
| `created_at`, `updated_at` | Timestamps obrigatórios | Padrão do Laravel. |

Campos técnicos e estados são definidos pelo servidor. O cliente não pode escolher proprietário, chave de armazenamento ou resultado do processamento. Caminhos internos não devem ser expostos nas respostas públicas da aplicação.

## Significado dos estados

| Estado | Significado |
| --- | --- |
| `pending` | O original foi recebido, validado e armazenado; aguarda extração. |
| `processing` | A extração de metadados está em andamento. |
| `completed` | A tentativa terminou normalmente. Pode ter produzido todos, alguns ou nenhum metadado. |
| `failed` | Um erro impediu a conclusão da extração. O original permanece armazenado e o livro conserva os dados disponíveis. |

Fluxo normal: `pending → processing → completed`. Em erro: `processing → failed`. Uma nova tentativa pode retornar a `pending`, limpando o erro anterior e `processed_at`. O processamento deve tolerar a execução repetida do mesmo trabalho.

Ausência de autor, capa ou título não é falha de processamento. Esses estados também não representam upload ou entrega a um dispositivo.

## Regras de criação e metadados

1. Validar tamanho, formato e estrutura do arquivo antes de aceitá-lo. Arquivos rejeitados não originam um livro com upload concluído.
2. Armazenar o original em disco privado com uma chave gerada pelo servidor.
3. Criar `Book`, `BookFile` e o trabalho de fila em uma transação de banco, com título inicial derivado do nome do arquivo sem a extensão e extração em `pending`.
4. Executar a extração de forma assíncrona após a confirmação da transação.
5. Quando houver título bibliográfico útil, ele substitui o título de reserva. Os demais metadados aproveitam os campos já existentes em `Book` e suas validações.
6. Metadados ausentes permanecem opcionais. Não inferir autor, editora ou gênero pelo nome do arquivo.
7. Alterações explícitas do usuário têm prioridade sobre a extração, inclusive quando houver processamento em andamento ou repetido. O controle interno `metadata_overrides` registra os campos editados para garantir essa prioridade.

Uma falha ao armazenar o original é falha de upload, não `processing_status = failed`. Como o armazenamento de objetos não participa da transação PostgreSQL, o fluxo precisa compensar falhas entre os dois, evitando objetos órfãos e registros que apontem para objetos não gravados.

## Acesso e exclusão

- Toda consulta, download ou processamento deve respeitar o proprietário do livro. Conhecer o ID do arquivo não concede acesso.
- O original é privado; eventual download exige autorização e um mecanismo temporário ou resposta autenticada.
- Excluir o livro remove seu registro `BookFile` por chave estrangeira com cascata. O fluxo da aplicação agenda a remoção do objeto original em fila, com repetição em caso de falha. A limpeza inclui também a capa extraída, armazenada no mesmo disco privado do original.
- Excluir um usuário exige o mesmo cuidado: a cascata no banco não remove os objetos do armazenamento.
- Excluir uma coleção não exclui livros nem arquivos, preservando a regra existente.

## Fora deste primeiro modelo

- Múltiplos arquivos ou formatos por livro, substituição e histórico de versões.
- OCR, conversão de formatos e consulta a catálogos externos.
- Entidades separadas para metadados, capas ou tentativas de processamento.
- Detecção automática de duplicados por hash. Reenvios não serão combinados automaticamente nesta etapa.
- Entrega ao Kindle ou outros dispositivos.

Limites aprovados: um arquivo por envio, até 50 MiB (apresentado como 50 MB na interface). No desenvolvimento, usar o disco privado local por decisão do usuário.

## Impacto no modelo existente

Nenhum campo bibliográfico precisa sair de `books`. Os livros existentes permanecem sem `BookFile` até receberem um arquivo em um fluxo futuro explicitamente definido. A criação principal por upload e a edição dos metadados aproveitarão a biblioteca e as coleções atuais.

O [fluxo de upload](../development/book-uploads.md) descreve a implementação, o processamento, a limpeza e os limites do incremento atual. A extração agora utiliza `kiwilan/php-ebook` para EPUB e aproveita `books.cover_path`, sem novas colunas. O download do original ainda não está implementado.
