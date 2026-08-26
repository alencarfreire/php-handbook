# 01 — Páginas com sessão

PHP puro. Sem Composer, sem banco, sem framework.

**Gerado por IA.** Não faz parte do handbook original da CodeMate.

## O que você treina

- `session_start()` / `$_SESSION`
- Form HTML + POST
- Redirect depois do POST (PRG)
- Página que exige sessão
- `htmlspecialchars` na saída

## Como rodar

```bash
cd projects/01-sessoes
php -S localhost:8000
```

Abre http://localhost:8000

1. Preenche nome e e-mail na home
2. Envia → vai para `/perfil.php` com os dados
3. `Sair` destroi a sessão

## O que não entra (de propósito)

- Banco
- Senha / login de verdade
- CSRF token
- Framework
