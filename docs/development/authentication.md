# Autenticação

A aplicação usa o guard `web` do Laravel, cookies de sessão e formulários Inertia. O PostgreSQL mantém usuários, sessões e tokens de recuperação nas tabelas iniciais; não há novas entidades de domínio nesta etapa.

O cadastro pede nome, e-mail e senha confirmada, autentica automaticamente e leva a `/dashboard`. Não exige confirmação de e-mail. E-mails são normalizados para minúsculas, sem espaços externos. A senha tem mínimo de oito caracteres e respeita o limite de 72 bytes do bcrypt.

O login regenera a sessão e respeita o destino protegido solicitado. O logout aceita somente POST, encerra a autenticação, invalida a sessão e renova o token CSRF. A página inicial permanece pública.

A recuperação usa o password broker do Laravel: token armazenado como hash, expiração de 60 minutos, uso único e intervalo de 60 segundos entre emissões para a mesma conta. A resposta pública não informa se o e-mail existe. Após redefinir, o usuário precisa entrar com a nova senha.

Limites por minuto: cinco tentativas de login por e-mail/IP, cinco cadastros por IP e três solicitações de recuperação por IP. Erros de formulário e mensagens do e-mail aparecem em pt-BR. As props compartilhadas expõem somente ID, nome e e-mail do usuário.

O Mailpit captura o SMTP no ambiente Docker, sem envio externo. Links usam `APP_URL`; a caixa fica em http://localhost:8025. O transporte de produção deve ser configurado no ambiente de implantação.

Os testes PHPUnit usam SQLite em memória e notificações simuladas, sem dependência de SMTP ou acesso aos dados de desenvolvimento. O TypeScript está em 5.9, compatível com o `vue-tsc` configurado.
