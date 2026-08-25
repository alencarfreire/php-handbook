# 15.5 Time Management e Priorização

## O que é

**Time Management:**
Gestão eficiente do tempo para ter o máximo de produtividade.

**Priorização:**
Definir importância e urgência das tarefas para gastar o tempo no lugar certo.

---

## Matriz de Eisenhower

**Modelo:**

```
┌─────────────────────────────┬─────────────────────────────┐
│ URGENTE + IMPORTANTE        │ NÃO URGENTE + IMPORTANTE    │
│ (Fazer agora)               │ (Planejar)                  │
├─────────────────────────────┼─────────────────────────────┤
│ Production bug              │ Refactoring                 │
│ Deadline crítico            │ Estudo                      │
│ Cliente esperando           │ Documentação                │
└─────────────────────────────┴─────────────────────────────┘
┌─────────────────────────────┬─────────────────────────────┐
│ URGENTE + NÃO IMPORTANTE    │ NÃO URGENTE + NÃO IMPORTANTE│
│ (Delegar)                   │ (Eliminar)                  │
├─────────────────────────────┼─────────────────────────────┤
│ Algumas reuniões            │ Navegação sem propósito     │
│ Ligações                    │ Redes sociais               │
│ Distrações                  │ Procrastinação              │
└─────────────────────────────┴─────────────────────────────┘
```

**Exemplos para o desenvolvedor:**

```
Quadrante 1 (Urgente + Importante):
- Produção caiu
- Bug crítico antes do release
- Deadline do sprint é amanhã

Quadrante 2 (Não urgente + Importante):
- Escrever testes
- Refactoring de código legacy
- Estudar uma tecnologia nova
- Otimizar performance

Quadrante 3 (Urgente + Não importante):
- Responder mensagem que não é crítica
- Entrar em toda reunião

Quadrante 4 (Não urgente + Não importante):
- Reddit, Twitter no horário de trabalho
- Ajustar a IDE em vez de trabalhar
```

---

## Métodos de gestão de tempo

**Pomodoro:**

```
25 minutos de trabalho → 5 minutos de pausa

Ciclo:
1. Escolher a tarefa
2. Timer de 25 minutos
3. Trabalhar sem distração
4. 5 minutos de pausa
5. Repetir
6. Depois de 4 ciclos: pausa longa de 15-30 minutos

Para desenvolvedores:
✓ 25 minutos de foco em uma tarefa
✓ Na pausa: levantar, água, sem rede social
✓ Se for interrompido: recomeçar
```

**Time Blocking:**

```
Agenda do dia:

09:00-11:00  Deep work (tarefas difíceis)
11:00-11:15  Pausa
11:15-12:00  Code review, emails
12:00-13:00  Almoço
13:00-14:00  Reuniões
14:00-16:00  Deep work (desenvolvimento de feature)
16:00-16:15  Pausa
16:15-17:00  Testes, documentação
17:00-18:00  Estudo / side projects
```

**Eat The Frog:**

```
A tarefa mais difícil/chata vai primeiro.

Exemplo:
❌ De manhã: emails, Slack, tarefa pequena
   Depois do almoço: refactoring difícil (já cansou)

✅ De manhã: refactoring difícil (cabeça fresca)
   Depois do almoço: emails, code review
```

---

## Priorização de tarefas

**Método MoSCoW:**

```
Must have (tem que ter):
- Autenticação de usuários
- Pagamento de pedidos
- Envio de pedidos

Should have (deveria ter):
- Notificações por email
- Histórico de pedidos
- Filtros de produtos

Could have (poderia ter):
- Favoritos
- Avaliações
- Wishlist

Won't have (não entra agora):
- Funções sociais
- Chat com o vendedor
- Provador AR
```

**Value vs Effort:**

```
Alto valor + baixo esforço:
→ Fazer agora

Alto valor + alto esforço:
→ Planejar (quebrar em partes)

Baixo valor + baixo esforço:
→ Fazer quando sobrar tempo

Baixo valor + alto esforço:
→ Não fazer
```

---

## Combate à procrastinação

**Por que procrastinamos:**

```
1. A tarefa é grande demais
   → Quebrar em passos pequenos

2. Não sei por onde começar
   → Começar pela parte mais simples

3. Medo de fazer mal feito
   → "Done is better than perfect"

4. A tarefa é chata
   → Recompensa depois de concluir

5. Distrações
   → Tirar as fontes de distração
```

**Técnicas:**

```
✓ Regra dos 2 minutos:
  Se a tarefa leva menos de 2 minutos → faça agora

✓ Regra dos 5 minutos:
  Comece a trabalhar 5 minutos, depois pode parar
  (geralmente você entra no ritmo e continua)

✓ Accountability:
  Fale para o colega: "Termino isso até o almoço"

✓ Tirar distrações:
  - Celular em outro cômodo
  - Fechar Slack/Email
  - Bloqueador de sites (Freedom, Cold Turkey)
```

---

## Exemplos práticos

**Planejamento do sprint:**

```markdown
Sprint Goal: Implementar sistema de pagamento

Day 1-2 (High Priority):
□ Setup Stripe SDK [3h]
□ Criar model Payment e migration [2h]
□ Implementar o método charge() [4h]

Day 3-4 (Medium Priority):
□ Adicionar UI de pagamento [5h]
□ Tratar webhooks [3h]
□ Error handling [2h]

Day 5-6 (Low Priority):
□ Escrever testes [4h]
□ Funcionalidade de reembolso [3h]
□ Admin dashboard [2h]

Buffer:
Day 7-8: Bug fixes, code review
```

**Planejamento diário:**

```markdown
# Plano de hoje (2024-01-15)

## Top 3 metas:
1. Corrigir bug crítico em produção [High]
2. Terminar integração de pagamento [High]
3. Escrever testes do UserService [Medium]

## Agenda:
09:00-09:30  Planejamento e emails
09:30-11:30  🐸 Corrigir bug em produção
11:30-12:00  Code review do time
12:00-13:00  Almoço
13:00-15:00  Integração de pagamento
15:00-15:30  Reunião: Sprint planning
15:30-17:00  Escrever testes
17:00-17:30  Slack, updates, planejar amanhã

## Notas:
- Bloquear horário para deep work (sem Slack)
- Pomodoro para testes
```

---

## Gestão de reuniões

**Reuniões eficientes:**

```
Antes da reunião:
□ Tem agenda?
□ Eu realmente preciso estar?
□ Isso poderia ser um email?

Durante a reunião:
□ Seguir a agenda
□ Anotar action items
□ Definir responsáveis

Depois da reunião:
□ Passar action items para tarefas
□ Follow up se precisar
```

**Quando recusar:**

```
✓ "Obrigado pelo convite, mas eu não vou conseguir
   contribuir de forma útil. Pode mandar só o summary?"

✓ "Tenho um deadline crítico. Dá para deixar para amanhã?"

✓ "Melhor a gente discutir async no Slack?"
```

---

## Estimativa de tarefas

**Lei de Hofstadter:**

```
"Tudo leva mais tempo do que o esperado,
mesmo levando em conta a lei de Hofstadter"

Na prática:
Sua estimativa: 2 horas
Realidade: 4-6 horas

Solução: multiplique a estimativa por 2-3x
```

**Fatores da estimativa:**

```
Desenvolvimento base:        [3h]
+ Testes:                    [1h]
+ Iterações de code review:  [0.5h]
+ Bugs inesperados:          [1h]
+ Integração com outros:     [1h]
─────────────────────────────────
Total:                       [6.5h] ≈ 1 dia
```

---

## Work-Life Balance

**Limites:**

```
✓ Horário de trabalho: 09:00-18:00 (não até meia-noite)
✓ Não responder Slack à noite/fim de semana
✓ Intervalo de almoço de 1 hora (não é trabalho)
✓ Férias = férias (não checar email)

Sinais de burnout:
❌ Cansaço constante
❌ Sem motivação
❌ Irritabilidade
❌ Sono ruim
❌ Erros no código
→ Precisa de descanso!
```

**Pausas:**

```
✓ A cada 25-50 minutos: 5-10 minutos
✓ Levantar, alongar, água
✓ Almoço: 1 hora, sair do computador
✓ À noite: trocar para um hobby
✓ Fim de semana: descansar, não trabalhar
```

---

## Ferramentas

**Task Management:**

```
- Todoist (tarefas pessoais)
- Notion (projetos, notas)
- Trello / Jira (tarefas do time)
- GitHub Projects (tarefas de dev)
```

**Time Tracking:**

```
- Toggl Track
- RescueTime (automático)
- Clockify
```

**Focus:**

```
- Forest (bloqueia o celular)
- Freedom (bloqueia sites)
- Pomodoro Timer
- Do Not Disturb mode
```

---

## Dicas para Junior

**O que importa:**

```
✓ Aprender a estimar tarefas de forma realista
✓ Falar se a tarefa vai levar mais tempo
✓ Pedir ajuda se travar mais de 30 minutos
✓ Não pegar mais tarefas do que você consegue
✓ Foco em qualidade, não velocidade
✓ Work-life balance desde o início da carreira
```

**O que evitar:**

```
❌ "Faço rápido" (depois leva 3 dias)
❌ Trabalhar até meia-noite com frequência
❌ Aceitar tudo que aparece
❌ Não planejar o dia
❌ Se distrair o tempo todo
❌ Ignorar o descanso
```

---

## Na entrevista

> "Time management: matriz de Eisenhower para priorizar (urgente/importante). Métodos: Pomodoro (25 min de trabalho + 5 min de pausa), Time Blocking (blocos de tempo), Eat The Frog (o difícil de manhã). MoSCoW para priorizar tarefas. Combate à procrastinação: quebrar em partes, tirar distrações, regra dos 2 minutos. Estimativa: multiplique por 2-3x. Work-life balance importa. Ferramentas: Todoist, Toggl, timers de Pomodoro."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
