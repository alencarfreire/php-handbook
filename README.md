# PHP/Laravel Interview Handbook

> Guia completo para entrevista de PHP/Laravel: exemplos reais, exercícios práticos e respostas prontas.

## Sobre o livro

Este handbook tem:

- **138+ temas** do Junior ao Senior
- **Resumos** para revisar rápido
- **Exemplos práticos** com código de verdade
- **Exercícios práticos** para fixar
- **Respostas prontas** para entrevista
- **Do simples ao avançado**

## Para quem é

- **Junior** — fundamentos de PHP e Laravel
- **Middle** — aprofundar e se preparar para entrevista
- **Senior** — organizar o que você já sabe e revisar antes da entrevista
- **Entrevistadores** — base de perguntas

## Estrutura

O livro está dividido por nível:

### Nível 1: Fundamentos de PHP (Junior)
- PHP Basics — tipos, variáveis, operadores, funções, arrays
- OOP em PHP — classes, herança, interfaces, traits
- PHP Advanced — namespaces, PSR, exceções, PHP 8

### Nível 2: Laravel & Frameworks (Middle)
- Laravel Basics — arquitetura, Service Container, Facades, rotas
- Laravel Advanced — Eloquent, queues, events, validation
- SQL & Databases — queries, transações, otimização

### Nível 3: Arquitetura & Patterns (Middle+)
- Design Patterns — criacionais, estruturais, comportamentais
- Architecture Patterns — MVC, Repository, Service Layer, DDD, CQRS
- Princípios — SOLID, KISS, DRY, GRASP

### Nível 4: Infra & DevOps (Senior)
- Docker — containers, docker-compose, CI/CD
- Performance — cache, profiling, otimização
- Message Brokers — RabbitMQ, Kafka, Redis Pub/Sub
- Microservices — arquitetura, API Gateway, Service Discovery

### Nível 5: Extra
- Security — XSS, CSRF, SQL Injection, autenticação
- Testing — Unit, Feature, TDD, mocks e stubs
- API Development — REST, GraphQL, documentação
- Soft Skills — code review, Agile, tech interview

## Como usar

**Para estudar:**
1. Leia na ordem, do simples ao avançado
2. Cada tema leva ~10–15 minutos
3. Faça os exercícios práticos

**Antes da entrevista:**
1. Revise as seções do seu nível (Junior/Middle/Senior)
2. Leia **Na entrevista** em cada tema
3. Prepare exemplos da sua experiência

**Por tema:**
1. Use a busca ou a navegação
2. Leia o tema inteiro
3. Teste os exemplos no seu projeto

## Formato de cada tema

1. **Resumo** — TL;DR para revisar rápido
2. **Conteúdo** — navegação do tema
3. **O que é** — definição simples
4. **Como funciona** — exemplos com código
5. **Prós e contras** — quando usar e o que evitar
6. **Na entrevista** — resposta pronta e estruturada
7. **Exercícios práticos** — problemas com solução

## Por onde começar

- [Começar pelo PHP](01-php-basics/01-types.md) — se você é iniciante
- [Laravel Basics](05-laravel-basics/01-architecture.md) — se já sabe PHP e quer Laravel
- [Design Patterns](18-design-patterns/01-creational.md) — para ir fundo em arquitetura
- [Docker & DevOps](11-docker/01-docker-basics.md) — para infra

## Sobre o autor

Este handbook foi feito pela equipe [CodeMate](https://codemate.team) com base em entrevistas reais e na preparação de devs.

## Tradução pt-BR

Esta edição em português brasileiro foi **traduzida por IA** por [Vinícius Freire](https://github.com/alencarfreire), a partir do original em russo da CodeMate.

**Metodologia:**

1. Tradução no lugar, sem i18n. Pastas, slugs e APIs ficaram em inglês.
2. Guia fixo em [`TRANSLATION.md`](TRANSLATION.md): pt-BR de entrevista, **você**, frases curtas. Sem tradução literal do russo e sem tom de manual.
3. Piloto humano em [`01-php-basics/01-types.md`](01-php-basics/01-types.md). O resto copiou esse tom e a tabela de rótulos (Resumo, Conteúdo, Na entrevista…).
4. Um arquivo por vez. Comentários e strings de exemplo traduzidos; identificadores PHP/SQL intactos.
5. Checagem mecânica: zero cirílico, TOC alinhado aos headings, `npm run docs:build`.
6. Passe de consistência (labels, `tu`, amostra de um capítulo por pasta). Não houve revisão linguística de cada capítulo.

Pode ter tom irregular. Correção via PR é bem-vinda.

## Projetos de bolso (só neste fork)

Pasta [`23-projects/`](23-projects/index.md) + código em [`projects/`](projects/README.md).

**Gerados por IA.** Não existem no [handbook original da CodeMate](https://github.com/codemateteam/php-handbook). Não abrir PR desses arquivos para o upstream.

1. [Páginas com sessão](23-projects/01-sessoes.md) — PHP puro, no ar
2. API MVC + SOLID — em desenvolvimento
3. Symfony — em desenvolvimento
4. Laravel básico — em desenvolvimento
5. Laravel completo — em desenvolvimento

---

**Pronto?** Escolha uma seção na navegação e começa.

*Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
