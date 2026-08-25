# 15.2 Agile e Scrum

## Resumo

> **Agile** — metodologia de desenvolvimento flexível, com iteração e foco em adaptação. **Scrum** — o framework Agile mais usado.
>
> **Papéis:** Product Owner (backlog), Scrum Master (processo), Development Team (desenvolvimento). **Sprint:** em geral 2 semanas.
>
> **Eventos:** Sprint Planning, Daily Standup (15 min), Sprint Review (demo), Retrospective (conversa sobre o processo).

---

## Conteúdo

- [O que é](#o-que-é)
- [Agile Manifesto](#agile-manifesto)
- [Scrum Framework](#scrum-framework)
- [Sprint](#sprint)
- [Daily Standup](#daily-standup)
- [Sprint Review](#sprint-review)
- [Sprint Retrospective](#sprint-retrospective)
- [User Stories](#user-stories)
- [Story Points](#story-points)
- [Backlog](#backlog)
- [Kanban Board](#kanban-board)
- [Definition of Done (DoD)](#definition-of-done-dod)
- [Velocity](#velocity)
- [Dicas práticas](#dicas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Agile:**
Metodologia flexível, com entrega iterativa, adaptação e colaboração.

**Scrum:**
Framework Agile mais comum. Sprint de tamanho fixo e papéis definidos.

---

## Agile Manifesto

**4 valores:**

```
1. Pessoas e interação valem mais que processos e ferramentas
2. Produto funcionando vale mais que documentação exaustiva
3. Colaboração com o cliente vale mais que negociar contrato
4. Responder a mudança vale mais que seguir o plano original
```

**12 princípios:**

```
- Satisfazer o cliente com entrega cedo e contínua
- Mudança é bem-vinda, mesmo no fim
- Software funcionando sai com frequência (semanas, não meses)
- Negócio e devs trabalham juntos todo dia
- Simplicidade — a arte de cortar trabalho que não precisa
```

---

## Scrum Framework

**Papéis:**

```
Product Owner (PO)
- Dono do produto
- Gerencia o backlog
- Prioriza as tarefas
- Decide o que entra de funcionalidade

Scrum Master
- Facilita o processo
- Remove impedimentos
- Protege o time de interferência externa
- Garante que o Scrum seja seguido

Development Team
- Devs, testers, designers
- Time auto-organizado
- Cross-functional
- 3 a 9 pessoas
```

---

## Sprint

**O que é:**
Período fixo (em geral 2 semanas) para entregar um conjunto de funções.

**Estrutura do sprint:**

```
Sprint (2 semanas)
├─ Dia 1: Sprint Planning (4h)
├─ Daily: Daily Standup (15 min)
├─ Último dia: Sprint Review (2h)
└─ Último dia: Sprint Retrospective (1,5h)
```

**Sprint Planning:**

```
Objetivo: Definir o que entra no sprint

Participantes: Time inteiro

Resultado:
- Sprint Goal (objetivo do sprint)
- Sprint Backlog (tarefas escolhidas)
- Estimativa das tarefas (Story Points)

Exemplo:
Sprint Goal: "Implementar o sistema de pagamento"
Tasks:
- Integração com Stripe [8 SP]
- UI da página de pagamento [5 SP]
- Testes de pagamento [3 SP]
- Notificações por email [2 SP]
```

---

## Daily Standup

**Formato:**

```
Cada um responde 3 perguntas:

1. O que eu fiz ontem?
   "Terminei a integração com a API do Stripe"

2. O que eu vou fazer hoje?
   "Vou tratar os erros de pagamento"

3. Tem algum impedimento?
   "Estou esperando acesso à conta de teste do Stripe"
```

**Regras:**

```
✓ No máximo 15 minutos
✓ Todo mundo em pé (para não alongar)
✓ Não discute solução (isso é depois)
✓ Foco em progresso e blockers
✓ Pode pular se não tiver o que falar
```

---

## Sprint Review

**O que é:**
Demo do que o sprint entregou para o cliente e os stakeholders.

**Formato:**

```
1. Mostrar o que foi feito
   - Live demo no staging
   - Passar pelos cenários

2. O que não foi feito e por quê
   "Integração com PayPal ficou para depois porque..."

3. Discutir o feedback
   Product Owner e stakeholders dão feedback

4. Atualizar o Product Backlog
   Prioridades novas com base no feedback
```

---

## Sprint Retrospective

**O que é:**
Reunião do time para falar do processo e melhorar.

**Formato:**

```
1. O que foi bem? ✅
   "A comunicação com o design fluiu"
   "Code review ficou mais rápido"

2. O que dá para melhorar? 🔧
   "Muito tempo em bug de production"
   "Testes não cobrem edge cases"

3. Action items 🎯
   "Adicionar pre-commit hooks para os testes"
   "Fazer mini code review antes do PR"
```

**Formatos de retro:**

```
Start / Stop / Continue:
- Start: Começar a fazer
- Stop: Parar de fazer
- Continue: Continuar fazendo

4L's:
- Liked (gostei)
- Learned (aprendi)
- Lacked (faltou)
- Longed for (queria ter)
```

---

## User Stories

**Formato:**

```
Como um [papel]
Eu quero [feature]
Para que [benefício]

Exemplo:
Como um cliente
Eu quero salvar meus métodos de pagamento
Para que eu consiga finalizar a compra mais rápido da próxima vez
```

**Acceptance Criteria:**

```php
Story: Cadastro de usuário

Acceptance Criteria:
✓ Usuário consegue se cadastrar com email e senha
✓ Senha precisa ter no mínimo 8 caracteres
✓ Email precisa ser único
✓ Email de confirmação é enviado
✓ Usuário não entra até confirmar o email

// Testes
public function test_user_can_register()
{
    $response = $this->post('/register', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/email/verify');
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
}
```

---

## Story Points

**O que é:**
Estimativa relativa da complexidade da tarefa.

**Fibonacci scale:**

```
1 point   - Muito simples (30 min - 1h)
2 points  - Simples (2-3h)
3 points  - Médio (meio dia)
5 points  - Difícil (um dia)
8 points  - Muito difícil (1-2 dias)
13 points - Tarefa grande demais (quebrar em várias)
```

**Planning Poker:**

```
1. PO descreve a tarefa
2. Time faz perguntas
3. Cada um escolhe uma carta com a nota (escondido)
4. Todo mundo mostra ao mesmo tempo
5. Discute as notas extremas
6. Repete até chegar em consenso
```

---

## Backlog

**Product Backlog:**

```
Lista de todas as tarefas do projeto (priorizada pelo PO)

1. [High] Autenticação de usuário
2. [High] Integração de pagamento
3. [Medium] Notificações por email
4. [Low] Tema escuro
5. [Low] Exportar para PDF
```

**Sprint Backlog:**

```
Tarefas escolhidas para o sprint atual

Sprint 5 Backlog:
□ Setup da integração Stripe [8 SP]
  ├─ Criar StripeService [3 SP]
  ├─ Adicionar UI de pagamento [3 SP]
  └─ Escrever testes [2 SP]
□ Adicionar notificações por email [5 SP]
```

---

## Kanban Board

**Colunas:**

```
TODO → In Progress → Code Review → Testing → Done

Exemplo:
TODO:
- Adicionar paginação nos posts
- Corrigir o template de email

In Progress:
- Integrar Stripe (John)
- Adicionar papéis de usuário (Sarah)

Code Review:
- Processamento de pagamento (PR #123)

Testing:
- Login com 2FA

Done:
- Cadastro de usuário ✅
- Redefinição de senha ✅
```

---

## Definition of Done (DoD)

**Critérios de prontidão da tarefa:**

```
✓ Código escrito
✓ Unit tests escritos e passando
✓ Feature tests escritos e passando
✓ Code review feito
✓ Pipeline de CI/CD verde
✓ Deploy no staging
✓ Testado manualmente
✓ Documentação atualizada (se precisar)
✓ Product Owner aceitou
```

---

## Velocity

**O que é:**
Média de Story Points que o time entrega por sprint.

**Exemplo:**

```
Sprint 1: 21 SP
Sprint 2: 18 SP
Sprint 3: 24 SP
Sprint 4: 20 SP

Velocity = (21 + 18 + 24 + 20) / 4 = 20.75 SP

Uso:
"Nossa velocity é 21 SP, então no próximo
sprint dá para puxar ~21 SP de tarefas"
```

---

## Dicas práticas

**Para o dev Junior:**

```
✓ Participe do Daily Standup
✓ Faça perguntas no Planning
✓ Vote nos Story Points (mesmo sem ter certeza)
✓ Traga ideia na Retrospective
✓ Atualize o status das tarefas no Jira/Trello
✓ Fale do blocker na hora, não espere
```

**Red flags:**

```
❌ "Não tem tempo para teste, o sprint está pegando fogo"
❌ "PO muda requisito o tempo todo no meio do sprint"
❌ "Daily standup dura 1 hora"
❌ "Retro é formalidade, nada muda"
❌ "Devs não entram no Planning"
```

---

## Na entrevista

> "Agile é desenvolvimento iterativo com foco em adaptação. Scrum é o framework: papéis (PO, Scrum Master, Team) e sprints (em geral 2 semanas). Sprint Planning escolhe as tarefas, Daily Standup sincroniza (15 min), Sprint Review é a demo, Retrospective fala do processo. User Stories com Acceptance Criteria. Story Points estimam complexidade (Fibonacci). Velocity é o volume médio por sprint. Definition of Done são os critérios de pronto. Kanban Board visualiza o fluxo."

---

## Exercícios práticos

### Exercício 1: Escreva uma User Story

Crie uma User Story para a feature "Redefinição de senha" com Acceptance Criteria.

<details>
<summary>Solução</summary>

**User Story:**

```
Como um usuário
Eu quero redefinir minha senha
Para que eu consiga acessar a conta se eu esquecer a senha
```

**Acceptance Criteria:**

```
✓ Usuário pede redefinição de senha na página de login
✓ Sistema envia o link de reset para o email
✓ Link expira em 1 hora
✓ Usuário define senha nova (mínimo 8 caracteres)
✓ Senha antiga deixa de valer depois do reset
✓ Usuário entra logado depois do reset
✓ Email de aviso é enviado depois da senha mudar
```

**Testes:**

```php
public function test_user_can_request_password_reset()
{
    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email
    ]);

    $response->assertRedirect();
    Notification::assertSentTo($user, ResetPasswordNotification::class);
}

public function test_user_can_reset_password_with_valid_token()
{
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
}

public function test_reset_link_expires_after_one_hour()
{
    $user = User::factory()->create();
    $token = Password::createToken($user);

    // Avança 61 minutos
    $this->travel(61)->minutes();

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors();
}
```

**Story Points:** 3 SP (complexidade média)

</details>

### Exercício 2: Estimativa no Planning Poker

Estime as tarefas abaixo em Story Points (1, 2, 3, 5, 8, 13):

1. Adicionar o campo "phone" no formulário de cadastro
2. Integrar o Stripe
3. Corrigir bug: typo no texto do botão
4. Criar sistema de notificações (email, SMS, push)

<details>
<summary>Solução</summary>

**1. Adicionar o campo "phone" no formulário de cadastro**
- **2 SP** (simples)
- Mudanças: migration, validation rule, formulário, testes
- Tempo: 2-3 horas

**2. Integrar o Stripe**
- **8 SP** (muito difícil)
- Mudanças: Stripe SDK, payment service, webhooks, UI, testes, tratamento de erro
- Tempo: 1-2 dias
- Incerteza: API externa

**3. Corrigir bug: typo no texto do botão**
- **1 SP** (muito simples)
- Mudanças: 1 linha no arquivo Blade
- Tempo: 5 minutos

**4. Criar sistema de notificações (email, SMS, push)**
- **13 SP** (tarefa grande demais!)
- **Recomendação:** quebrar em subtarefas:
  - Email notifications [3 SP]
  - SMS notifications [5 SP]
  - Push notifications [5 SP]

**Por que essas notas:**
- Complexidade de desenvolvimento
- Volume de mudança
- Incerteza / risco
- Tempo de teste
- Iterações de code review

</details>

### Exercício 3: Action Items da Retrospective

Depois do sprint o time achou problemas. Proponha Action Items:

**Problemas:**
- Code review atrasa 2-3 dias
- Muito bug aparece em production
- Testes não cobrem edge cases
- Não sobra tempo para refatorar

<details>
<summary>Solução</summary>

**Action Items:**

**1. Code review atrasa:**

```
Problema: PR fica 2-3 dias sem review

Action Items:
✓ [John] Definir SLA: review em até 4 horas
✓ [Sarah] Configurar lembrete automático no Slack
✓ [Team] Reservar "Code Review Hour" todo dia (15:00-16:00)
✓ [Team] PR com menos de 300 linhas (senão quebrar)
✓ [Scrum Master] Acompanhar a métrica "time to review"

Resultado esperado:
- PR revisado no mesmo dia
- Menos troca de contexto
```

**2. Muito bug em production:**

```
Problema: Usuário acha o bug, teste não acha

Action Items:
✓ [Team] Adicionar pre-commit hook para rodar os testes
✓ [Alice] Escrever checklist de teste manual para cada PR
✓ [Bob] Configurar Sentry para tracking de erro em production
✓ [Team] Definition of Done inclui: teste no staging
✓ [PO] Reservar 20% da capacity do sprint para bugfix

Resultado esperado:
- Menos bug vazando para production
- Acha e corrige mais rápido
```

**3. Testes não cobrem edge cases:**

```
Problema: Tem coverage, mas edge case não está testado

Action Items:
✓ [Team] Checklist de code review: olhar edge cases
✓ [John] Fazer workshop "Testing Edge Cases"
✓ [Team] Cada bug de production vira teste de regressão
✓ [Sarah] Adicionar mutation testing (Infection PHP)
✓ [Team] Exemplos de edge case no Definition of Done

Resultado esperado:
- Testes acham mais bug
- Mais confiança no deploy
```

**4. Não sobra tempo para refatorar:**

```
Problema: Débito técnico cresce, código fica mais difícil de manter

Action Items:
✓ [PO] Reservar 10-15% da capacity para débito técnico
✓ [Team] "Refactoring Friday" — toda sexta, 2 horas
✓ [Scrum Master] Colocar "Tech Debt" no Product Backlog
✓ [Team] Em cada mudança: "deixe o código melhor do que encontrou"
✓ [John] Manter um Tech Debt Register (priorizar)

Resultado esperado:
- Qualidade do código sobe aos poucos
- Menos tempo fazendo debug de código legado
```

**Acompanhamento:**
- Revisitar os action items na próxima Retrospective
- Medir: time to review, bugs em production, test coverage
- Celebrar as melhorias!

</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
