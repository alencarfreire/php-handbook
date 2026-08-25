# 15.3 Entrevista técnica

## O que é

**Tipos de entrevista:**

```
1. HR screening (15-30 min)
   - Sobre você, experiência, motivação

2. Technical interview (1-2 h)
   - Teoria, prática, algoritmos

3. Coding challenge / Live coding
   - Resolver problemas ao vivo

4. System design (para Senior+)
   - Projetar a arquitetura

5. Cultural fit
   - Se você encaixa no time
```

---

## Preparação

**O que revisar:**

```
✓ PHP básico (tipos, OOP, namespace, PSR)
✓ Laravel (rotas, middleware, Eloquent, events, jobs)
✓ SQL (JOIN, índices, otimização)
✓ Algoritmos (ordenação, busca, recursão)
✓ Estruturas de dados (arrays, pilhas, filas)
✓ Padrões (Repository, Factory, Observer)
✓ Git (branching, merge, rebase, conflitos)
✓ Testing (Unit, Feature, TDD)
✓ Segurança (XSS, SQL injection, CSRF)
✓ Performance (cache, N+1, índices)
```

**Projetos:**

```
✓ Prepare 2-3 projetos para discutir
✓ Saiba a arquitetura dos seus projetos
✓ Lembre dos problemas difíceis e como você resolveu
✓ Esteja pronto para mostrar código no GitHub
```

---

## Perguntas típicas

**Sobre você:**

```
"Fala um pouco sobre você"
✅ Resposta boa:
"Sou desenvolvedor PHP/Laravel com 2 anos de experiência.
Trabalhei num e-commerce com 100 mil usuários.
Fiz otimização de performance, implementei
funcionalidades novas, escrevi testes. Me interesso por
arquitetura e código limpo. Recentemente estudei
Event Sourcing e DDD."

❌ Resposta ruim:
"Eu estudei na faculdade, depois trabalhei... ééé...
em vários projetos. Laravel eu conheço bem."
```

**Por que você quer mudar de emprego:**

```
✅ Bom:
"Quero trabalhar em problemas mais complexos
e crescer em arquitetura"
"O produto de vocês e o stack me interessam"

❌ Ruim:
"Salário baixo"
"Time ruim"
"Muito código legacy"
```

---

## Perguntas técnicas

**PHP:**

```php
Q: "Qual a diferença entre == e ===?"
A: "== compara o valor e converte o tipo.
=== compara valor e tipo, sem conversão.
Exemplo: '5' == 5 (true), '5' === 5 (false)"

Q: "O que é PSR?"
A: "PHP Standards Recommendations. Padrões para:
- PSR-1, PSR-12: Code Style
- PSR-4: Autoloading
- PSR-7: HTTP message interfaces"

Q: "O que é namespace?"
A: "Isola classes. Evita conflito de nomes:
namespace App\Services;
class UserService {}"
```

**Laravel:**

```php
Q: "O que é Service Container?"
A: "Service Container (container de serviços) é o
container de DI. Injeta dependências sozinho via type-hint:
public function __construct(UserRepository $repo)"

Q: "Qual a diferença entre Facade e DI?"
A: "Facade é um proxy estático para a classe no container.
DI injeta a dependência no construtor.
DI é melhor para teste: você consegue mockar."

Q: "O que é o problema N+1?"
A: "Uma query da lista + N queries das relações.
Solução: eager loading com with()"
```

**SQL:**

```sql
Q: "Qual a diferença entre INNER JOIN e LEFT JOIN?"
A: "INNER JOIN devolve só as linhas que batem.
LEFT JOIN devolve todas as linhas da tabela da esquerda
+ as que batem na da direita (ou NULL)"

Q: "Para que servem os índices?"
A: "Aceleram busca em WHERE, ORDER BY, JOIN.
Mas deixam INSERT/UPDATE/DELETE mais lentos.
Use em foreign keys e colunas que você filtra muito"
```

---

## Live Coding

**Tarefas típicas:**

**1. FizzBuzz:**

```php
// Imprimir números de 1 a 100
// Múltiplos de 3: Fizz
// Múltiplos de 5: Buzz
// Múltiplos de 3 e 5: FizzBuzz

function fizzBuzz(int $n): array
{
    $result = [];

    for ($i = 1; $i <= $n; $i++) {
        if ($i % 15 === 0) {
            $result[] = 'FizzBuzz';
        } elseif ($i % 3 === 0) {
            $result[] = 'Fizz';
        } elseif ($i % 5 === 0) {
            $result[] = 'Buzz';
        } else {
            $result[] = (string) $i;
        }
    }

    return $result;
}
```

**2. Palíndromo:**

```php
function isPalindrome(string $str): bool
{
    $str = strtolower(preg_replace('/[^a-z0-9]/i', '', $str));
    return $str === strrev($str);
}

// Testes
isPalindrome('A grama é amarga'); // true
isPalindrome('teste'); // false
```

**3. Arrays:**

```php
// Encontrar duplicatas
function findDuplicates(array $arr): array
{
    $counts = array_count_values($arr);
    return array_keys(array_filter($counts, fn($c) => $c > 1));
}

// Remover duplicatas (manter a ordem)
function unique(array $arr): array
{
    return array_values(array_unique($arr));
}

// Interseção de arrays
function intersection(array $a, array $b): array
{
    return array_values(array_intersect($a, $b));
}
```

**4. Tarefas Laravel:**

```php
// Users com mais de 10 pedidos no último mês
User::has('orders', '>', 10)
    ->whereHas('orders', function ($q) {
        $q->where('created_at', '>=', now()->subMonth());
    })
    ->get();

// Top 5 produtos mais vendidos
Product::withCount('orderItems')
    ->orderBy('order_items_count', 'desc')
    ->limit(5)
    ->get();
```

---

## Dicas para Live Coding

**Processo:**

```
1. Confirme o enunciado
   "Precisa considerar maiúscula e minúscula?"
   "A string é só ASCII?"

2. Discuta a abordagem
   "Dá para resolver com array ou regex.
    Sugiro usar..."

3. Escreva a solução
   Fale em voz alta o que você está fazendo

4. Teste
   "Vamos ver os edge cases: string vazia, null..."

5. Otimize (se der tempo)
   "Dá para baixar a complexidade de O(n²) para O(n)"
```

**Se você travar:**

```
✓ Fale o problema em voz alta
✓ Peça um hint
✓ Comece pela solução simples (brute force)
✓ Pode usar Google / documentação (se for permitido)
```

---

## System Design (para Middle+/Senior)

**Tarefa típica:**

```
"Projete um sistema tipo Twitter"

1. Confirme os requisitos
   - Quantos usuários?
   - QPS (queries per second)?
   - Features: tweets, likes, follows?

2. Arquitetura high-level
   Users → Load Balancer → App Servers → Database

3. Database schema
   users: id, username, email
   tweets: id, user_id, content, created_at
   follows: follower_id, following_id

4. API endpoints
   POST /tweets
   GET /tweets/{id}
   GET /timeline (feed)

5. Scaling
   - Cache do timeline no Redis
   - Read replicas no banco
   - CDN para mídia
   - Queue para tarefas assíncronas
```

---

## Perguntas comportamentais

**Método STAR:**

```
Situation: Situação
Task: Tarefa
Action: Ação
Result: Resultado
```

**Exemplos:**

```
Q: "Fale de um problema técnico difícil"

A: (STAR)
S: "No e-commerce as queries estavam lentas (5+ segundos)"
T: "Precisava baixar para menos de 1 segundo"
A: "Achei N+1 no Laravel Debugbar,
    coloquei eager loading, criei índices no banco"
R: "A query caiu para 200ms,
    os usuários pararam de reclamar da lentidão"

Q: "Conflito no time?"

A: (STAR)
S: "Discordância com um colega sobre o jeito de refatorar"
T: "Precisávamos chegar numa decisão"
A: "Discutimos as duas abordagens, fizemos POC das duas,
    medimos a performance"
R: "Escolhemos a melhor opção com base nos dados,
    os dois ficaram ok com o resultado"
```

---

## Perguntas para o empregador

**Perguntas boas:**

```
Sobre o projeto:
- Qual o stack?
- Qual a arquitetura da app?
- Qual o tamanho do time?
- Como é o processo de desenvolvimento? (Agile/Scrum?)

Sobre Code Quality:
- Vocês fazem Code Review?
- Qual a cobertura de testes?
- Tem CI/CD?

Sobre crescimento:
- Tem mentoria para Junior?
- A empresa paga curso/estudo?
- Tem espaço para crescer?

Sobre o time:
- Com quem eu vou trabalhar?
- Como as decisões técnicas são tomadas?
```

---

## Red flags

**Do empregador:**

```
❌ "A gente não tem tempo para teste"
❌ "Code review? Isso atrasa o desenvolvimento"
❌ "A gente trabalha bastante no fim de semana"
❌ "Tem código legacy, mas a gente não mexe"
❌ "Não tem documentação, pergunta pro João"
❌ Entrevistadores agressivos ou arrogantes
```

---

## Na entrevista

> "Entrevista técnica testa teoria (PHP, Laravel, SQL), prática (live coding) e arquitetura (system design para Senior). Preparação: revisar o básico, algoritmos, padrões, seus projetos. Live coding: confirme o enunciado, discuta a abordagem, teste. Pergunta comportamental: método STAR (Situation, Task, Action, Result). Pergunte para a empresa: stack, processo, crescimento, time. Red flags: sem teste, sem code review, work-life balance ruim."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
