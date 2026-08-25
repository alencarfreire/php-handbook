# 5.8 Validation

## Resumo

> **Validação** — checagem dos dados de entrada com regras prontas (required, email, unique, exists, etc.) ou customizadas.
>
> `$request->validate()` — validação simples no controller. **Form Request** — classe separada com `authorize()`, `rules()`, `messages()`.
>
> **Regras customizadas:** via `make:rule` ou closure. `withValidator()` para lógica extra. Arrays aninhados: `array`, `array.*`.

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
Validação é checar os dados de entrada. O Laravel traz regras prontas e deixa você criar as suas.

**O essencial:**
- `$request->validate()` — no controller
- Form Request — classe separada
- Regras customizadas

---

## Como funciona

**Validação básica no controller:**

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'body' => 'required|string',
        'category_id' => 'required|exists:categories,id',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
    ]);

    $post = Post::create($validated);

    return response()->json($post, 201);
}
```

**Regras mais usadas:**

```php
[
    // Campo obrigatório
    'email' => 'required',

    // String
    'name' => 'string|min:3|max:255',

    // Email
    'email' => 'email:rfc,dns',

    // Número
    'age' => 'integer|min:18|max:100',
    'price' => 'numeric|between:0,9999.99',

    // Boolean
    'is_active' => 'boolean',

    // Data
    'birth_date' => 'date|before:today',
    'start_date' => 'date|after:tomorrow',
    'end_date' => 'date|after:start_date',

    // Arquivo
    'avatar' => 'file|image|mimes:jpeg,png|max:2048',  // 2MB

    // Array
    'tags' => 'array|min:1|max:5',
    'tags.*' => 'string',

    // Existe no banco
    'user_id' => 'exists:users,id',

    // Valor único
    'email' => 'unique:users,email',
    'email' => 'unique:users,email,'.$user->id,  // Ignorar o atual

    // Um dos valores
    'status' => 'in:pending,approved,rejected',

    // Regex
    'phone' => 'regex:/^\+55\d{10,11}$/',

    // Confirmação (password_confirmation)
    'password' => 'confirmed|min:8',

    // Nullable
    'middle_name' => 'nullable|string|max:255',

    // Sometimes (só se o campo vier)
    'bio' => 'sometimes|string|max:1000',

    // Required if
    'reason' => 'required_if:status,rejected',

    // Required with
    'state' => 'required_with:city,zip',

    // Distinct (valores únicos no array)
    'emails' => 'array',
    'emails.*' => 'email|distinct',
]
```

**Form Request (tirar a validação do controller):**

```bash
php artisan make:request CreatePostRequest
```

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    // Autorização
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    // Regras de validação
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:posts,slug',
            'body' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'nullable|array|max:5',
            'tags.*' => 'string|max:50',
            'published_at' => 'nullable|date|after:now',
        ];
    }

    // Mensagens customizadas
    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório',
            'slug.unique' => 'Esse slug já existe',
            'tags.max' => 'No máximo 5 tags',
        ];
    }

    // Nomes customizados dos atributos
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'body' => 'conteúdo',
        ];
    }

    // Preparar os dados antes da validação
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->title),
        ]);
    }
}

// Uso no controller
public function store(CreatePostRequest $request)
{
    // A validação já passou
    $post = Post::create($request->validated());

    return response()->json($post, 201);
}
```

---

## Quando usar

**Controller `$request->validate()`:**
- Validação simples
- Request pontual

**Form Request:**
- Validação complexa
- Reuso
- Precisa de autorização
- Mensagens customizadas

---

## Exemplo prático

**Validação completa de pedido:**

```php
namespace App\Http\Requests;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',

            // Validação baseada em outro campo
            'shipping_address' => 'required_if:delivery_method,courier',

            // Validação condicional
            'payment_method' => 'required|in:card,cash,online',
            'card_number' => 'required_if:payment_method,card|digits:16',
            'card_cvv' => 'required_if:payment_method,card|digits:3',

            // Regra customizada
            'promo_code' => ['nullable', 'string', new ValidPromoCode()],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'O carrinho não pode ficar vazio',
            'items.*.product_id.exists' => 'Produto não encontrado',
            'card_number.required_if' => 'Informe o número do cartão',
        ];
    }

    // Validação extra
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Checar estoque
            foreach ($this->input('items', []) as $item) {
                $product = Product::find($item['product_id']);

                if ($product && $product->stock < $item['quantity']) {
                    $validator->errors()->add(
                        "items.{$item['product_id']}.quantity",
                        "Estoque insuficiente (disponível: {$product->stock})"
                    );
                }
            }
        });
    }
}
```

**Regra customizada:**

```bash
php artisan make:rule ValidPromoCode
```

```php
namespace App\Rules;

use App\Models\PromoCode;
use Illuminate\Contracts\Validation\Rule;

class ValidPromoCode implements Rule
{
    private ?string $message = null;

    public function passes($attribute, $value): bool
    {
        $promoCode = PromoCode::where('code', $value)->first();

        if (!$promoCode) {
            $this->message = 'Cupom não encontrado';
            return false;
        }

        if ($promoCode->expires_at < now()) {
            $this->message = 'Cupom expirado';
            return false;
        }

        if ($promoCode->uses_count >= $promoCode->max_uses) {
            $this->message = 'Cupom esgotado';
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message ?? 'Cupom inválido';
    }
}

// Uso
'promo_code' => ['nullable', 'string', new ValidPromoCode()],
```

**Regra com closure (sem classe):**

```php
use Illuminate\Validation\Rule;

$request->validate([
    'email' => [
        'required',
        'email',
        function ($attribute, $value, $fail) {
            if (!str_ends_with($value, '@empresa.com')) {
                $fail('Use o email corporativo');
            }
        },
    ],

    // Ou Rule::forEach para arrays
    'users.*.email' => [
        'required',
        'email',
        Rule::forEach(function ($value) {
            return [
                'unique:users,email',
            ];
        }),
    ],
]);
```

**Regras condicionais:**

```php
public function rules(): array
{
    $rules = [
        'title' => 'required|string|max:255',
        'body' => 'required|string',
    ];

    // Regras extras no update
    if ($this->isMethod('put') || $this->isMethod('patch')) {
        $rules['slug'] = [
            'required',
            'string',
            Rule::unique('posts')->ignore($this->route('post')),
        ];
    }

    // Regras condicionais
    if ($this->input('type') === 'premium') {
        $rules['premium_content'] = 'required|string';
    }

    return $rules;
}
```

**Validação de array aninhado:**

```php
$request->validate([
    'orders' => 'required|array',
    'orders.*.user_id' => 'required|exists:users,id',
    'orders.*.items' => 'required|array|min:1',
    'orders.*.items.*.product_id' => 'required|exists:products,id',
    'orders.*.items.*.quantity' => 'required|integer|min:1',
    'orders.*.items.*.price' => 'required|numeric|min:0',
]);
```

**Sometimes (adicionar regra sob condição):**

```php
use Illuminate\Validation\Validator;

$validator = Validator::make($request->all(), [
    'email' => 'required|email',
]);

$validator->sometimes('reason', 'required|max:500', function ($input) {
    return $input->status === 'rejected';
});

if ($validator->fails()) {
    return response()->json($validator->errors(), 422);
}
```

**Error bag customizado:**

```php
public function store(CreatePostRequest $request, CreateTagRequest $tagRequest)
{
    // Error bags diferentes para cada form
    $request->validateWithBag('post', $request->rules());
    $tagRequest->validateWithBag('tag', $tagRequest->rules());
}
```

**Validação manual:**

```php
use Illuminate\Support\Facades\Validator;

$validator = Validator::make($request->all(), [
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

// Adicionar erros customizados
$validator->after(function ($validator) {
    if ($this->somethingElseIsInvalid()) {
        $validator->errors()->add('field', 'Algo deu errado!');
    }
});

if ($validator->fails()) {
    return redirect()->back()
        ->withErrors($validator)
        ->withInput();
}

$validated = $validator->validated();
```

**Regra bail (parar no primeiro erro):**

```php
$request->validate([
    // Para a validação do email no primeiro erro
    'email' => 'bail|required|email|unique:users',

    // Sem bail, testa todas as regras mesmo se required falhar
    'password' => 'required|min:8|confirmed',
]);
```

---

## Na entrevista

**Resposta estruturada:**

**Formas de validar:**
- `$request->validate()` — validação rápida no controller
- **Form Request** — classe separada para o caso complexo

**Regras mais usadas:**
```php
'email' => 'required|email|unique:users,email',
'age' => 'integer|min:18|max:100',
'file' => 'file|image|mimes:jpeg,png|max:2048',
'tags' => 'array|min:1',
'tags.*' => 'string|max:50',
'user_id' => 'exists:users,id',
'status' => 'in:pending,approved,rejected',
'password' => 'confirmed|min:8',
```

**Form Request:**
```php
authorize()  // Checa permissão
rules()      // Regras de validação
messages()   // Mensagens customizadas
attributes() // Nomes dos campos
prepareForValidation()  // Prepara os dados
withValidator()  // Validação extra
```

**Regras customizadas:**
- `make:rule` — classe separada
- Closure — caso simples
- `Rule::forEach` — para arrays

**Avançado:**
- `bail` — para no primeiro erro
- `sometimes` — regras condicionais
- `required_if`, `required_with` — regras dependentes
- Arrays aninhados: `orders.*.items.*.quantity`

---

## Exercícios práticos

### Exercício 1: Form Request com validação condicional

Crie um `UpdateProfileRequest`. O campo `email` tem que ser único (exceto o usuário atual). O campo `company_name` só é obrigatório se `account_type === 'business'`.

<details>
<summary>Solução</summary>

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // O usuário só edita o próprio perfil
        return $this->user()->id === $this->route('user')->id;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',

            // Email único, exceto o usuário atual
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->user()->id),
            ],

            'account_type' => 'required|in:personal,business',

            // Obrigatório só para business
            'company_name' => 'required_if:account_type,business|string|max:255',
            'company_vat' => 'nullable|string|max:50',

            'phone' => 'nullable|regex:/^\+55\d{10,11}$/',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Esse email já está em uso',
            'company_name.required_if' => 'Informe o nome da empresa para conta business',
            'phone.regex' => 'O telefone deve estar no formato +5511999999999',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => 'nome da empresa',
            'company_vat' => 'CNPJ',
        ];
    }
}

// Controller
public function update(UpdateProfileRequest $request, User $user)
{
    $user->update($request->validated());

    return response()->json($user);
}
```
</details>

### Exercício 2: Regra customizada para cupom

Crie a regra customizada `ValidPromoCode` que checa: o cupom existe, não expirou, não esgotou e serve para o usuário atual (valor mínimo do pedido).

<details>
<summary>Solução</summary>

```php
// php artisan make:rule ValidPromoCode

namespace App\Rules;

use App\Models\PromoCode;
use Illuminate\Contracts\Validation\Rule;

class ValidPromoCode implements Rule
{
    private ?string $message = null;
    private float $orderTotal;

    public function __construct(float $orderTotal)
    {
        $this->orderTotal = $orderTotal;
    }

    public function passes($attribute, $value): bool
    {
        $promoCode = PromoCode::where('code', $value)->first();

        if (!$promoCode) {
            $this->message = 'Cupom não encontrado';
            return false;
        }

        if (!$promoCode->is_active) {
            $this->message = 'Cupom inativo';
            return false;
        }

        if ($promoCode->expires_at && $promoCode->expires_at < now()) {
            $this->message = 'Cupom expirado';
            return false;
        }

        if ($promoCode->max_uses && $promoCode->uses_count >= $promoCode->max_uses) {
            $this->message = 'Cupom esgotado';
            return false;
        }

        if ($promoCode->min_order_amount && $this->orderTotal < $promoCode->min_order_amount) {
            $this->message = "Valor mínimo do pedido: R$ {$promoCode->min_order_amount}";
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message ?? 'Cupom inválido';
    }
}

// Uso no Request
public function rules(): array
{
    return [
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'promo_code' => [
            'nullable',
            'string',
            new ValidPromoCode($this->getOrderTotal()),
        ],
    ];
}

private function getOrderTotal(): float
{
    $total = 0;
    foreach ($this->input('items', []) as $item) {
        $product = Product::find($item['product_id']);
        if ($product) {
            $total += $product->price * $item['quantity'];
        }
    }
    return $total;
}
```
</details>

### Exercício 3: Validação de arrays aninhados

Crie a validação para importação em massa de usuários. Formato: `[{name, email, roles: [{id, expires_at}]}]`. Cheque unicidade do email no banco e dentro do array.

<details>
<summary>Solução</summary>

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'users' => 'required|array|min:1|max:100',
            'users.*.name' => 'required|string|max:255',
            'users.*.email' => [
                'required',
                'email',
                'distinct',  // Único dentro do array
                Rule::unique('users', 'email'),  // Único no banco
            ],
            'users.*.password' => 'required|string|min:8',
            'users.*.roles' => 'required|array|min:1',
            'users.*.roles.*.id' => 'required|exists:roles,id',
            'users.*.roles.*.expires_at' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'users.*.email.distinct' => 'O email :input está duplicado na lista',
            'users.*.email.unique' => 'O email :input já existe no sistema',
            'users.*.roles.min' => 'O usuário precisa ter pelo menos um role',
            'users.*.roles.*.id.exists' => 'Role não encontrado',
        ];
    }

    // Validação extra
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $users = $this->input('users', []);

            foreach ($users as $index => $user) {
                // Checar se os roles não se repetem
                $roleIds = collect($user['roles'] ?? [])->pluck('id');

                if ($roleIds->count() !== $roleIds->unique()->count()) {
                    $validator->errors()->add(
                        "users.{$index}.roles",
                        'Os roles não podem se repetir'
                    );
                }

                // Checar se o email é do domínio corporativo
                if (isset($user['email']) && !str_ends_with($user['email'], '@empresa.com')) {
                    $validator->errors()->add(
                        "users.{$index}.email",
                        'Use o email corporativo @empresa.com'
                    );
                }
            }
        });
    }
}

// Controller
public function import(ImportUsersRequest $request)
{
    $imported = [];

    DB::transaction(function () use ($request, &$imported) {
        foreach ($request->validated()['users'] as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt($userData['password']),
            ]);

            // Vincular os roles
            foreach ($userData['roles'] as $role) {
                $user->roles()->attach($role['id'], [
                    'expires_at' => $role['expires_at'] ?? null,
                ]);
            }

            $imported[] = $user;
        }
    });

    return response()->json([
        'message' => 'Usuários importados com sucesso',
        'count' => count($imported),
    ], 201);
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
