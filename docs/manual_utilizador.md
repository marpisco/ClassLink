# Manual de Utilizador — ClassLink

Bem-vindo ao **ClassLink**, a plataforma digital de reserva de salas e materiais.

---

## Índice

- [Acesso à Plataforma](#acesso-à-plataforma)
- [Dashboard Principal](#dashboard-principal)
- [Reservar uma Sala](#reservar-uma-sala)
- [As Minhas Reservas](#as-minhas-reservas)
- [Estados das Reservas](#estados-das-reservas)
- [Materiais](#materiais)
- [Perguntas Frequentes](#perguntas-frequentes)

---

## Acesso à Plataforma

O ClassLink utiliza autenticação via **OAuth2** integrada com o sistema escolar (GIAE/Authentik).

1. Aceda ao endereço da aplicação no seu browser.
2. Clique em **Iniciar Sessão**.
3. Será redirecionado para a página de autenticação institucional.
4. Introduza as suas credenciais escolares.
5. Após autenticação, será redirecionado para o dashboard do ClassLink.

> **Nota:** A sessão expira automaticamente após **30 minutos** de inatividade.

---

## Dashboard Principal

Após o login, é apresentado o dashboard principal com as seguintes opções:

| Botão | Descrição |
|-------|-----------|
| **Reservar uma Sala** | Iniciar o processo de reserva de sala |
| **Documentação** | Aceder a este manual e outros documentos |
| **As minhas reservas** | Ver e gerir as suas reservas ativas |
| **Painel Administrativo** | Apenas visível para administradores |

---

## Reservar uma Sala

### Passo a Passo

1. Clique em **Reservar uma Sala** no dashboard ou no menu de navegação.
2. **Selecione a(s) sala(s)** que pretende reservar na lista apresentada.
3. **Selecione o(s) tempo(s)** (período horário) para a reserva.
4. **Escolha a data** pretendida.
5. Indique o **motivo** da reserva.
6. (Opcional) Selecione **materiais** a requisitar juntamente com a sala.
7. Clique em **Submeter** para confirmar.

### Tipos de Sala

| Tipo | Descrição | Aprovação |
|------|-----------|-----------|
| **Aprovação necessária** | A reserva fica pendente até um administrador aprovar | Manual |
| **Reserva autónoma** | A reserva é aprovada imediatamente | Automática |

### Salas Bloqueadas

Salas com estado **bloqueado** só podem ser reservadas por administradores.

---

## As Minhas Reservas

Na página **As minhas reservas** pode visualizar todas as suas reservas.

Use os filtros disponíveis para filtrar por:
- **Estado** (pendente, aprovada, rejeitada, cancelada)
- **Data**

Para cancelar uma reserva, clique no botão **Cancelar** na linha correspondente.

---

## Estados das Reservas

As reservas podem ter os seguintes estados:

| Estado | Descrição |
|--------|-----------|
| ⏳ **Pendente** | Aguarda aprovação de um administrador |
| ✅ **Aprovada** | Reserva confirmada |
| ❌ **Rejeitada** | Reserva recusada pelo administrador |
| 🚫 **Cancelada** | Reserva cancelada pelo utilizador |

---

## Materiais

Alguns materiais (por exemplo, projetores, extensões, etc.) podem estar associados a salas específicas.
Ao reservar uma sala, é possível requisitar materiais disponíveis nessa sala.

Os materiais requisitados são indicados na confirmação da reserva e no email de notificação (quando disponível).

---

## Perguntas Frequentes

**Quanto tempo demora a aprovação de uma reserva?**  
Depende da disponibilidade do administrador. Receberá um email de notificação quando a reserva for aprovada ou rejeitada (se o sistema de email estiver configurado).

**Posso fazer reservas para vários tempos no mesmo dia?**  
Sim. No formulário de reserva, pode selecionar múltiplos tempos para o mesmo dia.

**Posso reservar a mesma sala em dias diferentes de uma só vez?**  
Não, cada reserva é para um único dia. Para dias diferentes, deve fazer reservas separadas.

**O que acontece se a minha sessão expirar durante uma reserva?**  
A sessão expira após 30 minutos de inatividade. Se isso acontecer durante o preenchimento, terá de iniciar sessão novamente e repetir o processo.

**Como cancelo uma reserva aprovada?**  
Aceda a **As minhas reservas**, localize a reserva e clique em **Cancelar**.

---

*Para questões adicionais, contacte o administrador da plataforma.*
