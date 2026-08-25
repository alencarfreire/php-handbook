# Tradução pt-BR

Guia da branch `translate-pt-br`. Sem i18n: traduz no lugar. Pastas e slugs ficam em inglês.

Idioma: **pt-BR de entrevista**. Não é tradução literal do russo e não é português de manual.

---

## Tom

O original fala com o leitor. Manter isso.

- **você**, nunca tu
- Frase curta, direta, como no quadro
- Sem “neste presente documento”
- Sem pt-PT: ficheiro, autocarro, descarregar, aplicação (no sentido de app → **app**)
- Sem espanhol: ordenador, subir (upload), descargar
- **Na entrevista** tem que soar falado — o que você diria para o entrevistador

Ruim: “O contentor de serviços efetua a resolução das dependências.”

Bom: “O Service Container resolve as dependências. Você pede a classe, o Laravel instancia.”

---

## O que traduz

- Prosa, títulos, TOC interno
- Comentários de código e strings de exemplo
- UI do VitePress (nav, footer, busca, “editar página”)
- Nomes de exemplo: Иван → João, `ivan@mail.com` → `joao@email.com`
- Moeda de exemplo: R$ quando o domínio pedir dinheiro

## O que não traduz / não mexe

- Caminhos, slugs, nomes de arquivo
- APIs, classes, métodos, pacotes
- SQL, keywords PHP, assinaturas
- Links entre arquivos (só o texto do link muda)
- `ex/img.png`

Quando um `##` mudar, atualizar o âncora do TOC daquele arquivo.

---

## Seções recorrentes

Usar sempre estes rótulos. Não improvisar sinônimo no meio do livro.

| Russo | pt-BR |
|---|---|
| Краткое резюме | Resumo |
| Содержание | Conteúdo |
| Что это / Что такое … | O que é |
| Как работает | Como funciona |
| Когда использовать | Quando usar |
| Пример из практики | Exemplo prático |
| Плюсы и минусы / Плюсы vs … | Prós e contras |
| На собеседовании скажешь | Na entrevista |
| Важно на собесе | Importante na entrevista |
| Практические задания | Exercícios práticos |
| Задание N | Exercício N |
| Условие | Enunciado |
| Ключевые моменты | Pontos-chave |
| Резюме / Резюме … | Recapitulando |
| Применение в Laravel | No Laravel |
| Когда нарушать принципы | Quando quebrar o princípio |
| Комбинация принципов | Combinando os princípios |

Labels em negrito no corpo (`**Что это:**`) usam a mesma tabela.

---

## Termos que ficam em inglês

A comunidade BR já fala assim. Não inventar tradução.

Laravel / PHP: Eloquent, middleware, facade, queue, job, seeder, migration, request, response, Service Container, Service Provider, artisan, trait, namespace, enum, DTO, value object, controller, endpoint, payload, helper, accessor, mutator, eager load, lazy load, observer (o pattern), factory (o pattern)

Padrões: MVC, SOLID, DRY, KISS, YAGNI, GRASP, CQRS, DDD, Repository, Factory, Observer, Singleton

Infra: Docker, Redis, Kafka, RabbitMQ, CI/CD, cache, deploy, cluster, shard

Primeira ocorrência de termo misto:

`Service Container (container de serviços)`

Depois só `Service Container`.

---

## Termos que vão para pt-BR

| Conceito | pt-BR |
|---|---|
| class / объект | classe / objeto |
| inheritance / наследование | herança |
| interface | interface |
| exception / исключение | exceção |
| visibility / область видимости | visibilidade |
| routing / маршрутизация | rotas / roteamento |
| authentication | autenticação |
| authorization | autorização |
| encryption | criptografia |
| debugging (prosa do resumo) | depurar |
| debugging (ato no dia a dia) | fazer debug |
| Repository (pattern) | Repository |
| repositório (git) | repositório |

Um termo só por conceito no livro inteiro.

- controller, não controlador
- middleware, não “software intermediário”
- job / queue, não “trabalho / fila” na primeira menção de Laravel (fila pode aparecer depois: `queue (fila)`)
- Repository = pattern; repositório = git

---

## Código

Traduz comentário e string. Não traduz identificador.

```php
public function greet(): string
{
    return "Olá, {$this->name}!";
}

$user->name = 'João';
$user->email = 'joao@email.com';
```

`User`, `greet`, `getAge` ficam. `Привет`, `Иван`, `mail.ru` saem.

---

## Critério de arquivo pronto

1. Zero cirílico: buscar `[А-яЁё]`
2. TOC bate com os headings novos
3. Código ainda faz sentido (só comentário/string mudou)
4. Tom de entrevista, não verbete

Uma unidade = um arquivo. Sem dump de pasta.

---

## Fases

| Fase | O quê |
|---|---|
| 0 | Esta branch, este guia |
| 1 | Casca: README, index, roadmap, SUMMARY, `.vitepress/config.mjs` |
| 2 | Piloto: `01-php-basics/01-types.md` |
| 3 | `01` restante → `02` → `03` → `04` |
| 4 | `05` → `06` (sem órfãos) → `07` → `08` → `09` |
| 5 | `10` → `11` |
| 6 | `12` → `13` → `14` → `15` |
| 7 | `16` → `17` → `18` → `19` → `20` |
| 8 | `21` → `22` |
| 9 | Grep de cirílico, órfãos `07-api-resources-improved.md` e `07-api-resources-v2.md`, build |

`main` do fork espelha o upstream. Trabalho só nesta branch.

Órfãos (fora do SUMMARY): deixar para a fase 9.
