# 02 — API MVC + SOLID (PHP puro)

API JSON em PHP 8.2+. Sem Laravel, sem Slim, sem Composer obrigatório.

**Gerado por IA.** Não faz parte do handbook original da CodeMate.

## O que você treina

- Front controller (`public/index.php`)
- Camadas: Domain → Application → Infrastructure / Presentation
- Repository com interface + PDO SQLite
- Auth por token (`Authorization: Bearer`)
- SOLID no tamanho de entrevista, não de DDD de livro

## Como rodar

```bash
cd projects/02-api-mvc
php -S localhost:8001 -t public
```

O SQLite nasce sozinho em `storage/app.sqlite` no primeiro request.

## Endpoints

| Método | Rota | Auth |
|---|---|---|
| POST | `/register` | não |
| POST | `/login` | não |
| GET | `/tasks` | Bearer |
| POST | `/tasks` | Bearer |
| GET | `/tasks/{id}` | Bearer |
| PATCH | `/tasks/{id}` | Bearer |
| DELETE | `/tasks/{id}` | Bearer |

## Curl

```bash
curl -s -X POST http://localhost:8001/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"João","email":"joao@email.com","password":"secret123"}'

TOKEN=$(curl -s -X POST http://localhost:8001/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"joao@email.com","password":"secret123"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST http://localhost:8001/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"title":"Comprar ração"}'

curl -s http://localhost:8001/tasks \
  -H "Authorization: Bearer $TOKEN"
```

## O que não entra (de propósito)

- JWT de biblioteca
- CORS / rate limit
- Framework
- Testes automatizados (o exercício é o curl)
