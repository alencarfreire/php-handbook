# Projetos de bolso

> **TL;DR**
> Esta seção **não existe no handbook original da CodeMate**. Foi gerada por IA por [Vinícius Freire](https://github.com/alencarfreire) neste fork. Aqui você constrói. Lá em cima é teoria.

## Aviso

- **Original:** [codemateteam/php-handbook](https://github.com/codemateteam/php-handbook) — só os temas 1–22.
- **Este fork:** [alencarfreire/php-handbook](https://github.com/alencarfreire/php-handbook) — tradução pt-BR **e** estes projetos.
- Código e walkthrough desta pasta foram **escritos por IA**. Não misture com o material da CodeMate numa PR para o upstream.

## Conteúdo

- [O recorte](#o-recorte)
- [Já no ar](#já-no-ar)
- [Em desenvolvimento](#em-desenvolvimento)
- [Sugestões (depois)](#sugestões-depois)
- [Como usar](#como-usar)

---

## O recorte

Cada projeto é código que roda em `projects/` + um walkthrough aqui. PHP 8.2+ no built-in server, depois Symfony, depois Laravel.

A ordem é de propósito: sessão na mão → API MVC/SOLID em PHP puro → framework.

## Já no ar

1. [Páginas com sessão](/23-projects/01-sessoes) — PHP puro. Form na home, dados na `$_SESSION`, outra página lê. Sem banco, sem framework. Código: `projects/01-sessoes/`
2. [API MVC + SOLID](/23-projects/02-api-mvc) — PHP puro, PDO SQLite, token Bearer, camadas. Código: `projects/02-api-mvc/`

## Em desenvolvimento

3. **Symfony de porta de entrada** — um CRUD curto para parar de ter medo do Symfony.
4. **Laravel básico** — o CRUD que a vaga júnior pede.
5. **Laravel completo** — filas, policies, API, testes. Conceitos que caem em middle.

## Sugestões (depois)

Não entram agora. Ficam no radar:

6. HTTP API pura (verbos, JSON, store em array — irmão do apipura)
7. Laravel API-only + Sanctum
8. Docker Compose do projeto 5
9. Pest como o produto (a suíte é o exercício)
10. Livewire ou Blade + Alpine — um quadro, sem SPA

## Como usar

1. Leia o walkthrough.
2. Rode o código em `projects/0N-...`.
3. Quebre de propósito. Mude o form. Tire o `session_start()` e veja o estrago.
4. Na entrevista, fale o que o projeto treina — não recorde o README.

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
