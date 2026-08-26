# 23.2 API MVC + SOLID (PHP puro)

> **TL;DR**
> PHP puro. Front controller, quatro camadas, PDO SQLite, token no header. Sem Laravel. O ponto é repetir sozinho: interface no domínio, caso de uso no meio, PDO na borda.

**Gerado por IA. Não existe no handbook original da CodeMate.**

## Conteúdo

- [O recorte](#o-recorte)
- [As camadas](#as-camadas)
- [SOLID neste tamanho](#solid-neste-tamanho)
- [Auth](#auth)
- [PDO](#pdo)
- [Como rodar](#como-rodar)
- [Na entrevista](#na-entrevista)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O recorte

Código em `projects/02-api-mvc/`.

Recurso: `Task` do usuário logado. Auth: `POST /register`, `POST /login`, depois `Authorization: Bearer`.

Não é Clean Architecture de livro. É o recorte que cabe na entrevista: domínio sem PDO, caso de uso sem `echo`, controller sem SQL.

## As camadas

| Pasta | Pode | Não pode |
|---|---|---|
| `Domain` | entidade, interface de repository | PDO, `$_SERVER`, JSON |
| `Application` | um caso de uso = um `handle()` | HTTP, SQL |
| `Infrastructure` | PDO, `password_hash`, tabela `tokens` | regra de negócio |
| `Presentation` | rota, status, JSON | SQL |

O `public/index.php` só **monta** as peças e despacha. É o composition root.

```
POST /tasks
  → Router
  → TaskController::store
  → CreateTask::handle
  → TaskRepository::save   (interface)
  → PdoTaskRepository      (SQL)
```

Trocar SQLite por MySQL mexe **só** em `Infrastructure/Connection.php`. Os casos de uso não sabem.

## SOLID neste tamanho

- **S** — `CreateTask` só cria. `LoginUser` só autentica.
- **O** — novo jeito de persistir = nova classe que implementa a interface. Não edita o caso de uso.
- **L** — `PdoTaskRepository` honra o contrato. Quem chama `allForUser` recebe `Task[]`.
- **I** — `UserRepository` não tem método de task.
- **D** — `CreateTask` depende de `TaskRepository`, não de `PDO`.

Hasher e token ainda são classes concretas injetadas. Na entrevista você fala: “dava para extrair interface; neste bolso não valia o arquivo extra.”

## Auth

Não é sessão (projeto 1). API não manda cookie de formulário.

1. Login valida hash.
2. `TokenService` grava `bin2hex(random_bytes(32))` na tabela `tokens`.
3. Request seguinte manda `Authorization: Bearer …`.
4. SQL da task **sempre** filtra `user_id`. IDOR morre aqui, não no controller.

Senha: `password_hash` / `password_verify`. Nunca `md5`.

## PDO

Prepared statement. Nunca concatene SQL com input.

```php
$stmt = $this->pdo->prepare(
    'SELECT * FROM tasks WHERE id = :id AND user_id = :user_id'
);
$stmt->execute(['id' => $id, 'user_id' => $userId]);
```

SQLite para caber no bolso. Schema roda no boot se a tabela não existe.

## Como rodar

```bash
cd projects/02-api-mvc
php -S localhost:8001 -t public
```

Curls no `projects/02-api-mvc/README.md`.

## Na entrevista

> "Eu separei domínio, caso de uso e PDO. O controller não escreve SQL. Auth é token na tabela, não sessão. Toda query de task leva `user_id`. Se a vaga usar Laravel, o Eloquent entra no lugar do repository concreto — a ideia das camadas continua."

Se puxarem “isso não é Clean Architecture de verdade”: você concorda. Entidade anêmica, sem use case bus, sem DTO de borda. É o tamanho que um júnior consegue reescrever numa tarde.

## Recapitulando

- Front controller + router na mão
- Interface no domínio, PDO na infraestrutura
- Token Bearer, não `$_SESSION`
- 422 / 401 / 404 / 409 / 405 saem do caso de uso via `AppException`
- Projeto de bolso: SQLite, sem Composer obrigatório

## Exercícios práticos

### Exercício 1

**Enunciado:**
Adicione `POST /logout` que apaga o token atual. 204. Sem token → 401.

<details>
<summary>Solução</summary>

Em `TokenService`, um `revoke(string $token): void` com `DELETE FROM tokens WHERE token = :token`. Rota autenticada: lê o Bearer, apaga, 204. Não precisa apagar o user.

</details>

### Exercício 2

**Enunciado:**
Um user não pode ver a task do outro. Prove com dois cadastros e um GET no id alheio.

<details>
<summary>Solução</summary>

Registra João e Maria. João cria a task 1. Login da Maria, `GET /tasks/1` → 404 (o repository filtra `user_id`). Não devolve 403 com o id — você nem admite que existe.

</details>

### Exercício 3

**Enunciado:**
Extraia `PasswordHasher` como interface. Por quê?

<details>
<summary>Solução</summary>

Hoje `RegisterUser` e `LoginUser` conhecem `NativePasswordHasher` (infra). Interface no domínio/application: o caso de uso não muda se você trocar o algo. DIP de verdade. Neste bolso era opcional; na entrevista você demonstra que **sabe** onde cortar.

</details>

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
