# 9.1 Normalização de banco

> **TL;DR**
> Normalização corta redundância: você parte os dados em tabelas relacionadas. Formas principais: 1NF (valores atômicos), 2NF (depende da chave inteira), 3NF (sem dependência transitiva). Na prática, 3NF basta. Prós: integridade, UPDATE simples. Contras: mais JOIN. Use para OLTP, desnormalize para OLAP.

## Conteúdo

- [O que é](#o-que-é)
- [1NF (First Normal Form)](#1nf-first-normal-form)
- [2NF (Second Normal Form)](#2nf-second-normal-form)
- [3NF (Third Normal Form)](#3nf-third-normal-form)
- [BCNF (Boyce-Codd Normal Form)](#bcnf-boyce-codd-normal-form)
- [Exemplos de normalização](#exemplos-de-normalização)
- [Anomalias sem normalização](#anomalias-sem-normalização)
- [Vantagens da normalização](#vantagens-da-normalização)
- [Desvantagens da normalização](#desvantagens-da-normalização)
- [Quando normalizar](#quando-normalizar)
- [Quando NÃO normalizar (desnormalização)](#quando-não-normalizar-desnormalização)
- [Laravel Migrations para schema normalizado](#laravel-migrations-para-schema-normalizado)
- [Boas práticas](#boas-práticas)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Normalização:**
Organizar os dados no banco para reduzir redundância e melhorar a integridade.

**Objetivos:**
- Eliminar duplicata
- Garantir consistência
- Facilitar mudança de schema
- Reduzir anomalias (insert/update/delete)

**Formas normais:**
1NF → 2NF → 3NF → BCNF → 4NF → 5NF

**Na prática, o que mais aparece é 3NF.**

---

## 1NF (First Normal Form)

**Regras:**
- Cada campo tem valor atômico (não array, não lista)
- Sem grupos repetidos
- Tem primary key

**❌ Não é 1NF:**

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255),
    products VARCHAR(255)  -- 'Notebook, Mouse, Teclado'
);
```

**Problema:**
- Não dá para buscar pedidos de um produto específico
- Incluir/remover produto fica difícil

**✅ 1NF:**

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255)
);

CREATE TABLE order_items (
    id INT PRIMARY KEY,
    order_id INT REFERENCES orders(id),
    product_name VARCHAR(255)
);
```

---

## 2NF (Second Normal Form)

**Regras:**
- Cumpre 1NF
- Sem dependência parcial (campo que não é chave depende da primary key INTEIRA)

**❌ Não é 2NF:**

```sql
CREATE TABLE order_items (
    order_id INT,
    product_id INT,
    customer_name VARCHAR(255),  -- depende só de order_id!
    product_name VARCHAR(255),    -- depende só de product_id!
    quantity INT,
    PRIMARY KEY (order_id, product_id)
);
```

**Problema:**
- customer_name se repete em cada item
- Mudar o cliente exige UPDATE em todas as linhas

**✅ 2NF:**

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255)
);

CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);

CREATE TABLE order_items (
    order_id INT REFERENCES orders(id),
    product_id INT REFERENCES products(id),
    quantity INT,
    PRIMARY KEY (order_id, product_id)
);
```

---

## 3NF (Third Normal Form)

**Regras:**
- Cumpre 2NF
- Sem dependência transitiva (campo que não é chave não depende de outro campo que também não é chave)

**❌ Não é 3NF:**

```sql
CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    department_id INT,
    department_name VARCHAR(255)  -- depende de department_id!
);
```

**Problema:**
- department_name se repete
- Mudar o departamento exige UPDATE em todos os funcionários

**✅ 3NF:**

```sql
CREATE TABLE departments (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);

CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    department_id INT REFERENCES departments(id)
);
```

**Laravel Eloquent:**

```php
class Employee extends Model
{
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

// Uso
$employee = Employee::with('department')->find(1);
echo $employee->department->name;
```

---

## BCNF (Boyce-Codd Normal Form)

**Regras:**
- Cumpre 3NF
- Todo determinant (atributo que determina os outros) tem que ser candidate key

**Raramente se quebra. Na prática, 3NF costuma bastar.**

---

## Exemplos de normalização

### E-commerce: antes da normalização

```sql
-- ❌ Desnormalizado (tudo numa tabela só)
CREATE TABLE orders_denormalized (
    order_id INT,
    order_date DATE,
    customer_id INT,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_address TEXT,
    product_id INT,
    product_name VARCHAR(255),
    product_price DECIMAL(10, 2),
    category_name VARCHAR(255),
    quantity INT,
    total DECIMAL(10, 2)
);
```

**Problemas:**
- Dados do cliente se repetem em cada item do pedido
- Dados do produto se repetem em cada pedido
- Mudar o email do cliente exige UPDATE em milhares de linhas
- Insert anomaly: não dá para cadastrar produto sem pedido
- Delete anomaly: apagar o último pedido apaga os dados do cliente

---

### E-commerce: depois da normalização (3NF)

```sql
-- ✅ Normalizado (3NF)
CREATE TABLE customers (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    address TEXT
);

CREATE TABLE categories (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);

CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    price DECIMAL(10, 2),
    category_id INT REFERENCES categories(id)
);

CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_id INT REFERENCES customers(id),
    created_at TIMESTAMP,
    total DECIMAL(10, 2)
);

CREATE TABLE order_items (
    id INT PRIMARY KEY,
    order_id INT REFERENCES orders(id),
    product_id INT REFERENCES products(id),
    quantity INT,
    price DECIMAL(10, 2)  -- preço no momento do pedido
);
```

**Models no Laravel:**

```php
class Customer extends Model
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

class Order extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}

class OrderItem extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

class Product extends Model
{
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

---

## Anomalias sem normalização

### 1. Insert Anomaly

```sql
-- ❌ Não dá para cadastrar produto sem pedido
INSERT INTO orders_denormalized (product_name, product_price)
VALUES ('Novo Produto', 99.99);
-- ERROR: order_id cannot be NULL
```

**Solução: tabela products à parte**

```sql
-- ✅ Dá para cadastrar produto sem pedido
INSERT INTO products (name, price) VALUES ('Novo Produto', 99.99);
```

---

### 2. Update Anomaly

```sql
-- ❌ Mudar o email do cliente = UPDATE em todos os pedidos dele
UPDATE orders_denormalized
SET customer_email = 'novo@email.com'
WHERE customer_id = 123;
-- Podem ser milhares de linhas!
```

**Solução: tabela customers à parte**

```sql
-- ✅ UPDATE numa linha só
UPDATE customers
SET email = 'novo@email.com'
WHERE id = 123;
```

---

### 3. Delete Anomaly

```sql
-- ❌ Apagar o último pedido do cliente apaga todos os dados dele
DELETE FROM orders_denormalized
WHERE order_id = 999;
-- Perdeu customer_name, customer_email, customer_address!
```

**Solução: tabela customers à parte**

```sql
-- ✅ O cliente continua depois de apagar o pedido
DELETE FROM orders WHERE id = 999;
-- Customer continua na tabela customers
```

---

## Vantagens da normalização

```
✅ Sem duplicata (menos espaço)
✅ Consistência (uma fonte da verdade)
✅ UPDATE mais simples (muda num lugar só)
✅ Sem anomalias (insert/update/delete)
✅ Flexibilidade (mais fácil adicionar campo/tabela)
✅ Integridade referencial (foreign keys)
```

---

## Desvantagens da normalização

```
❌ Mais JOIN (SELECT mais lento)
❌ Queries mais complexas
❌ Mais tabelas (schema mais difícil de ler)
```

**Solução: desnormalizar queries críticas de performance** (veja o próximo tópico).

---

## Quando normalizar

```
✓ OLTP (sistemas transacionais): INSERT/UPDATE o tempo todo
✓ E-commerce, CRM, ERP
✓ Dados mudam o tempo todo
✓ Precisa de consistência forte
```

---

## Quando NÃO normalizar (desnormalização)

```
✓ OLAP (analítica): SELECT o tempo todo, INSERT raro
✓ Relatórios, dashboards
✓ Workload read-heavy
✓ Performance é crítica
✓ Data Warehouses
```

---

## Laravel Migrations para schema normalizado

```php
// Migration: customers
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->text('address')->nullable();
    $table->timestamps();
});

// Migration: orders
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->decimal('total', 10, 2);
    $table->timestamps();
});

// Migration: products
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->foreignId('category_id')->constrained();
    $table->timestamps();
});

// Migration: order_items
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->decimal('price', 10, 2);  // preço no momento do pedido
    $table->timestamps();
});
```

---

## Boas práticas

```
✓ Vá de 3NF em sistema transacional
✓ BCNF, 4NF, 5NF quase não aparecem na prática
✓ Use foreign keys para integridade referencial
✓ Índice em foreign key para JOIN rápido
✓ Desnormalizar vale quando performance pede
✓ Use relationships do Laravel no lugar de JOIN na mão
✓ Migrations para versionar o schema
```

---

## Exercícios práticos

### Exercício 1: Levar a tabela para 1NF

**Enunciado:** Dada a tabela desnormalizada:

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255),
    phone_numbers VARCHAR(255),  -- '11999991111, 11988882222'
    products VARCHAR(500)         -- 'Notebook, Mouse, Teclado'
);
```

Leve ela para 1NF.

<details>
<summary>Solução</summary>

```sql
-- Tabela de pedidos
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255)
);

-- Tabela de telefones
CREATE TABLE customer_phones (
    id INT PRIMARY KEY,
    order_id INT REFERENCES orders(id),
    phone_number VARCHAR(20)
);

-- Tabela de itens do pedido
CREATE TABLE order_items (
    id INT PRIMARY KEY,
    order_id INT REFERENCES orders(id),
    product_name VARCHAR(255)
);

-- Laravel Eloquent
class Order extends Model
{
    public function phones()
    {
        return $this->hasMany(CustomerPhone::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

**Pontos-chave:** 1NF exige valores atômicos. Listas CSV viram linhas em tabelas relacionadas.
</details>

---

### Exercício 2: Levar a tabela para 3NF

**Enunciado:** Dada a tabela:

```sql
CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    department_id INT,
    department_name VARCHAR(255),
    department_location VARCHAR(255)
);
```

Leve ela para 3NF.

<details>
<summary>Solução</summary>

```sql
-- Tabela de departamentos
CREATE TABLE departments (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    location VARCHAR(255)
);

-- Tabela de funcionários
CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    department_id INT REFERENCES departments(id)
);

-- Models no Laravel
class Department extends Model
{
    protected $fillable = ['name', 'location'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}

class Employee extends Model
{
    protected $fillable = ['name', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

// Uso
$employee = Employee::with('department')->find(1);
echo $employee->department->name;
echo $employee->department->location;
```

**Pontos-chave:** 3NF elimina dependência transitiva. `department_name` e `department_location` dependem de `department_id`, não de `employee.id` direto. Extraímos para outra tabela.
</details>

---

### Exercício 3: Encontrar anomalias

**Enunciado:** Temos esta tabela:

```sql
CREATE TABLE student_courses (
    student_id INT,
    student_name VARCHAR(255),
    student_email VARCHAR(255),
    course_id INT,
    course_name VARCHAR(255),
    instructor_name VARCHAR(255),
    grade CHAR(2),
    PRIMARY KEY (student_id, course_id)
);
```

Quais anomalias podem aparecer? Como corrigir?

<details>
<summary>Solução</summary>

**Anomalias:**

1. **Insert Anomaly:** Não dá para cadastrar curso sem aluno
2. **Update Anomaly:** Mudar o email do aluno exige UPDATE em todos os cursos dele
3. **Delete Anomaly:** Apagar o último registro do aluno apaga os dados dele

**Solução: normalizar até 3NF**

```sql
CREATE TABLE students (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE
);

CREATE TABLE courses (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    instructor_name VARCHAR(255)
);

CREATE TABLE enrollments (
    student_id INT REFERENCES students(id),
    course_id INT REFERENCES courses(id),
    grade CHAR(2),
    PRIMARY KEY (student_id, course_id)
);
```

**Models no Laravel:**

```php
class Student extends Model
{
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot('grade');
    }
}

class Course extends Model
{
    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->withPivot('grade');
    }
}

// Uso
$student = Student::with('courses')->find(1);
foreach ($student->courses as $course) {
    echo $course->name . ': ' . $course->pivot->grade;
}
```

**Vantagens:**
- Dá para cadastrar curso sem aluno
- Email muda num lugar só
- Apagar o enrollment não apaga aluno/curso
</details>

---

## Na entrevista

> "Normalização é organizar os dados para reduzir redundância. Formas: 1NF (valores atômicos, sem array), 2NF (sem dependência parcial da chave composta), 3NF (sem dependência transitiva). Na prática, 3NF. Prós: sem duplicata, consistência, UPDATE simples, sem anomalias (insert/update/delete). Contras: mais JOIN, SELECT mais lento. Normaliza para OLTP (transação), desnormaliza para OLAP (analítica). No Laravel: relationships no lugar de JOIN, foreign keys nas migrations."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
