# 8.6 Criptografia

## Resumo

> **Criptografia** — transformar dados em forma ilegível para proteger a confidencialidade.
>
> **Tipos:** Simétrica (AES-256-CBC via Crypt::encrypt()), Hash (bcrypt via Hash::make()), Signing (URL::signedRoute()).
>
> **Importante:** APP_KEY — chave de criptografia (php artisan key:generate). Criptografia para dado reversível (cartão de crédito), hash para irreversível (senha).

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Criptografia — transformar dados em forma ilegível. O Laravel usa AES-256-CBC para criptografia simétrica.

**Tipos:**
- **Simétrica** — uma chave (AES)
- **Assimétrica** — par de chaves (RSA)
- **Hash** — só de ida (bcrypt, argon2)

---

## Como funciona

**Criptografia simétrica (Laravel Crypt):**

```php
use Illuminate\Support\Facades\Crypt;

// Criptografar
$encrypted = Crypt::encryptString('dados secretos');

// Descriptografar
$decrypted = Crypt::decryptString($encrypted);

// Criptografar array/objeto
$encrypted = Crypt::encrypt(['card' => '1234-5678-9012-3456']);
$decrypted = Crypt::decrypt($encrypted);
```

**Criptografia automática no model:**

```php
// Model
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Model
{
    protected function creditCard(): Attribute
    {
        return Attribute::make(
            get: fn($value) => Crypt::decryptString($value),
            set: fn($value) => Crypt::encryptString($value),
        );
    }
}

// Uso
$user->credit_card = '1234-5678-9012-3456';  // Criptografa sozinho
$user->save();

$card = $user->credit_card;  // Descriptografa sozinho
```

**Hash (só de ida):**

```php
use Illuminate\Support\Facades\Hash;

// Hash da senha
$hashed = Hash::make('senha');

// Checagem
if (Hash::check('senha', $hashed)) {
    // Senha correta
}

// Checar se precisa de rehash (o algoritmo mudou)
if (Hash::needsRehash($hashed)) {
    $hashed = Hash::make('senha');
}
```

---

## Quando usar

**Criptografia para:**
- Cartão de crédito
- Dados pessoais (CPF, endereço)
- Chaves de API
- Quando precisa descriptografar

**Hash para:**
- Senhas
- Tokens
- Quando não precisa descriptografar

---

## Exemplo prático

**Criptografar dados sensíveis:**

```php
// Migration
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->text('encrypted_ssn')->nullable();  // CPF
    $table->text('encrypted_credit_card')->nullable();
    $table->timestamps();
});

// Model
class User extends Model
{
    protected function ssn(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: fn($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    protected function creditCard(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: fn($value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}

// Uso
$user = User::create([
    'email' => 'joao@email.com',
    'ssn' => '123-45-6789',  // Criptografado no banco
    'credit_card' => '1234-5678-9012-3456',  // Criptografado no banco
]);

echo $user->ssn;  // 123-45-6789 (descriptografado)
```

**APP_KEY (chave de criptografia):**

```bash
# Gerar a chave
php artisan key:generate

# .env
APP_KEY=base64:...

# IMPORTANTE: Não commitar no git, guardar em segurança
# Se perder a chave, não dá para descriptografar
```

**Criptografia no config:**

```php
// Criptografar no config
'api_key' => env('API_KEY'),

// .env (NÃO criptografado)
API_KEY=chave-secreta-aqui

// Uso
$apiKey = config('services.api_key');

// Alternativa: Laravel Secrets
php artisan env:encrypt
// Cria .env.encrypted

php artisan env:decrypt
```

**Signing (assinatura de dados):**

```php
use Illuminate\Support\Facades\URL;

// Criar URL assinado
$url = URL::temporarySignedRoute(
    'unsubscribe',
    now()->addMinutes(30),
    ['user' => $user->id]
);

// Checar a assinatura (middleware)
Route::get('/unsubscribe/{user}', [UnsubscribeController::class, 'handle'])
    ->name('unsubscribe')
    ->middleware('signed');

// Checar na mão
if ($request->hasValidSignature()) {
    // Assinatura válida
}
```

**Hash de tokens:**

```php
// Criar o token
$token = Str::random(60);
$hashedToken = hash('sha256', $token);

// Salvar no banco
PasswordReset::create([
    'email' => $user->email,
    'token' => $hashedToken,
    'created_at' => now(),
]);

// Enviar o token em texto puro para o usuário
Mail::to($user)->send(new ResetPassword($token));

// Checagem
$hashedToken = hash('sha256', $request->input('token'));
$reset = PasswordReset::where('token', $hashedToken)->first();
```

**Database Encryption (no banco):**

```sql
-- MySQL (AES_ENCRYPT/AES_DECRYPT)
INSERT INTO users (email, encrypted_data)
VALUES ('joao@email.com', AES_ENCRYPT('segredo', 'chave'));

SELECT AES_DECRYPT(encrypted_data, 'chave') FROM users;

-- PostgreSQL (pgcrypto)
CREATE EXTENSION pgcrypto;

INSERT INTO users (email, encrypted_data)
VALUES ('joao@email.com', pgp_sym_encrypt('segredo', 'chave'));

SELECT pgp_sym_decrypt(encrypted_data, 'chave') FROM users;
```

**Searchable Encryption (para busca):**

```php
// Hash para busca
class User extends Model
{
    protected function email(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                $this->attributes['email_hash'] = hash('sha256', strtolower($value));
                return Crypt::encryptString($value);
            }
        );
    }
}

// Migration
Schema::create('users', function (Blueprint $table) {
    $table->text('email');  // Criptografado
    $table->string('email_hash')->index();  // Para busca
});

// Busca
$user = User::where('email_hash', hash('sha256', strtolower($searchEmail)))->first();
```

**Rotação de chaves:**

```php
// Ao trocar o APP_KEY, precisa criptografar de novo
php artisan tinker

// Chave antiga
$oldKey = config('app.previous_key');
config(['app.key' => $oldKey]);

// Descriptografar
$decrypted = Crypt::decryptString($user->credit_card);

// Chave nova
$newKey = config('app.key');
config(['app.key' => $newKey]);

// Criptografar de novo
$user->credit_card = $decrypted;
$user->save();
```

**Secure Random (para tokens):**

```php
use Illuminate\Support\Str;

// String aleatória (criptograficamente segura)
$token = Str::random(40);

// UUID
$uuid = Str::uuid();

// Ordered UUID (para primary keys)
$uuid = Str::orderedUuid();
```

---

## Na entrevista

> "Criptografia transforma o dado em forma ilegível. O Laravel usa AES-256-CBC (Crypt::encryptString()). APP_KEY é a chave (php artisan key:generate). Criptografia automática via Attribute no model. Hash::make() para senha (bcrypt, irreversível). Signing para URL (URL::temporarySignedRoute()). Criptografia para cartão, dado pessoal. Hash para senha, token. Database encryption no banco (AES_ENCRYPT). Searchable encryption via hash para buscar."

---

## Exercícios práticos

### Exercício 1: Implemente criptografia automática no model

Crie o model `PaymentMethod` com criptografia automática do número do cartão.

<details>
<summary>Solução</summary>

```php
// 1. Migration
Schema::create('payment_methods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('card_number'); // Número do cartão criptografado
    $table->string('card_holder');
    $table->string('card_last_four'); // Para exibir (****1234)
    $table->string('card_type'); // visa, mastercard
    $table->string('expiry_month');
    $table->string('expiry_year');
    $table->timestamps();
});

// 2. PaymentMethod Model
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'card_number',
        'card_holder',
        'card_last_four',
        'card_type',
        'expiry_month',
        'expiry_year',
    ];

    /**
     * Criptografa/descriptografa o número do cartão sozinho
     */
    protected function cardNumber(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: function ($value) {
                // Guardar os últimos 4 dígitos
                $this->attributes['card_last_four'] = substr($value, -4);

                // Detectar o tipo do cartão
                $this->attributes['card_type'] = $this->detectCardType($value);

                return Crypt::encryptString($value);
            }
        );
    }

    /**
     * Detectar o tipo pelo número
     */
    private function detectCardType(string $number): string
    {
        $patterns = [
            'visa' => '/^4/',
            'mastercard' => '/^5[1-5]/',
            'amex' => '/^3[47]/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $number)) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Número do cartão mascarado
     */
    public function getMaskedCardNumberAttribute(): string
    {
        return '**** **** **** ' . $this->card_last_four;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 3. Controller
class PaymentMethodController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'card_number' => 'required|string|size:16|regex:/^[0-9]+$/',
            'card_holder' => 'required|string|max:255',
            'expiry_month' => 'required|digits:2|min:1|max:12',
            'expiry_year' => 'required|digits:4|min:' . date('Y'),
        ]);

        $paymentMethod = $request->user()->paymentMethods()->create([
            'card_number' => $validated['card_number'], // Criptografa sozinho
            'card_holder' => $validated['card_holder'],
            'expiry_month' => $validated['expiry_month'],
            'expiry_year' => $validated['expiry_year'],
        ]);

        return response()->json([
            'id' => $paymentMethod->id,
            'masked_number' => $paymentMethod->masked_card_number,
            'card_type' => $paymentMethod->card_type,
        ]);
    }

    public function show(PaymentMethod $paymentMethod)
    {
        $this->authorize('view', $paymentMethod);

        return response()->json([
            'id' => $paymentMethod->id,
            'card_holder' => $paymentMethod->card_holder,
            'masked_number' => $paymentMethod->masked_card_number,
            'card_type' => $paymentMethod->card_type,
            'expiry_month' => $paymentMethod->expiry_month,
            'expiry_year' => $paymentMethod->expiry_year,
            // NÃO envia o número completo na API
        ]);
    }
}

// 4. Policy
class PaymentMethodPolicy
{
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->id === $paymentMethod->user_id;
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->id === $paymentMethod->user_id;
    }
}
```
</details>

### Exercício 2: Crie signed URL para download de arquivo

Implemente o download seguro via URL assinado temporário.

<details>
<summary>Solução</summary>

```php
// 1. FileController
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FileController extends Controller
{
    /**
     * Criar URL assinado para download
     */
    public function createDownloadLink(File $file)
    {
        $this->authorize('download', $file);

        // URL assinado temporário (vale 30 minutos)
        $url = URL::temporarySignedRoute(
            'files.download',
            now()->addMinutes(30),
            ['file' => $file->id]
        );

        return response()->json([
            'download_url' => $url,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /**
     * Download pelo URL assinado
     */
    public function download(Request $request, File $file)
    {
        // Checagem da assinatura (automática via middleware)
        if (!$request->hasValidSignature()) {
            abort(403, 'Link inválido ou expirado');
        }

        // Checar se o arquivo existe
        if (!Storage::disk('private')->exists($file->path)) {
            abort(404, 'Arquivo não encontrado');
        }

        // Log do download
        activity()
            ->causedBy($request->user())
            ->performedOn($file)
            ->log('downloaded');

        // Entregar o arquivo
        return Storage::disk('private')->download(
            $file->path,
            $file->original_name
        );
    }
}

// 2. Routes
Route::middleware('auth')->group(function () {
    Route::post('/files/{file}/download-link', [FileController::class, 'createDownloadLink'])
        ->name('files.download-link');
});

Route::get('/files/{file}/download', [FileController::class, 'download'])
    ->name('files.download')
    ->middleware('signed'); // Checagem da assinatura

// 3. File Model
class File extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'original_name',
        'size',
        'mime_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 4. FilePolicy
class FilePolicy
{
    public function download(User $user, File $file): bool
    {
        // Dono ou arquivo público
        return $user->id === $file->user_id || $file->is_public;
    }
}

// 5. Alternativa: URL assinado permanente
public function createPermanentLink(File $file)
{
    $this->authorize('download', $file);

    $url = URL::signedRoute('files.download', ['file' => $file->id]);

    return response()->json(['download_url' => $url]);
}

// 6. Criar a assinatura na mão
use Illuminate\Support\Facades\Hash;

public function createCustomSignedUrl(File $file): string
{
    $signature = Hash::make($file->id . config('app.key'));

    return route('files.download', [
        'file' => $file->id,
        'signature' => $signature,
    ]);
}

public function downloadWithCustomSignature(Request $request, File $file)
{
    $signature = $request->query('signature');

    if (!Hash::check($file->id . config('app.key'), $signature)) {
        abort(403, 'Assinatura inválida');
    }

    return Storage::disk('private')->download($file->path);
}
```
</details>

### Exercício 3: Implemente searchable encryption

Crie um sistema de armazenamento de email com busca mesmo criptografado.

<details>
<summary>Solução</summary>

```php
// 1. Migration
Schema::create('user_emails', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('email'); // Email criptografado
    $table->string('email_hash')->index(); // Hash para busca
    $table->boolean('is_primary')->default(false);
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();

    $table->unique('email_hash');
});

// 2. UserEmail Model
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class UserEmail extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'is_primary',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Criptografa o email + cria hash para busca
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: function ($value) {
                $normalized = strtolower(trim($value));

                // Criar hash para busca
                $this->attributes['email_hash'] = hash('sha256', $normalized);

                return Crypt::encryptString($value);
            }
        );
    }

    /**
     * Buscar por email (via hash)
     */
    public static function findByEmail(string $email): ?self
    {
        $hash = hash('sha256', strtolower(trim($email)));

        return static::where('email_hash', $hash)->first();
    }

    /**
     * Scope para busca pelo hash
     */
    public function scopeWhereEmail($query, string $email)
    {
        $hash = hash('sha256', strtolower(trim($email)));

        return $query->where('email_hash', $hash);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// 3. Controller
class UserEmailController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        // Checar se o email já está em uso
        if (UserEmail::findByEmail($validated['email'])) {
            return back()->withErrors([
                'email' => 'Este email já está em uso',
            ]);
        }

        $userEmail = $request->user()->emails()->create([
            'email' => $validated['email'], // Criptografa sozinho
        ]);

        // Enviar email de verificação
        $userEmail->sendVerificationNotification();

        return redirect()->back()->with('success', 'Email adicionado');
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Busca no email criptografado (via hash)
        $userEmail = UserEmail::whereEmail($validated['email'])->first();

        if (!$userEmail) {
            return response()->json(['message' => 'Email não encontrado'], 404);
        }

        return response()->json([
            'id' => $userEmail->id,
            'user_id' => $userEmail->user_id,
            'is_verified' => $userEmail->verified_at !== null,
        ]);
    }
}

// 4. Trait para reutilizar
trait HasSearchableEncryption
{
    /**
     * Criar hash para campo searchable
     */
    protected static function makeSearchableHash(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Buscar por campo criptografado
     */
    public static function findByEncryptedField(string $field, string $value): ?self
    {
        $hash = static::makeSearchableHash($value);
        $hashField = $field . '_hash';

        return static::where($hashField, $hash)->first();
    }
}

// Uso
class UserEmail extends Model
{
    use HasSearchableEncryption;

    // ...
}

$userEmail = UserEmail::findByEncryptedField('email', 'joao@email.com');
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
