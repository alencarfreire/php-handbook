# Roadmap de PHP/Laravel

Caminho do básico ao Senior. Cada bloco aponta para o capítulo correspondente do handbook.

---

## Junior Developer

### Fundamentos de PHP

**Sintaxe**
- [Tipos](/01-php-basics/01-types) — tipos primitivos, type casting
- [Variáveis](/01-php-basics/02-variables) — escopo, constantes
- [Operadores](/01-php-basics/03-operators) — aritméticos, lógicos, comparação
- [Estruturas de controle](/01-php-basics/04-control-structures) — if, switch, loops

**Funções e estruturas de dados**
- [Funções](/01-php-basics/05-functions) — declaração, parâmetros, retorno
- [Arrays](/01-php-basics/06-arrays) — indexados, associativos, funções de array
- [Strings e regex](/01-php-basics/07-strings-regex) — strings, PCRE

### Programação orientada a objetos

**OOP básico**
- [Classes e objetos](/02-oop/01-classes-objects) — instância, propriedades, métodos
- [Herança](/02-oop/02-inheritance) — extends, override
- [Interfaces](/02-oop/03-interfaces) — contratos, implementação múltipla
- [Classes abstratas](/02-oop/04-abstract-classes) — abstração, template method

**Recursos avançados**
- [Traits](/02-oop/05-traits) — reuso de código
- [Magic methods](/02-oop/06-magic-methods) — __construct, __get, __set, __call
- [Late static binding](/02-oop/07-static-binding) — self vs static
- [Visibilidade](/02-oop/08-visibility) — public, protected, private

### PHP Advanced

**Recursos modernos**
- [Namespaces](/03-php-advanced/01-namespaces) — organização do código, autoload
- [Composer e PSR-4](/03-php-advanced/02-autoloading) — dependências
- [Exceções](/03-php-advanced/03-exceptions) — erros, custom exceptions
- [Padrões PSR](/03-php-advanced/04-psr) — PSR-1, PSR-2, PSR-4, PSR-12

**PHP 8+**
- [PHP 8 features](/03-php-advanced/07-php8-features) — Named arguments, Match, Attributes
- [Generators](/03-php-advanced/05-generators) — yield, menos memória
- [Reflection](/03-php-advanced/06-reflection) — metaprogramação

### Git

- [Git Basics](/04-git/01-git-basics) — init, add, commit, push, pull
- [Branching Strategies](/04-git/02-branching-strategies) — feature branches
- [Rebase vs Merge](/04-git/03-rebase-vs-merge) — quando usar cada um
- [Git Flow](/04-git/04-git-flow) — workflow de time
- [Conflict Resolution](/04-git/05-conflict-resolution) — resolver conflitos

### Laravel Basics

**Arquitetura do framework**
- [Arquitetura do Laravel](/05-laravel-basics/01-architecture) — MVC, estrutura do projeto
- [Service Container](/05-laravel-basics/02-service-container) — container de DI
- [Service Providers](/05-laravel-basics/03-service-providers) — registro de serviços
- [Facades](/05-laravel-basics/04-facades) — acesso estático aos serviços

**Peças principais**
- [Routing](/05-laravel-basics/05-routing) — rotas, grupos, parâmetros
- [Middleware](/05-laravel-basics/06-middleware) — tratamento do request
- [Controllers](/05-laravel-basics/07-controllers) — regra de negócio
- [Request/Response](/05-laravel-basics/08-request-response) — HTTP

---

## Middle Developer

### Laravel Advanced

**Dados**
- [Eloquent Relationships](/06-laravel-advanced/01-eloquent-relationships) — relações entre models
- [Query Builder](/06-laravel-advanced/02-query-builder) — montar queries
- [Migrations e Seeders](/06-laravel-advanced/03-migrations-seeders) — versionar o banco
- [Validation](/06-laravel-advanced/08-validation) — validar dados

**Processamento assíncrono**
- [Events & Listeners](/06-laravel-advanced/04-events-listeners) — modelo de eventos
- [Jobs & Queues](/06-laravel-advanced/05-jobs-queues) — tarefas em background
- [Notifications](/06-laravel-advanced/06-notifications) — notificações

**API**
- [API Resources](/06-laravel-advanced/07-api-resources) — transformar dados

### Bancos de dados

**SQL**
- [SQL Basics](/07-sql-databases/01-sql-basics) — SELECT, JOIN, subqueries
- [Aggregate Functions](/07-sql-databases/02-aggregate-functions) — COUNT, SUM, AVG
- [Indexes](/07-sql-databases/03-indexes) — tipos de índice, otimização
- [Transactions](/07-sql-databases/04-transactions) — ACID, isolation levels
- [Normalization](/07-sql-databases/05-normalization) — formas normais

**Otimização**
- [N+1 Query Problem](/07-sql-databases/06-n-plus-one) — eager loading
- [Query Optimization](/07-sql-databases/07-query-optimization) — EXPLAIN, índices
- [Caching](/07-sql-databases/09-caching) — cache de query

### Testing

- [Unit Tests](/08-testing/01-unit-tests) — testar peça isolada
- [Feature Tests](/08-testing/02-feature-tests) — testes de integração
- [TDD](/08-testing/03-tdd) — desenvolver pelo teste
- [Mocking & Stubbing](/08-testing/04-mocking-stubbing) — mocks, stubs
- [PHPUnit](/08-testing/05-phpunit) — framework de teste

### Security

- [XSS](/09-security/01-xss) — se proteger de XSS
- [CSRF](/09-security/02-csrf) — tokens, proteger formulário
- [SQL Injection](/09-security/03-sql-injection) — queries parametrizadas
- [Authentication](/09-security/04-authentication) — autenticar usuário
- [Authorization](/09-security/05-authorization) — permissão, policies
- [OWASP Top 10](/09-security/08-owasp-top-10) — falhas mais comuns

### API Development

- [REST API](/10-api-development/01-rest-api) — princípios REST
- [API Versioning](/10-api-development/05-api-versioning) — versionar a API
- [Rate Limiting](/10-api-development/04-rate-limiting) — limitar request
- [CORS](/10-api-development/06-cors) — request cross-origin
- [Swagger Documentation](/10-api-development/03-swagger-documentation) — documentar

### Docker

- [Docker Basics](/11-docker/01-docker-basics) — containers, images
- [Dockerfile](/11-docker/02-dockerfile) — criar image
- [Docker Compose](/11-docker/03-docker-compose) — app multi-container
- [CI/CD](/11-docker/04-ci-cd) — automatizar o deploy

---

## Senior Developer

### Database Advanced

**Recursos avançados**
- [Replication](/12-database-advanced/01-replication) — replicar dados
- [Sharding](/12-database-advanced/02-sharding) — escala horizontal
- [Window Functions](/12-database-advanced/03-window-functions) — funções analíticas
- [Isolation Levels](/12-database-advanced/04-isolation-levels) — níveis de isolamento
- [Locks](/12-database-advanced/05-locks) — locks, deadlocks
- [JSONB](/12-database-advanced/06-jsonb) — JSON no PostgreSQL
- [Materialized Views](/12-database-advanced/07-materialized-views) — views materializadas
- [Partitioning](/12-database-advanced/08-partitioning) — particionar tabela

**Otimização**
- [Normalization](/13-database-optimization/01-normalization) — normalização
- [Denormalization](/13-database-optimization/02-denormalization) — desnormalização
- [Big Data](/13-database-optimization/03-big-data) — volume grande

### Caching

- [Caching Strategies](/14-caching/01-strategies) — estratégias de cache
- [Redis](/14-caching/02-redis) — cache in-memory
- [Memcached](/14-caching/03-memcached) — cache distribuído
- [HTTP Cache](/14-caching/04-http-cache) — cache do browser
- [OPcache](/14-caching/05-opcache) — cache de bytecode

### Performance

- [Caching](/15-performance/01-caching) — aplicar cache
- [Database Optimization](/15-performance/02-database-optimization) — otimizar o banco
- [Query Optimization](/15-performance/03-query-optimization) — otimizar query
- [PHP Optimization](/15-performance/05-php-optimization) — otimizar PHP
- [Scaling](/15-performance/06-scaling) — escalar o app

### Principles & Patterns

**Princípios**
- [KISS, DRY, YAGNI](/16-principles/01-kiss-dry-yagni) — princípios básicos
- [GRASP](/16-principles/02-grasp) — quem fica com cada responsabilidade

**Patterns de arquitetura**
- [MVC](/17-architecture-patterns/01-mvc) — Model-View-Controller
- [Repository Pattern](/17-architecture-patterns/02-repository-pattern) — abstrair dados
- [Service Layer](/17-architecture-patterns/03-service-layer) — regra de negócio
- [SOLID](/17-architecture-patterns/04-solid) — princípios de OOP
- [DDD](/17-architecture-patterns/05-ddd) — Domain-Driven Design
- [CQRS](/17-architecture-patterns/06-cqrs) — separar comando e query
- [Event Sourcing](/17-architecture-patterns/07-event-sourcing) — guardar eventos
- [Dependency Injection](/17-architecture-patterns/08-dependency-injection) — injeção de dependência

**Design Patterns**
- [Creational Patterns](/18-design-patterns/01-creational) — patterns criacionais
- [Structural Patterns](/18-design-patterns/02-structural) — patterns estruturais
- [Behavioral Patterns](/18-design-patterns/03-behavioral) — patterns comportamentais

### Message Brokers

- [RabbitMQ](/19-message-brokers/01-rabbitmq) — filas de mensagem
- [Kafka](/19-message-brokers/02-kafka) — event streaming
- [Redis Pub/Sub](/19-message-brokers/03-redis-pubsub) — publish/subscribe
- [Comparison](/19-message-brokers/04-comparison) — comparar as opções

### Microservices

- [Monolith vs Microservices](/20-microservices/01-monolith-vs-microservices) — escolher a arquitetura
- [API Gateway](/20-microservices/02-api-gateway) — porta de entrada única
- [Circuit Breaker](/20-microservices/03-circuit-breaker) — se proteger de falha
- [Service Discovery](/20-microservices/04-service-discovery) — achar o serviço
- [Saga Pattern](/20-microservices/05-saga-pattern) — transação distribuída

### Soft Skills

- [Code Review](/21-soft-skills/01-code-review) — revisar código
- [Agile & Scrum](/21-soft-skills/02-agile-scrum) — método de trabalho
- [Tech Interview](/21-soft-skills/03-tech-interview) — passar na entrevista
- [Documentation](/21-soft-skills/04-documentation) — documentar código

---

## Prática

Depois da teoria, fixe na prática:

- [Coding Challenges](/22-practice/01-coding-challenges) — problemas de algoritmo
- [System Design](/22-practice/02-system-design) — desenhar sistema
- [Debugging](/22-practice/03-debugging) — achar e corrigir bug
- [Refactoring](/22-practice/04-refactoring) — refatorar
- [Real World Cases](/22-practice/05-real-world-cases) — casos reais

---

## Projetos de bolso (só neste fork)

Gerados por IA. **Não existem no handbook original da CodeMate.**

- [Páginas com sessão](/23-projects/01-sessoes) — PHP puro, no ar
- [API MVC + SOLID](/23-projects/02-api-mvc) — PHP puro, no ar
- Symfony — em desenvolvimento
- Laravel básico — em desenvolvimento
- Laravel completo — em desenvolvimento

Comece em [Projetos](/23-projects/).

---

## Extra

**Junior**
- Escreva código todo dia
- Faça pet projects
- Leia código dos outros no GitHub
- Entre em code review

**Middle**
- Estude a arquitetura de projetos reais
- Pratique TDD
- Otimize performance
- Ensine o time

**Senior**
- Desenhe a arquitetura
- Mentore juniors
- Estude práticas de DevOps
- Acompanhe o que a indústria está fazendo

**Com mentor**
O programa de mentoria da CodeMate encurta esse caminho: consultoria, code review, mock interview e ajuda com a vaga. Detalhes em [codemate.team](https://codemate.team).
