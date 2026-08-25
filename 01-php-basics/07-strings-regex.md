# 1.7 Strings e expressões regulares

> **TL;DR**
> Para Unicode (acentos) use as funções mb_* (mb_strlen, mb_substr, mb_strtolower). strpos() devolve 0 (falsy), compare com !== false. PHP 8.0 trouxe str_contains, str_starts_with, str_ends_with. Para email use filter_var, não regex. preg_match acha a primeira ocorrência, preg_match_all acha todas. No Laravel tem o helper Str e validação regex.

## Conteúdo

- [Trabalhando com strings](#trabalhando-com-strings)
- [substr, mb_substr, str_replace](#substr-mb_substr-str_replace)
- [explode, implode, str_split](#explode-implode-str_split)
- [strpos, str_contains, str_starts_with (PHP 8.0+)](#strpos-str_contains-str_starts_with-php-80)
- [Expressões regulares: preg_match, preg_replace](#expressões-regulares-preg_match-preg_replace)
- [Padrões principais de regex](#padrões-principais-de-regex)
- [Regex populares](#regex-populares)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Trabalhando com strings

**O que é:**
Funções para mexer com texto.

**Como funciona:**
```php
$str = 'Hello, World!';

// strlen — tamanho da string (bytes, NÃO caracteres!)
echo strlen($str);  // 13

// mb_strlen — tamanho em caracteres (Unicode)
$portuguese = 'Ação';
echo strlen($portuguese);     // 6 (bytes UTF-8)
echo mb_strlen($portuguese);  // 4 (caracteres)

// strtolower / strtoupper — caixa
echo strtolower($str);  // "hello, world!"
echo strtoupper($str);  // "HELLO, WORLD!"

// mb_* para Unicode
$text = 'AÇÃO';
echo strtolower($text);     // "aÇÃo" (não funciona!)
echo mb_strtolower($text);  // "ação" ✅

// ucfirst / ucwords — primeira letra maiúscula
echo ucfirst('hello');   // "Hello"
echo ucwords('hello world');  // "Hello World"

// trim — tira espaço das pontas
$input = '  hello  ';
echo trim($input);   // "hello"
echo ltrim($input);  // "hello  " (só da esquerda)
echo rtrim($input);  // "  hello" (só da direita)
```

**Quando usar:**
Tratar input do usuário, formatar texto.

**Exemplo prático:**
```php
// Validar e limpar input
$email = trim($request->input('email'));
$email = strtolower($email);

// Formatando o nome
$name = ucwords(mb_strtolower($request->input('name')));

// Preparar pra busca no banco
$search = trim($request->input('search'));
$search = preg_replace('/\s+/', ' ', $search);  // Tira espaço sobrando

// Laravel Request
$validated = $request->validate([
    'email' => 'required|email|max:255',
]);
```

**Na entrevista:**
> "strlen devolve bytes, mb_strlen devolve caracteres (Unicode). Para acento eu sempre uso mb_* (mb_strlen, mb_strtolower, mb_substr). trim tira espaço das pontas."

---

## substr, mb_substr, str_replace

**Como funciona:**
```php
$str = 'Hello, World!';

// substr — pega um pedaço da string
echo substr($str, 0, 5);   // "Hello"
echo substr($str, 7);      // "World!"
echo substr($str, -6);     // "World!" (do fim)
echo substr($str, 0, -7);  // "Hello" (até -7 do fim)

// mb_substr para Unicode
$text = 'Olá, mundo!';
echo substr($text, 0, 3);     // "Ol�" (corta no meio do á!)
echo mb_substr($text, 0, 3);  // "Olá" ✅

// str_replace — troca um trecho
echo str_replace('World', 'PHP', $str);  // "Hello, PHP!"

// Troca em várias ocorrências
$text = 'Hello, World! Hello, PHP!';
echo str_replace('Hello', 'Hi', $text);  // "Hi, World! Hi, PHP!"

// Array de trocas
$text = str_replace(['Hello', 'World'], ['Hi', 'PHP'], $text);
// "Hi, PHP! Hi, PHP!"

// str_ireplace — ignora caixa
echo str_ireplace('hello', 'Hi', $text);  // "Hi, World!"
```

**Quando usar:**
Pegar um pedaço da string, trocar texto.

**Exemplo prático:**
```php
// Encurtar descrição
$description = 'Descrição bem longa do produto...';
$short = mb_substr($description, 0, 100) . '...';

// Trocar placeholder no template
$template = 'Olá, {name}! Seu pedido #{order_id} está pronto.';
$message = str_replace(
    ['{name}', '{order_id}'],
    [$user->name, $order->id],
    $template
);

// Limpar telefone
$phone = '+55 (11) 98765-4321';
$clean = str_replace(['+', ' ', '(', ')', '-'], '', $phone);
// "5511987654321"

// Laravel Str helper
use Illuminate\Support\Str;

$short = Str::limit($description, 100);
$slug = Str::slug('Título do artigo');  // "titulo-do-artigo"
```

**Na entrevista:**
> "substr pega um pedaço da string (por bytes), mb_substr pega por caracteres. str_replace troca trechos (aceita array). Para Unicode eu uso mb_substr. No Laravel tem o helper Str com uns métodos úteis."

---

## explode, implode, str_split

**Como funciona:**
```php
// explode — parte a string em array
$csv = 'apple,banana,orange';
$fruits = explode(',', $csv);  // ['apple', 'banana', 'orange']

// Limite de pedaços
$text = 'one:two:three:four';
$parts = explode(':', $text, 2);  // ['one', 'two:three:four']

// implode (join) — junta o array numa string
$fruits = ['apple', 'banana', 'orange'];
$csv = implode(',', $fruits);  // "apple,banana,orange"

// str_split — parte em caracteres
$str = 'hello';
$chars = str_split($str);  // ['h', 'e', 'l', 'l', 'o']

// Partir de N em N
$chunks = str_split($str, 2);  // ['he', 'll', 'o']

// mb_str_split para Unicode (PHP 7.4+)
$text = 'Ação';
$chars = mb_str_split($text);  // ['A', 'ç', 'ã', 'o']
```

**Quando usar:**
Parsear CSV, juntar array, partir string.

**Exemplo prático:**
```php
// Parsear tags
$tagString = 'php, laravel, mysql';
$tags = explode(',', $tagString);
$tags = array_map('trim', $tags);  // ['php', 'laravel', 'mysql']

// Gerar CSV
$users = User::all();
$csv = "name,email,age\n";

foreach ($users as $user) {
    $csv .= implode(',', [$user->name, $user->email, $user->age]) . "\n";
}

// Laravel Collection
$tagString = $post->tags->pluck('name')->implode(', ');

// Segmentos da URL
$url = '/api/v1/users/123';
$segments = explode('/', trim($url, '/'));  // ['api', 'v1', 'users', '123']

// Laravel
$segments = request()->segments();  // ['api', 'v1', 'users', '123']
```

**Na entrevista:**
> "explode parte a string em array pelo separador. implode (join) junta o array numa string. str_split parte em caracteres (por bytes), mb_str_split parte por caracteres Unicode."

---

## strpos, str_contains, str_starts_with (PHP 8.0+)

**Como funciona:**
```php
$str = 'Hello, World!';

// strpos — posição do trecho (ou false)
$pos = strpos($str, 'World');  // 7
$pos = strpos($str, 'PHP');    // false

// Checar se existe (NÃO use == pra checar!)
if (strpos($str, 'Hello') !== false) {
    echo 'Encontrado';
}

// ⚠️ Erro clássico
if (strpos($str, 'Hello')) {  // ❌ Devolve 0 (falsy!)
    echo 'Nunca vai executar';
}

// PHP 8.0: str_contains (mais fácil)
if (str_contains($str, 'World')) {
    echo 'Encontrado';
}

// str_starts_with (PHP 8.0+)
if (str_starts_with($str, 'Hello')) {
    echo 'Começa com Hello';
}

// str_ends_with (PHP 8.0+)
if (str_ends_with($str, '!')) {
    echo 'Termina com !';
}

// stripos — ignora caixa
$pos = stripos($str, 'hello');  // 0
```

**Quando usar:**
Checar se tem um trecho, validar, parsear.

**Exemplo prático:**
```php
// Checar extensão do arquivo
$filename = 'document.pdf';

// PHP < 8.0
if (strpos($filename, '.pdf') !== false) {
    // ...
}

// PHP 8.0+
if (str_ends_with($filename, '.pdf')) {
    // É PDF
}

// Checar URL
$url = 'https://example.com/api/users';

if (str_starts_with($url, 'https://')) {
    // Conexão segura
}

// Filtrar por prefixo
$routes = ['admin/users', 'admin/posts', 'api/users'];
$adminRoutes = array_filter($routes, fn($r) => str_starts_with($r, 'admin/'));

// Laravel Str helper
use Illuminate\Support\Str;

if (Str::startsWith($url, 'https://')) {
    // ...
}

if (Str::endsWith($filename, '.pdf')) {
    // ...
}

if (Str::contains($email, '@gmail.com')) {
    // ...
}
```

**Na entrevista:**
> "strpos devolve a posição ou false (tem que checar !== false, não ==). PHP 8.0 trouxe str_contains, str_starts_with, str_ends_with — mais fácil pra checar. No Laravel tem Str::startsWith, Str::endsWith, Str::contains."

---

## Expressões regulares: preg_match, preg_replace

**O que é:**
Padrões pra achar e trocar trechos na string.

**Como funciona:**
```php
$text = 'Meu email: teste@email.com';

// preg_match — primeira ocorrência
if (preg_match('/\w+@\w+\.\w+/', $text, $matches)) {
    echo $matches[0];  // "teste@email.com"
}

// Grupos de captura
$text = 'Data: 2024-01-15';
if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $matches)) {
    $year = $matches[1];   // "2024"
    $month = $matches[2];  // "01"
    $day = $matches[3];    // "15"
}

// Grupos nomeados
if (preg_match('/(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/', $text, $matches)) {
    $year = $matches['year'];
}

// preg_match_all — todas as ocorrências
$text = 'Email: teste@email.com, admin@site.com';
preg_match_all('/\w+@\w+\.\w+/', $text, $matches);
var_dump($matches[0]);  // ["teste@email.com", "admin@site.com"]

// preg_replace — troca pelo padrão
$text = 'Preço: 1000 reais';
$text = preg_replace('/\d+/', '2000', $text);  // "Preço: 2000 reais"

// Com grupos de captura
$text = '2024-01-15';
$text = preg_replace('/(\d{4})-(\d{2})-(\d{2})/', '$3.$2.$1', $text);
// "15.01.2024"
```

**Quando usar:**
Validar email, telefone, URL, parsear formato chato.

**Exemplo prático:**
```php
// Validar email
if (preg_match('/^[\w\.\-]+@[\w\.\-]+\.\w+$/', $email)) {
    // Email válido
}

// Melhor: filter_var
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Email válido
}

// Parsear telefone
$phone = '+55 (11) 98765-4321';
if (preg_match('/\+55 \((\d{2})\) (\d{5})-(\d{4})/', $phone, $matches)) {
    $ddd = $matches[1];  // "11"
    $number = $matches[2] . $matches[3];  // "987654321"
}

// Limpar tags HTML
$text = '<p>Hello <b>World</b></p>';
$clean = preg_replace('/<[^>]*>/', '', $text);  // "Hello World"

// Melhor: strip_tags
$clean = strip_tags($text);

// Laravel Validation (usa regex)
$validated = $request->validate([
    'phone' => 'required|regex:/^\+55 \(\d{2}\) \d{5}-\d{4}$/',
]);
```

**Na entrevista:**
> "preg_match acha a primeira ocorrência, preg_match_all acha todas. preg_replace troca pelo padrão. Uso pra validar formato chato (telefone, data). Pra email eu uso filter_var, pra HTML uso strip_tags."

---

## Padrões principais de regex

**O que é:**
Sintaxe pra descrever o formato.

**Como funciona:**
```php
// Metacaracteres básicos
// . — qualquer caractere (exceto \n)
// \d — dígito [0-9]
// \w — letra, dígito, _ [a-zA-Z0-9_]
// \s — espaço em branco (space, tab, newline)
// \D, \W, \S — negação

// Quantificadores
// * — 0 ou mais
// + — 1 ou mais
// ? — 0 ou 1
// {n} — exatamente n
// {n,} — n ou mais
// {n,m} — de n até m

// Exemplos
preg_match('/\d+/', 'abc123');        // true (um ou mais dígitos)
preg_match('/\d{4}/', '2024');        // true (exatamente 4 dígitos)
preg_match('/\w{3,10}/', 'hello');    // true (3-10 letras/dígitos)

// Âncoras
// ^ — começo da string
// $ — fim da string
preg_match('/^\d{4}$/', '2024');      // true (SÓ 4 dígitos)
preg_match('/^\d{4}$/', '2024abc');   // false

// Classes de caracteres
// [abc] — a, b ou c
// [a-z] — qualquer letra minúscula
// [^abc] — NÃO a, b, c

preg_match('/[0-9]+/', 'abc123');     // true
preg_match('/[a-z]+/', 'Hello');      // true ("ello")
preg_match('/[^0-9]+/', 'abc123');    // true ("abc")

// Grupos e alternativas
// (abc) — grupo
// a|b — a OU b

preg_match('/(http|https):\/\//', 'https://example.com');  // true
```

**Quando usar:**
Validar formato, parsear, limpar dado.

**Exemplo prático:**
```php
// Validar login (letra, dígito, _, 3-20 caracteres)
if (preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    // OK
}

// Validar senha (mínimo 8, letra + dígito)
if (preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
    // OK
}

// Extrair hashtags
$text = 'Olá #php #laravel #coding';
preg_match_all('/#(\w+)/', $text, $matches);
$hashtags = $matches[1];  // ["php", "laravel", "coding"]

// Trocar URL por link
$text = 'Site: https://example.com';
$text = preg_replace(
    '/(https?:\/\/[^\s]+)/',
    '<a href="$1">$1</a>',
    $text
);
// "Site: <a href="https://example.com">https://example.com</a>"

// Laravel Validation
$validated = $request->validate([
    'username' => 'required|regex:/^[a-zA-Z0-9_]{3,20}$/',
    'password' => 'required|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d)/',
]);
```

**Na entrevista:**
> "Regex: metacaracteres (\d, \w, \s), quantificadores (+, *, ?), âncoras (^, $), classes ([a-z]). Uso pra validar formato complexo. No Laravel tem validação regex no form."

---

## Regex populares

**O que é:**
Padrões prontos pra tarefa comum.

```php
// Email
$email = '/^[\w\.\-]+@[\w\.\-]+\.\w+$/';
// Melhor: filter_var($email, FILTER_VALIDATE_EMAIL)

// Telefone (BR)
$phone = '/^\+55 \(\d{2}\) \d{5}-\d{4}$/';
// +55 (11) 98765-4321

// URL
$url = '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/';

// Endereço IP
$ip = '/^(\d{1,3}\.){3}\d{1,3}$/';
// Não valida o intervalo (0-255)

// Data (YYYY-MM-DD)
$date = '/^\d{4}-\d{2}-\d{2}$/';

// Hora (HH:MM)
$time = '/^([01]\d|2[0-3]):([0-5]\d)$/';

// Senha (mínimo 8, letra + dígito + caractere especial)
$password = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/';

// Slug (URL-friendly)
$slug = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

// Hashtag
$hashtag = '/#[a-zA-Z0-9_]+/';

// Mention (@username)
$mention = '/@[a-zA-Z0-9_]+/';
```

**Exemplo prático:**
```php
// Validação de form
class RegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'regex:/^[a-zA-Z0-9_]{3,20}$/',
                'unique:users',
            ],
            'email' => 'required|email|unique:users',
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])/',
            ],
            'phone' => 'required|regex:/^\+55 \(\d{2}\) \d{5}-\d{4}$/',
        ];
    }
}

// Extrair menções
$text = '@joao escreveu @pedro olá';
preg_match_all('/@(\w+)/', $text, $matches);
$mentions = $matches[1];  // ["joao", "pedro"]

// Trocar URL automaticamente
function linkify(string $text): string
{
    return preg_replace(
        '/(https?:\/\/[^\s]+)/',
        '<a href="$1" target="_blank">$1</a>',
        $text
    );
}
```

**Na entrevista:**
> "Pra coisa comum (email, URL, telefone) eu uso padrão pronto ou função nativa (filter_var no email). No Laravel, validação regex pra formato específico. @mention e #hashtag eu extraio com preg_match_all."

---

## Recapitulando

**Strings:**
- `strlen` (bytes) vs `mb_strlen` (caracteres)
- Funções `mb_*` para Unicode (mb_strtolower, mb_substr)
- `trim` — tira espaço das pontas
- `substr`, `str_replace` — extrair e trocar
- `explode` / `implode` — partir e juntar
- `strpos` (PHP < 8.0) vs `str_contains`, `str_starts_with` (PHP 8.0+)

**Expressões regulares:**
- `preg_match` — primeira ocorrência
- `preg_match_all` — todas as ocorrências
- `preg_replace` — troca pelo padrão
- Metacaracteres: `\d`, `\w`, `\s`, `.`
- Quantificadores: `+`, `*`, `?`, `{n,m}`
- Âncoras: `^`, `$`
- Classes: `[a-z]`, `[^0-9]`

**Importante na entrevista:**
- Para acentos/Unicode use as funções `mb_*`
- `strpos()` devolve `0` (falsy!), compare com `!== false`
- PHP 8.0: `str_contains`, `str_starts_with`, `str_ends_with`
- Para email use `filter_var`, não regex
- No Laravel tem o helper `Str` e validação `regex`

---

## Exercícios práticos

### Exercício 1: O problema do strpos
**Enunciado:** Ache e corrija o erro na checagem de substring.

<details>
<summary>Solução</summary>

```php
<?php

$url = 'https://example.com/api/users';

// ❌ ERRADO
if (strpos($url, 'https')) {
    echo 'Conexão segura';
} else {
    echo 'Conexão insegura';
}
// Vai imprimir: "Conexão insegura" ❌
// strpos devolveu 0 (a posição), e 0 = falsy!

// ✅ CERTO (comparação estrita)
if (strpos($url, 'https') !== false) {
    echo 'Conexão segura';
}

// ✅ PHP 8.0+ (str_contains)
if (str_contains($url, 'https')) {
    echo 'Conexão segura';
}

// ✅ PHP 8.0+ (str_starts_with)
if (str_starts_with($url, 'https://')) {
    echo 'Conexão segura';
}

// Exemplo prático
function validateImageExtension(string $filename): bool
{
    $allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];

    // ❌ ERRADO
    foreach ($allowedExtensions as $ext) {
        if (strpos($filename, $ext)) {  // Quebra no arquivo "0.jpg"
            return true;
        }
    }

    // ✅ CERTO (PHP < 8.0)
    foreach ($allowedExtensions as $ext) {
        if (strpos($filename, $ext) !== false) {
            return true;
        }
    }

    // ✅ CERTO (PHP 8.0+)
    foreach ($allowedExtensions as $ext) {
        if (str_ends_with($filename, $ext)) {
            return true;
        }
    }

    // ✅ AINDA MELHOR (Laravel)
    return Str::endsWith($filename, $allowedExtensions);
}

// Laravel Validation
$request->validate([
    'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp',
]);
```

**Pontos-chave:**
- `strpos()` devolve `0` quando o trecho está no começo
- `0` é falsy, então `if (strpos(...))` não funciona
- Sempre compare com `!== false`
- PHP 8.0: `str_contains`, `str_starts_with`, `str_ends_with` são mais seguros
</details>

### Exercício 2: Funções mb_* para Unicode
**Enunciado:** Corte um texto em português até 100 caracteres, do jeito certo.

<details>
<summary>Solução</summary>

```php
<?php

$text = 'Descrição bem longa do produto em português com caracteres acentuados';

// ❌ ERRADO (corta por bytes)
$short = substr($text, 0, 50);
echo $short;  // pode cortar no meio de um caractere acentuado
echo strlen($short);  // 50 bytes, menos de 50 caracteres

// ✅ CERTO (corta por caracteres)
$short = mb_substr($text, 0, 50);
echo $short;  // 50 caracteres, sem quebrar acento
echo mb_strlen($short);  // 50 caracteres

// Função pra cortar com reticências
function truncate(string $text, int $length, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    $truncated = mb_substr($text, 0, $length);

    // Cortar no último espaço
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return $truncated . $suffix;
}

echo truncate($text, 30);
// "Descrição bem longa do..."

// Laravel Str helper
use Illuminate\Support\Str;

echo Str::limit($text, 30);  // "Descrição bem longa do produto..."
echo Str::words($text, 5);   // "Descrição bem longa do produto em..."

// Comparar ignorando caixa (acentos)
$search = 'ÇÃO';
$text = 'Descrição do produto';

// ❌ ERRADO
var_dump(stripos($text, $search));  // false (não funciona com acento)

// ✅ CERTO
var_dump(mb_stripos($text, $search));  // 6 (posição)

// Ou via strtolower
$textLower = mb_strtolower($text);
$searchLower = mb_strtolower($search);
var_dump(strpos($textLower, $searchLower));  // 6
```

**Pontos-chave:**
- `strlen()` devolve bytes, `mb_strlen()` devolve caracteres
- UTF-8: letra acentuada = 2 bytes
- Sempre use `mb_*` quando tiver acento
- O helper `Str` do Laravel usa `mb_*` por baixo
</details>

### Exercício 3: Validar e limpar input do usuário
**Enunciado:** Crie uma função pra validar e limpar os dados de um form.

<details>
<summary>Solução</summary>

```php
<?php

class InputSanitizer
{
    /**
     * Limpa e valida o email
     */
    public function sanitizeEmail(string $email): ?string
    {
        // Tira espaço e joga pra minúsculo
        $email = trim($email);
        $email = mb_strtolower($email);

        // Validação
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }

    /**
     * Limpa o nome
     */
    public function sanitizeName(string $name): string
    {
        // Tira espaço sobrando
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);

        // Primeira letra maiúscula em cada palavra
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Limpa o telefone
     */
    public function sanitizePhone(string $phone): ?string
    {
        // Tira tudo que não for dígito ou +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Valida o formato
        if (preg_match('/^\+55\d{11}$/', $phone)) {
            return $phone;
        }

        return null;
    }

    /**
     * Limpa o slug da URL
     */
    public function sanitizeSlug(string $slug): string
    {
        // Tira acento
        $slug = $this->transliterate($slug);

        // Minúsculo
        $slug = mb_strtolower($slug);

        // Troca tudo que não for letra, dígito ou hífen
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);

        // Tira hífen repetido
        $slug = preg_replace('/-+/', '-', $slug);

        // Tira hífen das pontas
        return trim($slug, '-');
    }

    private function transliterate(string $text): string
    {
        $transliteration = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];

        return strtr(mb_strtolower($text), $transliteration);
    }
}

// Uso
$sanitizer = new InputSanitizer();

// Email
$email = $sanitizer->sanitizeEmail('  JoHn@Example.COM  ');
// "john@example.com"

// Nome
$name = $sanitizer->sanitizeName('  joão   silva  ');
// "João Silva"

// Telefone
$phone = $sanitizer->sanitizePhone('+55 (11) 98765-4321');
// "+5511987654321"

// Slug
$slug = $sanitizer->sanitizeSlug('Título do artigo em português!');
// "titulo-do-artigo-em-portugues"

// Laravel (validadores nativos)
$request->validate([
    'email' => 'required|email|max:255',
    'name' => 'required|string|max:100',
    'phone' => 'required|regex:/^\+55\d{11}$/',
]);

// Laravel Str helper
use Illuminate\Support\Str;

$slug = Str::slug('Título do artigo');  // "titulo-do-artigo"
$name = Str::title('joão silva');     // "João Silva"
```

**Pontos-chave:**
- Sempre limpe o input do usuário (trim, caixa)
- Use `filter_var` pra validar email
- Regex pra formato específico (telefone)
- Laravel já tem validador pronto
- `Str::slug()` tira acento sozinho
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
