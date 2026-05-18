# Inventário de mensagens do ClassLink

Documento de apoio à revisão das mensagens exibidas/enviadas pelo sistema.

> As mensagens abaixo estão normalizadas para facilitar a revisão de legibilidade e consistência.

## 1) Autenticação e sessão

### `login/index.php`
- `⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento`
- `Bem-vindo ao ClassLink pela primeira vez! Valide o código que recebeu no email para criar a sua conta.`
- `Introduza o código que recebeu no email para validar-se.`
- `Erro ao criar utilizador. Tente novamente.`
- `Código inválido ou expirado. Peça um novo código.`
- `Sessão expirada. Por favor tente novamente.`
- `Por favor introduza o seu nome.`
- `A sessão expirou. Por favor inicie sessão de novo.`
- `Não foi possível validar o TOTP. Contacte o administrador do sistema.`
- `Código TOTP inválido. Por favor tente novamente.`
- `Acesso Bloqueado`
- `Não tem permissão para aceder a esta plataforma. Contacte o administrador do sistema.`
- `Sem permissão`
- `Não tem autorização para entrar nesta página.`
- `Iniciar Sessão no ClassLink`
- `Terminou sessão`
- `Caso pretenda voltar a iniciar sessão, carregue no botão em baixo.`
- `Verificação de Segurança`
- `Introduza o código do seu autenticador para prosseguir.`
- `Complete o seu perfil`
- `Por favor, introduza o seu nome completo.`
- `Configurar Autenticador`
- `Escaneie o código QR com a sua aplicação de autenticação ou introduza o código manualmente.`
- `Código manual:`

### `index.php`, `reservar/manage.php`, `admin/relatorios.php`
- `A reencaminhar para iniciar sessão...`
- `Não pode entrar no Painel Administrativo. Voltar para a página inicial`

## 2) Reservas

### `reservar/index.php`
- `Sala Bloqueada: Esta sala encontra-se bloqueada. Como administrador, pode criar reservas.`
- `Sala Bloqueada: Esta sala está bloqueada.`
- `Reserva Autónoma: Esta sala é de reserva autónoma. A sua reserva será aprovada automaticamente.`
- `Reserva Autónoma: Esta sala é de reserva autónoma, mas como utilizador externo, a sua reserva necessita de aprovação por um administrador.`
- `Pendente`
- `Ocupado`
- `Livre`
- `Reservas em Massa`
- `Reservar para utilizador (ADMIN):`
- `Reservar para mim mesmo`
- `Motivo da Reserva`
- `Informação Extra`
- `Materiais Disponíveis (opcional):`
- `Reservar Selecionados`
- `Limpar Seleção`

### `reservar/manage.php`
- `Motivo é obrigatório.`
- `Nenhum tempo foi selecionado.`
- `Já reservado`
- `Sala não encontrada`
- `Sala bloqueada`
- `Data no passado`
- `Houve um problema a reservar a sala. Contacte um administrador, ou tente novamente mais tarde.`
- `Não tem permissão para apagar esta reserva.`
- `Não é possível apagar reservas no passado. Apenas os administradores podem apagar reservas em datas passadas.`
- `Houve um problema a apagar a reserva. Contacte um administrador, ou tente novamente mais tarde.`
- `Reservas Aprovadas!`
- `reserva(s) criada(s) com sucesso e aprovadas automaticamente.`
- `Reservas Submetidas!`
- `reserva(s) criada(s) com sucesso e submetidas para aprovação.`
- `Algumas reservas falharam:`
- `Informações Importantes - {Sala}`

### `func/email_helper.php` (emails de reserva)
- Criação:
  - `Confirmação de Reserva da Sala`
  - `Reserva Submetida`
  - `a aguardar aprovação`
  - `Informamos que a sua reserva foi criada com sucesso.`
  - `A sua reserva foi submetida com sucesso...`
- Aprovação:
  - `Reserva Aprovada`
  - `Temos boas notícias! A sua reserva foi aprovada.`
- Rejeição:
  - `Reserva Rejeitada`
  - `Lamentamos informar que a sua reserva foi rejeitada.`
- Remoção:
  - `Reserva Removida`
  - `Informamos que a sua reserva foi removida...`
- Mensagens comuns:
  - `Ver Detalhes da Reserva`
  - `Fazer Nova Reserva`
  - `Ver as minhas reservas`
  - `Este email foi enviado automaticamente pelo sistema ClassLink. Não responda a este email.`

## 3) Gestão de pedidos

### `admin/pedidos.php`
- `Parâmetros inválidos.`
- `Reserva Aprovada com Sucesso!`
- `O utilizador será notificado por email sobre a aprovação.`
- `A reserva foi aprovada, mas o email de notificação não foi enviado. Contacte o Postmaster.`
- `Reserva Rejeitada`
- `O utilizador foi notificado por email sobre a rejeição.`
- `A reserva foi rejeitada, mas o email de notificação não foi enviado. Contacte o Postmaster.`
- `Nenhuma reserva selecionada.`
- `Dados inválidos.`
- `Aprovações em Massa Concluídas!`
- `reserva(s) aprovada(s) com sucesso.`
- `reserva(s) falharam.`
- `Algumas reservas foram aprovadas mas os emails não foram enviados:`
- `Rejeições em Massa Concluídas`
- `reserva(s) rejeitada(s).`
- `Algumas reservas foram rejeitadas mas os emails não foram enviados:`
- `Nenhum pedido encontrado`
- `Não existem pedidos pendentes para os filtros selecionados.`

## 4) Administração

### `admin/config.php`
- `Configurações guardadas com sucesso!`
- `Erro ao guardar configurações.`
- `Por favor, insira uma regex para testar.`
- `Introduza um email para testar a regex:`
- `O email "{email}" CORRESPONDE à regex. Será BLOQUEADO.`
- `O email "{email}" NÃO corresponde à regex. Será PERMITIDO.`
- `Erro na regex: ...`

### `admin/users.php`
- `ID inválido.`
- `Erro: Existem reservas associadas a este utilizador. Por segurança, é necessária uma intervenção manual.`
- `Utilizador não encontrado.`
- `Dados inválidos.`
- `TOTP removido com sucesso.`
- `Nome e Email são obrigatórios.`
- `Formato de email inválido.`
- `Já existe um utilizador com este email.`
- `Utilizador pré-adicionado com sucesso.`
- `Erro ao pré-adicionar utilizador.`
- `Não deve efetuar nenhuma ação presente nesta página sem consultar o manual do Administrador.`
- `Não existem utilizadores.`
- `Nenhum utilizador encontrado.`

### `admin/salas.php`
- `Dados inválidos.`
- `ID inválido.`
- `Erro: Existem reservas associadas a esta sala. Por segurança, é necessária uma intervenção manual.`
- `Sala não encontrada.`
- `A editar a Sala ...`
- `Tipo de sala inválido.`
- `Estado de sala inválido.`
- `Não existem salas.`
- `Erro ao carregar salas.`

### `admin/tempos.php`
- `Dados inválidos.`
- `ID inválido.`
- `Erro: Existem reservas associadas a este tempo. Por segurança, é necessária uma intervenção manual.`
- `Tempo não encontrado.`
- `A editar o Tempo ...`
- `Não existem tempos.`
- `Erro ao carregar tempos.`

### `admin/materiais.php`
- `Erro ao fazer upload do ficheiro.`
- `Linha {n} inválida: ...`
- `Sala não encontrada para material '{nome}': ...`
- `Erro ao inserir material '{nome}': ...`
- `{n} material(ais) importado(s) com sucesso.`
- `{n} erro(s) durante a importação:`
- `Dados inválidos.`
- `Completar informações do material`
- `Material não encontrado.`
- `A editar o Material ...`
- `Não existem materiais.`

### `admin/salas_postreserva.php`
- `ID inválido.`
- `Sala não encontrada.`
- `A editar a Página Pós-Reserva da Sala ...`
- `Dados inválidos.`
- `Não existem salas.`
- `Erro ao carregar salas.`

### `admin/registos.php`
- `Não existem registos.`
- `Erro ao carregar registos.`
- `Mostrar IPs`
- `Ocultar IPs`
- `Oculto`
- `Todos os registos foram carregados.`

## 5) Scripts administrativos

### `admin/scripts/notifyemail.php`
- `Por favor, preencha o assunto e a mensagem antes de visualizar.`
- `Por favor, selecione o tipo de destinatários.`
- `Não foi possível obter a lista de destinatários. Por favor, tente novamente.`
- `Por favor, preencha o assunto e a mensagem.`
- `Tem a certeza que deseja enviar este email para todos os destinatários?`
- `O modo, assunto e a mensagem são obrigatórios.`
- `Não existem destinatários para enviar o email usando os filtros que selecionou.`
- `O sistema de email não está ativado.`
- `Sucesso! Email enviado com sucesso para {n} destinatário(s) em BCC.`
- `Resumo:`
- `Erro ao enviar email: ...`

### `admin/scripts/semanasrepetidas.php`
- `Por favor, selecione um utilizador.`
- `Deve selecionar pelo menos um tempo.`
- `O campo Motivo é obrigatório.`
- `A data de fim deve ser igual ou posterior à data de início.`
- `Sala ou utilizador inválido.`
- `O dia da semana selecionado não ocorre dentro do intervalo de datas especificado. Nenhuma reserva foi criada.`
- `Sucesso! {n} reserva(s) criada(s) com sucesso.`
- `Atenção: {n} reserva(s) já existia(m) e não foi/foram criada(s).`
- `Erros encontrados:`
- `Resumo:`

## 6) APIs JSON

### `admin/api/recipients_preview.php`
- `Acesso negado.`
- `Modo de email em falta ou inválido`

### `admin/api/dashboard_stats.php`
- `Não autorizado.`

### `admin/api/api_registos.php`
- `Não autorizado.`

### `admin/api/salas_search.php`
- `Acesso negado.`

### `admin/api/tempos_search.php`
- `Acesso negado.`

### `admin/api/users_search.php`
- `Acesso negado.`

### `admin/index.php`
- `⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento`
- `Erro ao carregar estatísticas.`

## 7) Observações para revisão

- O texto de sucesso do `notifyemail.php` é demasiado detalhado para o utilizador final.
- Há várias mensagens de erro técnicas que mostram detalhes internos (`$e->getMessage()` / `$stmt->error`); idealmente devem ser registradas no servidor e substituídas por mensagens genéricas para o utilizador.
- Algumas mensagens estão repetidas em variantes curtas/longas; vale a pena normalizar vocabulário e tom antes da próxima atualização.
