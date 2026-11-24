# Entidade KindleDelivery

Tentativa de envio de um arquivo existente para o Kindle por e-mail. Model: `App\Models\KindleDelivery`. Tabela: `kindle_deliveries`.

Pertence obrigatoriamente a um `BookFile`, que pode ter várias tentativas ao longo do tempo. O proprietário é obtido pelo livro do arquivo. Somente o dono do livro pode solicitar um envio ou consultar seu estado. A exclusão do arquivo remove seus registros de envio por cascata.

Contém identificador, vínculo com arquivo, UUID único de idempotência, destinatário capturado na solicitação, estado, código controlado de erro, data de aceite pelo SMTP e timestamps. Destinatário e UUID são internos. O resumo mais recente é exposto junto ao livro.

Estados: `queued`, `processing`, `submitted` e `failed`. `submitted` representa somente o aceite SMTP, sem afirmar recebimento no Kindle. Não há estado de entrega confirmada nesta POC.

A criação de tentativas usa transação e lock no livro e arquivo para impedir envios simultâneos. A repetição de uma mesma chave não cria um novo trabalho. O envio SMTP não é repetido automaticamente quando o resultado é incerto.

Campos, configuração e contrato HTTP estão em [POC Kindle](../development/kindle-poc.md).
