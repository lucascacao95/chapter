# ADR 0001: avaliações pessoais versionadas

- Data: 2026-11-12
- Status: aceito

## Contexto

A avaliação de uma leitura pode mudar. O produto precisa manter as notas e comentários anteriores e permitir navegar pela classificação atual, inclusive para livros cadastrados sem arquivo.

## Decisão

Cada salvamento insere uma `BookRating`. A aplicação não altera nem exclui versões individualmente. O maior ID do livro define a avaliação atual, inclusive quando timestamps coincidem; não há média entre versões.

A nota aceita passos de meia estrela entre 0 e 5. Zero é diferente de ausência de avaliação. O arquivo é opcional e sua referência é capturada no momento do registro. O servidor define os vínculos do livro, usuário e arquivo.

A biblioteca agrupa pela faixa da última nota. Histórico e contadores são privados ao proprietário. A exclusão do livro ou da conta também exclui as avaliações; a retenção não ultrapassa a vida desses registros. Arquivos referenciados não podem ser excluídos isoladamente.

## Consequências

O histórico cresce a cada salvamento e exige paginação. Índice por livro e ID, relação da última versão e agregação no banco evitam carregar o histórico inteiro para renderizar cards ou contar faixas. Os endpoints não oferecem update/delete de avaliações e o model impede essas operações por instância; SQL direto deve respeitar a mesma regra e não é protegido por esses eventos.

Não são necessários event sourcing, tabela separada de notas atuais, médias, serviços externos ou novas dependências.


## Evolução

A ADR 0002 permite compartilhar versões específicas por consentimento explícito, usando snapshots separados no feed. O histórico da biblioteca continua privado e imutável.
