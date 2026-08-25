# 8.4 Autenticação

## Resumo

> **Autenticação** — checagem de quem é o usuário (quem é você?).
>
> **Métodos:** Session-based (cookies) no web, Token-based (Bearer) na API, OAuth para provedores externos.
>
> **Importante:** Rate limiting contra brute force, Email verification para confirmar, 2FA para proteção extra, Hash::make() para guardar senha com segurança.

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
Autenticação é checar quem é o usuário (quem é você?). O Laravel já traz os mecanismos: sessão, token (Sanctum, Passport).

**Métodos:**
- Session-based — cookie com session ID
- Token-based — token Bearer (API)
- OAuth — via provedores externos

---

## Como funciona

**Session-based (web):**

```php
// Login
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();  // Proteção contra session fixation

        return redirect()->intended('/dashboard');
    }

    return back()->withErrors([
        'email' => 'Credenciais inválidas',
    ]);
});

// Logout
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
});

// Checagem de autenticação
Route::middleware('auth')->group(function () {
    Route::get('/profile', function () {
        $user = auth()->user();
        return view('profile', compact('user'));
    });
});
```

**Token-based (Sanctum na API):**

```php
// Login e emissão do token
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Credenciais inválidas'], 401);
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json(['token' => $token]);
});

// Endpoint protegido
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Logout (apagar o token)
Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logout feito']);
});
```

---

## Quando usar

**Session-based para:**
- Apps web
- Formulários tradicionais
- Server-side rendering

**Token-based para:**
- API
- Apps mobile
- SPA (Single Page Apps)

---

## Exemplo prático

**Multi-factor Authentication (2FA):**

```php
// Instalar o pacote
composer require pragmarx/google2fa-laravel

// Ativar 2FA
class TwoFactorController extends Controller
{
    public function enable(Request $request)
    {
        $user = $request->user();

        $google2fa = app('pragmarx.google2fa');
        $user->google2fa_secret = $google2fa->generateSecretKey();
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->google2fa_secret
        );

        return view('2fa.enable', compact('qrCodeUrl'));
    }

    public function verify(Request $request)
    {
        $user = $request->user();

        $valid = app('pragmarx.google2fa')->verifyKey(
            $user->google2fa_secret,
            $request->input('code')
        );

        if ($valid) {
            $user->google2fa_enabled = true;
            $user->save();

            return redirect('/dashboard');
        }

        return back()->withErrors(['code' => 'Código inválido']);
    }
}

// Middleware para checar 2FA
class Ensure2FA
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user->google2fa_enabled && !session('2fa_verified')) {
            return redirect('/2fa/verify');
        }

        return $next($request);
    }
}
```

**Remember Me:**

```php
// Login com Remember Me
if (Auth::attempt($credentials, $remember = true)) {
    // Cookie por 5 anos
}

// Checagem do Remember token
if (Auth::viaRemember()) {
    // Usuário logado via Remember Me
}
```

**Rate Limiting (proteção contra brute force):**

```php
use Illuminate\Support\Facades\RateLimiter;

Route::post('/login', function (Request $request) {
    $key = 'login:' . $request->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $seconds = RateLimiter::availableIn($key);

        return back()->withErrors([
            'email' => "Muitas tentativas. Tente de novo em {$seconds} segundos.",
        ]);
    }

    if (Auth::attempt($request->only('email', 'password'))) {
        RateLimiter::clear($key);  // Zerar depois do sucesso
        return redirect('/dashboard');
    }

    RateLimiter::hit($key, 60);  // +1 tentativa, TTL 60 segundos

    return back()->withErrors(['email' => 'Credenciais inválidas']);
});
```

**Email Verification:**

```php
// User model
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
}

// routes/web.php
Auth::routes(['verify' => true]);

// Proteção da rota
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Reenviar o email de verificação
Route::post('/email/resend', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('message', 'Link de verificação enviado!');
});
```

**Password Reset:**

```php
use Illuminate\Support\Facades\Password;

// Enviar o link de reset
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    return $status === Password::RESET_LINK_SENT
        ? back()->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
});

// Resetar a senha
Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed|min:8',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->save();
        }
    );

    return $status === Password::PASSWORD_RESET
        ? redirect('/login')->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
});
```

**Social Authentication (Socialite):**

```bash
composer require laravel/socialite
```

```php
// config/services.php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URL'),
],

// routes/web.php
use Laravel\Socialite\Facades\Socialite;

Route::get('/auth/github', function () {
    return Socialite::driver('github')->redirect();
});

Route::get('/auth/github/callback', function () {
    $githubUser = Socialite::driver('github')->user();

    $user = User::updateOrCreate([
        'email' => $githubUser->email,
    ], [
        'name' => $githubUser->name,
        'github_id' => $githubUser->id,
        'github_token' => $githubUser->token,
    ]);

    Auth::login($user);

    return redirect('/dashboard');
});
```

**Password Hashing:**

```php
use Illuminate\Support\Facades\Hash;

// Hash
$hashed = Hash::make('senha');

// Checagem
if (Hash::check('senha', $user->password)) {
    // Senha correta
}

// Checar se precisa de rehash
if (Hash::needsRehash($user->password)) {
    $user->password = Hash::make('nova-senha');
    $user->save();
}
```

---

## Na entrevista

> "Autenticação é checar quem você é. Session-based no web (Auth::attempt(), auth()->user()). Token-based na API (Sanctum, createToken()). Auth middleware protege as rotas. Rate limiting contra brute force (RateLimiter::tooManyAttempts()). Email verification com MustVerifyEmail. Password reset com Password::sendResetLink(). 2FA com google2fa. Remember Me no segundo parâmetro do attempt(). Socialite para OAuth. Hash::make() para hash da senha, Hash::check() para conferir."

---

## Exercícios práticos

### Exercício 1: Implemente Rate Limiting no login

**Enunciado:** Proteja o endpoint de login contra brute force com rate limiting.

<details>
<summary>Solução</summary>

```php
// LoginController
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Chave do rate limiting (email + IP)
        $key = 'login:' . $request->email . ':' . $request->ip();

        // Checagem do limite (5 tentativas por minuto)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => [
                    "Muitas tentativas de login. Tente de novo em {$seconds} segundos."
                ],
            ]);
        }

        // Tentativa de autenticação
        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            // Zerar o contador no login com sucesso
            RateLimiter::clear($key);

            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        // Incrementar o contador (TTL 60 segundos)
        RateLimiter::hit($key, 60);

        throw ValidationException::withMessages([
            'email' => ['Credenciais inválidas.'],
        ]);
    }
}

// Alternativa: usar o middleware throttle
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 requests por 1 minuto

// Ou um rate limiter customizado no RouteServiceProvider
use Illuminate\Cache\RateLimiting\Limit;

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->input('email') . $request->ip())
        ->response(function () {
            return response()->json([
                'message' => 'Muitas tentativas de login.'
            ], 429);
        });
});

// Na rota
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:login');
```
</details>

### Exercício 2: Configure Email Verification

**Enunciado:** Implemente confirmação de email para usuários novos.

<details>
<summary>Solução</summary>

```php
// 1. User Model
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}

// 2. Migration (já vem no Laravel)
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable(); // Importante!
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

// 3. Routes
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/dashboard');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('message', 'Email enviado!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// 4. Proteção das rotas
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});

// 5. Customizar a notificação
class User extends Authenticatable implements MustVerifyEmail
{
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }
}

// app/Notifications/CustomVerifyEmail.php
use Illuminate\Auth\Notifications\VerifyEmail;

class CustomVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirme o email')
            ->line('Clique no botão abaixo para confirmar.')
            ->action('Confirmar email', $verificationUrl)
            ->line('Se você não criou a conta, ignore este email.');
    }
}

// 6. View (resources/views/auth/verify-email.blade.php)
<div>
    <h2>Confirme o email</h2>

    @if (session('message'))
        <div>{{ session('message') }}</div>
    @endif

    <p>Enviamos um email para você. Confira a caixa de entrada.</p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Reenviar o email</button>
    </form>
</div>
```
</details>

### Exercício 3: Implemente um 2FA simples

**Enunciado:** Adicione autenticação de dois fatores com código por email.

<details>
<summary>Solução</summary>

```php
// 1. Migration
Schema::table('users', function (Blueprint $table) {
    $table->string('two_factor_code')->nullable();
    $table->timestamp('two_factor_expires_at')->nullable();
});

// 2. LoginController
class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Gerar o código 2FA
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        // Enviar o código por email
        $user->notify(new TwoFactorCode($code));

        // Guardar o user_id na sessão
        session(['2fa_user_id' => $user->id]);

        return redirect()->route('two-factor.verify');
    }

    public function showVerifyForm()
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $userId = session('2fa_user_id');
        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        // Checagem de validade
        if (now()->isAfter($user->two_factor_expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['Código expirado. Faça login de novo.'],
            ]);
        }

        // Checagem do código
        if (!Hash::check($request->code, $user->two_factor_code)) {
            throw ValidationException::withMessages([
                'code' => ['Código inválido.'],
            ]);
        }

        // Limpar os dados do 2FA
        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        // Fazer login
        Auth::login($user);
        $request->session()->forget('2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}

// 3. Notification
class TwoFactorCode extends Notification
{
    public function __construct(private string $code)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de verificação')
            ->line("Seu código: {$this->code}")
            ->line('O código vale por 10 minutos.');
    }
}

// 4. Routes
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::get('/two-factor', [LoginController::class, 'showVerifyForm'])->name('two-factor.verify');
Route::post('/two-factor', [LoginController::class, 'verify']);

// 5. View (resources/views/auth/two-factor.blade.php)
<form method="POST" action="{{ route('two-factor.verify') }}">
    @csrf

    <div>
        <label>Digite o código do email</label>
        <input type="text" name="code" maxlength="6" required>
        @error('code')
            <span>{{ $message }}</span>
        @enderror
    </div>

    <button type="submit">Confirmar</button>
</form>
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
