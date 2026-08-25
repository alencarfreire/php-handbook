# 3.6 Reflection API

## Resumo

> **Reflection API** — analisa e altera a estrutura de classes, métodos e propriedades em tempo de execução.
>
> **O essencial:** ReflectionClass, ReflectionMethod, ReflectionProperty, setAccessible(true).
>
> **Laravel:** o Service Container (container de serviços) usa Reflection para DI, o Eloquent para models, Attributes (PHP 8.0+).

---

## Conteúdo

- [O que é Reflection](#o-que-é-reflection)
- [ReflectionClass](#reflectionclass)
- [ReflectionProperty](#reflectionproperty)
- [ReflectionMethod](#reflectionmethod)
- [ReflectionParameter](#reflectionparameter)
- [Attributes (PHP 8.0+)](#attributes-php-80)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é Reflection

**O que é:**
API para analisar e alterar a estrutura de classes, métodos e propriedades em tempo de execução.

**Como funciona:**
```php
class User
{
    private string $name;
    protected int $age;
    public bool $isActive;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
        $this->isActive = true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function getAge(): int
    {
        return $this->age;
    }
}

// Reflection da classe
$reflection = new ReflectionClass(User::class);

// Informação da classe
echo $reflection->getName();  // "User"
echo $reflection->getShortName();  // "User" (sem namespace)
var_dump($reflection->isAbstract());  // false
var_dump($reflection->isFinal());  // false

// Propriedades
$properties = $reflection->getProperties();
foreach ($properties as $property) {
    echo $property->getName() . " (" . $property->getType() . ")\n";
}
// name (string)
// age (int)
// isActive (bool)

// Métodos
$methods = $reflection->getMethods();
foreach ($methods as $method) {
    echo $method->getName() . "\n";
}
// __construct
// getName
// getAge
```

**Quando usar:**
Metaprogramação, framework, container de DI, ORM.

**Exemplo prático:**
```php
// Laravel Service Container (simplificado)
class Container
{
    public function make(string $class): object
    {
        $reflection = new ReflectionClass($class);

        // Pegar o construtor
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();  // Sem construtor
        }

        // Pegar os parâmetros do construtor
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                // Resolver a dependência de forma recursiva
                $dependencies[] = $this->make($type->getName());
            }
        }

        // Criar o objeto com as dependências
        return $reflection->newInstanceArgs($dependencies);
    }
}

// Uso
$container = new Container();
$service = $container->make(UserService::class);
// Resolve todas as dependências sozinho
```

**Na entrevista:**
> "Reflection API analisa a estrutura das classes em tempo de execução. Eu pego informação de propriedade, método, parâmetro. O Service Container do Laravel usa Reflection para resolver as dependências sozinho."

---

## ReflectionClass

**O que é:**
Classe que analisa outra classe.

**Como funciona:**
```php
$reflection = new ReflectionClass(User::class);

// Informação da classe
echo $reflection->getName();  // "App\Models\User"
echo $reflection->getShortName();  // "User"
echo $reflection->getNamespaceName();  // "App\Models"
echo $reflection->getFileName();  // "/path/to/User.php"

// Checagens
var_dump($reflection->isAbstract());  // false
var_dump($reflection->isFinal());  // false
var_dump($reflection->isInterface());  // false
var_dump($reflection->isTrait());  // false
var_dump($reflection->isInstantiable());  // true (dá para criar objeto)

// Classe pai
$parent = $reflection->getParentClass();  // ReflectionClass ou false

// Interfaces
$interfaces = $reflection->getInterfaces();  // ReflectionClass[]

// Traits
$traits = $reflection->getTraits();  // ReflectionClass[]

// Constantes da classe
$constants = $reflection->getConstants();  // ['STATUS_ACTIVE' => 'active', ...]

// Criar objeto
$user = $reflection->newInstance('João', 25);
// Ou com array de argumentos
$user = $reflection->newInstanceArgs(['João', 25]);

// Sem chamar o construtor
$user = $reflection->newInstanceWithoutConstructor();
```

**Quando usar:**
Analisar a estrutura da classe, criar objeto, DI.

**Exemplo prático:**
```php
// Análise de um model Eloquent
$reflection = new ReflectionClass(User::class);

// Checar se é Model
$isModel = $reflection->isSubclassOf(Model::class);

// Pegar a tabela (se existir protected $table)
$table = $reflection->hasProperty('table')
    ? $reflection->getProperty('table')->getValue(new User())
    : Str::snake(Str::pluralStudly($reflection->getShortName()));

// Factory de objetos
class ObjectFactory
{
    public function create(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \Exception("Não é possível instanciar {$class}");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $arguments[] = $data[$name] ?? $parameter->getDefaultValue();
        }

        return $reflection->newInstanceArgs($arguments);
    }
}

$factory = new ObjectFactory();
$user = $factory->create(User::class, ['name' => 'João', 'age' => 25]);
```

**Na entrevista:**
> "ReflectionClass analisa a classe. Métodos: getName(), getProperties(), getMethods(), getInterfaces(), getTraits(). Para criar objeto: newInstance(), newInstanceArgs(). O Laravel usa isso para analisar model e para DI."

---

## ReflectionProperty

**O que é:**
Classe que analisa uma propriedade.

**Como funciona:**
```php
class User
{
    private string $name;
    protected int $age;
    public bool $isActive = true;
}

$reflection = new ReflectionClass(User::class);

// Pegar a propriedade
$property = $reflection->getProperty('name');

// Informação da propriedade
echo $property->getName();  // "name"
echo $property->getType();  // "string"
var_dump($property->isPrivate());  // true
var_dump($property->isProtected());  // false
var_dump($property->isPublic());  // false
var_dump($property->isStatic());  // false

// Acesso a private/protected
$user = new User('João', 25);

// Sem Reflection
// echo $user->name;  // ❌ Error (private)

// Com Reflection
$property = new ReflectionProperty(User::class, 'name');
$property->setAccessible(true);  // Liberar o acesso
echo $property->getValue($user);  // "João"

$property->setValue($user, 'Pedro');
echo $property->getValue($user);  // "Pedro"

// Pegar todas as propriedades
$properties = $reflection->getProperties();

foreach ($properties as $property) {
    $property->setAccessible(true);

    echo "{$property->getName()}: ";
    echo $property->getValue($user) . "\n";
}
```

**Quando usar:**
Acessar propriedade private (teste, serialização, ORM).

**Exemplo prático:**
```php
// Eloquent toArray() (simplificado)
class Model
{
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $result = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            $result[$property->getName()] = $property->getValue($this);
        }

        return $result;
    }
}

// Teste unitário de propriedade private
class UserTest extends TestCase
{
    public function test_name_is_set(): void
    {
        $user = new User('João', 25);

        $property = new ReflectionProperty(User::class, 'name');
        $property->setAccessible(true);

        $this->assertEquals('João', $property->getValue($user));
    }
}

// Serializer
class Serializer
{
    public function serialize(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($object);
        }

        return $data;
    }
}
```

**Na entrevista:**
> "ReflectionProperty analisa a propriedade. setAccessible(true) libera private/protected. Métodos: getValue(), setValue(), getName(), getType(). Uso em teste, ORM, serialização."

---

## ReflectionMethod

**O que é:**
Classe que analisa um método.

**Como funciona:**
```php
class User
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function setName(string $name): void
    {
        $this->name = $name;
    }

    private function validateName(string $name): bool
    {
        return strlen($name) > 0;
    }
}

$reflection = new ReflectionClass(User::class);

// Pegar o método
$method = $reflection->getMethod('getName');

// Informação do método
echo $method->getName();  // "getName"
echo $method->getReturnType();  // "string"
var_dump($method->isPublic());  // true
var_dump($method->isProtected());  // false
var_dump($method->isPrivate());  // false
var_dump($method->isStatic());  // false
var_dump($method->isAbstract());  // false
var_dump($method->isFinal());  // false

// Chamar método protected/private
$user = new User('João');

$method = new ReflectionMethod(User::class, 'setName');
$method->setAccessible(true);
$method->invoke($user, 'Pedro');  // Chamar o método

echo $user->getName();  // "Pedro"

// Parâmetros do método
$parameters = $method->getParameters();
foreach ($parameters as $parameter) {
    echo $parameter->getName() . ": " . $parameter->getType() . "\n";
}
// name: string
```

**Quando usar:**
Chamar método private (teste) e analisar API.

**Exemplo prático:**
```php
// Teste unitário de método private
class UserTest extends TestCase
{
    public function test_name_validation(): void
    {
        $user = new User('João');

        $method = new ReflectionMethod(User::class, 'validateName');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($user, 'Valid'));
        $this->assertFalse($method->invoke($user, ''));
    }
}

// Analisador de API
class ApiAnalyzer
{
    public function analyzeController(string $controller): array
    {
        $reflection = new ReflectionClass($controller);
        $endpoints = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $controller) {
                continue;  // Só métodos desta classe
            }

            $endpoints[] = [
                'method' => $method->getName(),
                'parameters' => $this->getParameters($method),
                'return_type' => (string) $method->getReturnType(),
            ];
        }

        return $endpoints;
    }

    private function getParameters(ReflectionMethod $method): array
    {
        $params = [];

        foreach ($method->getParameters() as $parameter) {
            $params[] = [
                'name' => $parameter->getName(),
                'type' => (string) $parameter->getType(),
                'optional' => $parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null,
            ];
        }

        return $params;
    }
}

$analyzer = new ApiAnalyzer();
$endpoints = $analyzer->analyzeController(UserController::class);
```

**Na entrevista:**
> "ReflectionMethod analisa o método. setAccessible(true) para chamar private/protected. invoke() chama o método. getParameters() devolve os parâmetros. Uso em teste de método private."

---

## ReflectionParameter

**O que é:**
Classe que analisa um parâmetro de método ou função.

**Como funciona:**
```php
class UserService
{
    public function create(
        string $name,
        int $age = 18,
        ?string $email = null,
        bool $isActive = true
    ): User {
        // ...
    }
}

$reflection = new ReflectionMethod(UserService::class, 'create');
$parameters = $reflection->getParameters();

foreach ($parameters as $parameter) {
    echo "Parâmetro: {$parameter->getName()}\n";
    echo "  Tipo: {$parameter->getType()}\n";
    echo "  Posição: {$parameter->getPosition()}\n";
    echo "  Opcional: " . ($parameter->isOptional() ? 'sim' : 'não') . "\n";

    if ($parameter->isDefaultValueAvailable()) {
        echo "  Padrão: " . var_export($parameter->getDefaultValue(), true) . "\n";
    }

    echo "  Nullable: " . ($parameter->allowsNull() ? 'sim' : 'não') . "\n";
    echo "\n";
}

// Saída:
// Parâmetro: name
//   Tipo: string
//   Posição: 0
//   Opcional: não
//   Nullable: não
//
// Parâmetro: age
//   Tipo: int
//   Posição: 1
//   Opcional: sim
//   Padrão: 18
//   Nullable: não
//
// Parâmetro: email
//   Tipo: ?string
//   Posição: 2
//   Opcional: sim
//   Padrão: NULL
//   Nullable: sim
```

**Quando usar:**
Container de DI, análise de API, documentação automática.

**Exemplo prático:**
```php
// Laravel Service Container (simplificado)
class Container
{
    public function make(string $class): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                // Tipo escalar ou sem type hint
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \Exception("Não é possível resolver {$parameter->getName()}");
                }
            } else {
                // Classe — resolver de forma recursiva
                $dependencies[] = $this->make($type->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}

// Validador de parâmetros
class ParameterValidator
{
    public function validate(ReflectionParameter $parameter, mixed $value): void
    {
        $type = $parameter->getType();

        if ($type === null) {
            return;  // Sem type hint
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            // Checagem de tipo escalar
            if (gettype($value) !== $typeName) {
                throw new \TypeError("Esperado {$typeName}, recebido " . gettype($value));
            }
        } else {
            // Checagem de classe
            if (!($value instanceof $typeName)) {
                throw new \TypeError("Esperado {$typeName}, recebido " . get_class($value));
            }
        }

        if (!$parameter->allowsNull() && $value === null) {
            throw new \TypeError("{$parameter->getName()} não pode ser null");
        }
    }
}
```

**Na entrevista:**
> "ReflectionParameter analisa o parâmetro da função ou do método. Métodos: getName(), getType(), isOptional(), getDefaultValue(), allowsNull(). O Service Container do Laravel usa isso para resolver dependência sozinho."

---

## Attributes (PHP 8.0+)

**O que é:**
Metadados em classe, método e propriedade (o sucessor das anotações).

**Como funciona:**
```php
// Definição do attribute
#[Attribute]
class Route
{
    public function __construct(
        public string $method,
        public string $path,
    ) {}
}

// Uso do attribute
class UserController
{
    #[Route('GET', '/users')]
    public function index() {}

    #[Route('POST', '/users')]
    public function store() {}

    #[Route('GET', '/users/{id}')]
    public function show(int $id) {}
}

// Leitura dos attributes
$reflection = new ReflectionClass(UserController::class);

foreach ($reflection->getMethods() as $method) {
    $attributes = $method->getAttributes(Route::class);

    foreach ($attributes as $attribute) {
        $route = $attribute->newInstance();  // Objeto Route

        echo "{$route->method} {$route->path} → {$method->getName()}\n";
    }
}

// Saída:
// GET /users → index
// POST /users → store
// GET /users/{id} → show
```

**Quando usar:**
Metadado (rota, validação, cache). Substitui anotação de PHPDoc.

**Exemplo prático:**
```php
// Validação via attributes
#[Attribute(Attribute::TARGET_PROPERTY)]
class Required {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(public int $value) {}
}

class CreateUserRequest
{
    #[Required, Email]
    public string $email;

    #[Required, Min(8)]
    public string $password;

    #[Required]
    public string $name;
}

// Validador
class AttributeValidator
{
    public function validate(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $errors = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);

            foreach ($property->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Required && empty($value)) {
                    $errors[$property->getName()][] = 'Campo obrigatório';
                }

                if ($instance instanceof Email && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$property->getName()][] = 'E-mail inválido';
                }

                if ($instance instanceof Min && strlen($value) < $instance->value) {
                    $errors[$property->getName()][] = "Tamanho mínimo é {$instance->value}";
                }
            }
        }

        return $errors;
    }
}

$request = new CreateUserRequest();
$request->email = 'invalid';
$request->password = '123';
$request->name = 'João';

$validator = new AttributeValidator();
$errors = $validator->validate($request);
// ['email' => ['E-mail inválido'], 'password' => ['Tamanho mínimo é 8']]

// Registro de rota (estilo Laravel)
#[Attribute(Attribute::TARGET_METHOD)]
class Get
{
    public function __construct(public string $path) {}
}

class ApiController
{
    #[Get('/api/users')]
    public function users() {}
}

// Router
class Router
{
    public function registerController(string $controller): void
    {
        $reflection = new ReflectionClass($controller);

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Get::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $this->get($route->path, [$controller, $method->getName()]);
            }
        }
    }
}
```

**Na entrevista:**
> "Attributes (PHP 8.0+) são metadados em classe, método, propriedade. #[AttributeName]. Leio com getAttributes(). Uso em rota, validação, cache. Substitui anotação de PHPDoc."

---

## Recapitulando

**O essencial:**
- **ReflectionClass** — analisa a classe
- **ReflectionProperty** — analisa a propriedade
- **ReflectionMethod** — analisa o método
- **ReflectionParameter** — analisa o parâmetro
- **setAccessible(true)** — acesso a private/protected
- **Attributes (PHP 8.0+)** — metadados via #[Attr]

**Métodos principais:**
- `getName()` — nome do elemento
- `getType()` — tipo do elemento
- `getValue()` / `setValue()` — ler/escrever propriedade
- `invoke()` — chamar o método
- `newInstance()` / `newInstanceArgs()` — criar objeto
- `getAttributes()` — ler attributes (PHP 8.0+)

**Importante na entrevista:**
- O Service Container do Laravel usa Reflection para DI
- setAccessible(true) para testar método private
- Eloquent usa Reflection nos models
- Attributes (PHP 8.0+) para metadado
- Reflection é mais lento que código normal (faça cache do resultado)
- Uso em framework, DI, ORM, teste

---

## Exercícios práticos

### Exercício 1: Object Mapper com Reflection

**Enunciado:** Implemente uma classe que converte DTO em array e de volta, usando Reflection.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Utils;

use ReflectionClass;
use ReflectionProperty;

class ObjectMapper
{
    public function toArray(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $name = $property->getName();
            $value = $property->getValue($object);

            // Converte objetos aninhados de forma recursiva
            if (is_object($value)) {
                $value = $this->toArray($value);
            } elseif (is_array($value)) {
                $value = array_map(function ($item) {
                    return is_object($item) ? $this->toArray($item) : $item;
                }, $value);
            }

            $data[$name] = $value;
        }

        return $data;
    }

    public function fromArray(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);

        // Criar objeto sem construtor
        $object = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $name = $property->getName();

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $value = $data[$name];

            // Converter o tipo se precisar
            $type = $property->getType();

            if ($type && !$type->isBuiltin() && is_array($value)) {
                $value = $this->fromArray($type->getName(), $value);
            }

            $property->setValue($object, $value);
        }

        return $object;
    }
}

// DTO
class Address
{
    public function __construct(
        public string $city,
        public string $street,
    ) {}
}

class UserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public Address $address,
    ) {}
}

// Uso
$mapper = new ObjectMapper();

$user = new UserDTO(
    name: 'João',
    email: 'joao@email.com',
    address: new Address(
        city: 'São Paulo',
        street: 'Av. Paulista, 1000'
    )
);

// DTO → Array
$array = $mapper->toArray($user);
/*
[
    'name' => 'João',
    'email' => 'joao@email.com',
    'address' => [
        'city' => 'São Paulo',
        'street' => 'Av. Paulista, 1000',
    ]
]
*/

// Array → DTO
$restored = $mapper->fromArray(UserDTO::class, $array);

echo $restored->name;  // "João"
echo $restored->address->city;  // "São Paulo"
```
</details>

### Exercício 2: Validator simples com Attributes

**Enunciado:** Crie um validador que usa PHP 8 Attributes para as regras de validação.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Validation;

use Attribute;
use ReflectionClass;

// Attributes
#[Attribute(Attribute::TARGET_PROPERTY)]
class Required {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(public int $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max
{
    public function __construct(public int $value) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    public function __construct(public string $pattern) {}
}

// Validator
class AttributeValidator
{
    public function validate(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $errors = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);
            $propertyName = $property->getName();

            foreach ($property->getAttributes() as $attribute) {
                $rule = $attribute->newInstance();

                $error = $this->validateRule($rule, $value, $propertyName);

                if ($error !== null) {
                    $errors[$propertyName][] = $error;
                }
            }
        }

        return $errors;
    }

    private function validateRule(object $rule, mixed $value, string $propertyName): ?string
    {
        return match (true) {
            $rule instanceof Required => $this->validateRequired($value, $propertyName),
            $rule instanceof Email => $this->validateEmail($value, $propertyName),
            $rule instanceof Min => $this->validateMin($value, $rule->value, $propertyName),
            $rule instanceof Max => $this->validateMax($value, $rule->value, $propertyName),
            $rule instanceof Regex => $this->validateRegex($value, $rule->pattern, $propertyName),
            default => null,
        };
    }

    private function validateRequired(mixed $value, string $property): ?string
    {
        return empty($value) ? "{$property} é obrigatório" : null;
    }

    private function validateEmail(mixed $value, string $property): ?string
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$property} precisa ser um e-mail válido";
    }

    private function validateMin(mixed $value, int $min, string $property): ?string
    {
        $length = is_string($value) ? mb_strlen($value) : $value;
        return $length >= $min ? null : "{$property} precisa ter no mínimo {$min}";
    }

    private function validateMax(mixed $value, int $max, string $property): ?string
    {
        $length = is_string($value) ? mb_strlen($value) : $value;
        return $length <= $max ? null : "{$property} precisa ter no máximo {$max}";
    }

    private function validateRegex(mixed $value, string $pattern, string $property): ?string
    {
        return preg_match($pattern, $value) ? null : "formato de {$property} é inválido";
    }
}

// Uso
class RegisterRequest
{
    #[Required, Email]
    public string $email = '';

    #[Required, Min(8), Max(255)]
    public string $password = '';

    #[Required, Min(2), Max(100)]
    public string $name = '';

    #[Regex('/^\+55\d{11}$/')]
    public string $phone = '';
}

// Validação
$request = new RegisterRequest();
$request->email = 'invalid-email';
$request->password = '123';
$request->name = 'J';
$request->phone = '123';

$validator = new AttributeValidator();
$errors = $validator->validate($request);

/*
[
    'email' => ['email precisa ser um e-mail válido'],
    'password' => ['password precisa ter no mínimo 8'],
    'name' => ['name precisa ter no mínimo 2'],
    'phone' => ['formato de phone é inválido'],
]
*/

// Laravel Controller
public function register(Request $request)
{
    $dto = new RegisterRequest();
    $dto->email = $request->input('email');
    $dto->password = $request->input('password');
    $dto->name = $request->input('name');
    $dto->phone = $request->input('phone');

    $validator = new AttributeValidator();
    $errors = $validator->validate($dto);

    if (!empty($errors)) {
        return response()->json(['errors' => $errors], 422);
    }

    $user = User::create([
        'email' => $dto->email,
        'password' => Hash::make($dto->password),
        'name' => $dto->name,
    ]);

    return response()->json($user, 201);
}
```
</details>

### Exercício 3: Auto-Router com Reflection e Attributes

**Enunciado:** Implemente o registro automático de rotas analisando os métodos do Controller com Attributes.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Routing;

use Attribute;
use ReflectionClass;
use ReflectionMethod;

// Route Attributes
#[Attribute(Attribute::TARGET_METHOD)]
class Get
{
    public function __construct(public string $path) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Post
{
    public function __construct(public string $path) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Put
{
    public function __construct(public string $path) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Delete
{
    public function __construct(public string $path) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Middleware
{
    public function __construct(public array $middleware) {}
}

// Auto Router
class AutoRouter
{
    public function registerController(string $controller): void
    {
        $reflection = new ReflectionClass($controller);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Pula métodos da classe pai
            if ($method->class !== $controller) {
                continue;
            }

            $this->registerMethod($controller, $method);
        }
    }

    private function registerMethod(string $controller, ReflectionMethod $method): void
    {
        $middleware = $this->getMiddleware($method);

        foreach ($method->getAttributes() as $attribute) {
            $route = $attribute->newInstance();

            $httpMethod = match (true) {
                $route instanceof Get => 'GET',
                $route instanceof Post => 'POST',
                $route instanceof Put => 'PUT',
                $route instanceof Delete => 'DELETE',
                default => null,
            };

            if ($httpMethod === null) {
                continue;
            }

            $this->registerRoute(
                $httpMethod,
                $route->path,
                [$controller, $method->getName()],
                $middleware
            );
        }
    }

    private function getMiddleware(ReflectionMethod $method): array
    {
        $middlewares = [];

        foreach ($method->getAttributes(Middleware::class) as $attribute) {
            $middleware = $attribute->newInstance();
            $middlewares = array_merge($middlewares, $middleware->middleware);
        }

        return $middlewares;
    }

    private function registerRoute(string $method, string $path, array $action, array $middleware): void
    {
        $route = \Illuminate\Support\Facades\Route::{strtolower($method)}($path, $action);

        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        echo "Registrado: {$method} {$path} -> {$action[0]}@{$action[1]}\n";
    }
}

// Controller com Attributes
namespace App\Http\Controllers\Api;

use App\Routing\{Get, Post, Put, Delete, Middleware};

class UserController
{
    #[Get('/api/users')]
    #[Middleware(['auth:api'])]
    public function index()
    {
        return User::all();
    }

    #[Get('/api/users/{id}')]
    #[Middleware(['auth:api'])]
    public function show(int $id)
    {
        return User::findOrFail($id);
    }

    #[Post('/api/users')]
    #[Middleware(['auth:api', 'admin'])]
    public function store(Request $request)
    {
        return User::create($request->all());
    }

    #[Put('/api/users/{id}')]
    #[Middleware(['auth:api'])]
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->all());
        return $user;
    }

    #[Delete('/api/users/{id}')]
    #[Middleware(['auth:api', 'admin'])]
    public function destroy(int $id)
    {
        User::findOrFail($id)->delete();
        return response()->noContent();
    }
}

// Service Provider
namespace App\Providers;

use App\Routing\AutoRouter;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = new AutoRouter();

        // Registra todos os controllers automaticamente
        $router->registerController(\App\Http\Controllers\Api\UserController::class);
        $router->registerController(\App\Http\Controllers\Api\PostController::class);

        // Ou varre a pasta
        $this->registerControllersFromDirectory(app_path('Http/Controllers/Api'));
    }

    private function registerControllersFromDirectory(string $directory): void
    {
        $router = new AutoRouter();

        foreach (glob($directory . '/*Controller.php') as $file) {
            $class = $this->getClassFromFile($file);

            if (class_exists($class)) {
                $router->registerController($class);
            }
        }
    }

    private function getClassFromFile(string $file): string
    {
        $namespace = 'App\\Http\\Controllers\\Api';
        $class = basename($file, '.php');
        return "{$namespace}\\{$class}";
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
