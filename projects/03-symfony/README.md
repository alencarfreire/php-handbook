# 03 — Symfony de porta de entrada

CRUD de tasks. Symfony 7.4, Twig, Doctrine, SQLite.

**Gerado por IA.** Não faz parte do handbook original da CodeMate.

## O que você treina

- `#[Route]` no controller
- Autowire (repository e EntityManager caem no método)
- Form + CSRF
- Twig (`path`, `form_*`)
- Doctrine entity / persist / flush

## Como rodar

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php -S localhost:8002 -t public
```

Abre http://localhost:8002

## O que não entra (de propósito)

- Auth / Security
- API JSON
- Webpack / Asset Mapper
- Testes
