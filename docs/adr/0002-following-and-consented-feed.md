# ADR 0002: feed de atividades com consentimento por ação

- Data: 2026-11-12
- Status: aceito

## Contexto

A biblioteca e as avaliações são privadas. A página inicial passará a mostrar atividades próprias e de usuários seguidos, sem compartilhar arquivos ou introduzir posts livres.

## Decisão

Usar uma relação direcional `user_follows` e uma tabela própria `feed_activities`, consultada pela audiência atual. Não criar notificações por destinatário, event bus ou infraestrutura adicional. Telas e APIs de seguir ficam para outra etapa.

Upload, cadastro manual e cada versão de avaliação exigem consentimento independente, desligado por padrão. Dados anteriores não são publicados retroativamente. A atividade pendente e seu job na fila de banco são gravados na transação da origem; o worker só os consome após commit.

Toda atividade de livro com arquivo aguarda processamento bem-sucedido. Na publicação, armazenar snapshot dos dados e cópia privada da capa. Compartilhar informações não concede acesso ao livro privado ou ao arquivo.

Versões compartilhadas são independentes, coerentes com a ADR 0001. Uma versão privada posterior não retira anteriores. Retirada do feed mantém registro invisível e limpa a capa; exclusão do livro/conta remove suas atividades. Jobs não restauram atividades retiradas.

## Consequências

A publicação tem consistência eventual e precisa de estados explícitos. Chave única por origem e bloqueios garantem idempotência. Cópias das capas consomem armazenamento e precisam de limpeza assíncrona, mas preservam a aparência publicada e isolam a autorização do feed.

O feed usa cursor por data de publicação e ID, não paginação por offset. Seguidores atuais veem o histórico compartilhado e deixam de acessá-lo nas próximas requisições ao desfazer o vínculo. Não há invalidação remota do conteúdo já exibido.
