# 16.1 Coding Challenges

## Tarefas típicas na entrevista

### 1. Trabalho com strings

**Palíndromo:**

```php
function isPalindrome(string $str): bool
{
    $str = strtolower(preg_replace('/[^a-z0-9]/i', '', $str));
    return $str === strrev($str);
}

// Testes
isPalindrome('A man a plan a canal Panama'); // true
isPalindrome('racecar'); // true
isPalindrome('hello'); // false
```

**Anagramas:**

```php
function areAnagrams(string $str1, string $str2): bool
{
    $str1 = strtolower(str_replace(' ', '', $str1));
    $str2 = strtolower(str_replace(' ', '', $str2));

    $chars1 = str_split($str1);
    $chars2 = str_split($str2);

    sort($chars1);
    sort($chars2);

    return $chars1 === $chars2;
}

// Testes
areAnagrams('listen', 'silent'); // true
areAnagrams('hello', 'world'); // false
```

**Primeiro caractere único:**

```php
function firstUniqChar(string $s): int
{
    $counts = [];

    // Contagem
    for ($i = 0; $i < strlen($s); $i++) {
        $char = $s[$i];
        $counts[$char] = ($counts[$char] ?? 0) + 1;
    }

    // Achar o primeiro com count = 1
    for ($i = 0; $i < strlen($s); $i++) {
        if ($counts[$s[$i]] === 1) {
            return $i;
        }
    }

    return -1;
}

// Testes
firstUniqChar('leetcode'); // 0 ('l')
firstUniqChar('loveleetcode'); // 2 ('v')
```

---

### 2. Trabalho com arrays

**Dois números com a soma:**

```php
// Achar dois números cuja soma = target
function twoSum(array $nums, int $target): array
{
    $map = [];

    foreach ($nums as $i => $num) {
        $complement = $target - $num;

        if (isset($map[$complement])) {
            return [$map[$complement], $i];
        }

        $map[$num] = $i;
    }

    return [];
}

// Testes
twoSum([2, 7, 11, 15], 9); // [0, 1] (2 + 7 = 9)
twoSum([3, 2, 4], 6); // [1, 2] (2 + 4 = 6)
```

**Encontrar duplicatas:**

```php
function findDuplicates(array $arr): array
{
    $seen = [];
    $duplicates = [];

    foreach ($arr as $item) {
        if (isset($seen[$item])) {
            $duplicates[] = $item;
        }
        $seen[$item] = true;
    }

    return array_unique($duplicates);
}

// Testes
findDuplicates([1, 2, 3, 2, 4, 5, 3]); // [2, 3]
```

**Rotacionar array:**

```php
function rotateArray(array $arr, int $k): array
{
    $n = count($arr);
    $k = $k % $n; // Trata k > n

    // Inverte tudo
    $arr = array_reverse($arr);
    // Inverte os primeiros k
    $part1 = array_reverse(array_slice($arr, 0, $k));
    // Inverte o resto
    $part2 = array_reverse(array_slice($arr, $k));

    return array_merge($part1, $part2);
}

// Testes
rotateArray([1, 2, 3, 4, 5], 2); // [4, 5, 1, 2, 3]
```

---

### 3. FizzBuzz (clássico)

```php
function fizzBuzz(int $n): array
{
    $result = [];

    for ($i = 1; $i <= $n; $i++) {
        if ($i % 15 === 0) {
            $result[] = 'FizzBuzz';
        } elseif ($i % 3 === 0) {
            $result[] = 'Fizz';
        } elseif ($i % 5 === 0) {
            $result[] = 'Buzz';
        } else {
            $result[] = (string) $i;
        }
    }

    return $result;
}

// Output: 1, 2, Fizz, 4, Buzz, Fizz, 7, 8, Fizz, Buzz, 11, Fizz, 13, 14, FizzBuzz...
```

---

### 4. Validação de parênteses

```php
function isValidParentheses(string $s): bool
{
    $stack = [];
    $pairs = [
        ')' => '(',
        '}' => '{',
        ']' => '['
    ];

    for ($i = 0; $i < strlen($s); $i++) {
        $char = $s[$i];

        if (in_array($char, ['(', '{', '['])) {
            // Abre
            $stack[] = $char;
        } elseif (isset($pairs[$char])) {
            // Fecha
            if (empty($stack) || array_pop($stack) !== $pairs[$char]) {
                return false;
            }
        }
    }

    return empty($stack);
}

// Testes
isValidParentheses('()'); // true
isValidParentheses('()[]{}'); // true
isValidParentheses('(]'); // false
isValidParentheses('([)]'); // false
isValidParentheses('{[]}'); // true
```

---

### 5. Números de Fibonacci

**Recursivo (lento):**

```php
function fibRecursive(int $n): int
{
    if ($n <= 1) {
        return $n;
    }

    return fibRecursive($n - 1) + fibRecursive($n - 2);
}
// O(2^n) — muito lento
```

**Iterativo (rápido):**

```php
function fib(int $n): int
{
    if ($n <= 1) {
        return $n;
    }

    $prev = 0;
    $curr = 1;

    for ($i = 2; $i <= $n; $i++) {
        $temp = $curr;
        $curr = $prev + $curr;
        $prev = $temp;
    }

    return $curr;
}
// O(n)
```

**Com memoization:**

```php
function fibMemo(int $n, array &$memo = []): int
{
    if ($n <= 1) {
        return $n;
    }

    if (isset($memo[$n])) {
        return $memo[$n];
    }

    $memo[$n] = fibMemo($n - 1, $memo) + fibMemo($n - 2, $memo);
    return $memo[$n];
}
// O(n) com O(n) de memória
```

---

### 6. Reverter string/array

```php
// String
function reverseString(string $s): string
{
    return strrev($s);
    // Ou na mão:
    // return implode('', array_reverse(str_split($s)));
}

// Array
function reverseArray(array $arr): array
{
    $left = 0;
    $right = count($arr) - 1;

    while ($left < $right) {
        $temp = $arr[$left];
        $arr[$left] = $arr[$right];
        $arr[$right] = $temp;

        $left++;
        $right--;
    }

    return $arr;
}
```

---

### 7. Tarefas específicas de Laravel

**Encontrar users com > N pedidos:**

```php
// No último mês, com mais de 10 pedidos
User::has('orders', '>', 10)
    ->whereHas('orders', function ($query) {
        $query->where('created_at', '>=', now()->subMonth());
    })
    ->get();
```

**Top 5 produtos:**

```php
Product::withCount('orderItems')
    ->orderBy('order_items_count', 'desc')
    ->limit(5)
    ->get();
```

**Valor médio do pedido por usuário:**

```php
User::select('users.id', 'users.name')
    ->selectRaw('AVG(orders.total) as avg_order_value')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->groupBy('users.id', 'users.name')
    ->having('avg_order_value', '>', 100)
    ->get();
```

---

### 8. Algoritmos de ordenação

**Bubble Sort:**

```php
function bubbleSort(array $arr): array
{
    $n = count($arr);

    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                // Troca
                $temp = $arr[$j];
                $arr[$j] = $arr[$j + 1];
                $arr[$j + 1] = $temp;
            }
        }
    }

    return $arr;
}
// O(n²)
```

**Quick Sort:**

```php
function quickSort(array $arr): array
{
    if (count($arr) <= 1) {
        return $arr;
    }

    $pivot = $arr[0];
    $left = $right = [];

    for ($i = 1; $i < count($arr); $i++) {
        if ($arr[$i] < $pivot) {
            $left[] = $arr[$i];
        } else {
            $right[] = $arr[$i];
        }
    }

    return array_merge(
        quickSort($left),
        [$pivot],
        quickSort($right)
    );
}
// Média O(n log n)
```

---

### 9. Busca binária

```php
function binarySearch(array $arr, int $target): int
{
    $left = 0;
    $right = count($arr) - 1;

    while ($left <= $right) {
        $mid = floor(($left + $right) / 2);

        if ($arr[$mid] === $target) {
            return $mid;
        }

        if ($arr[$mid] < $target) {
            $left = $mid + 1;
        } else {
            $right = $mid - 1;
        }
    }

    return -1; // Não encontrado
}

// Testes
binarySearch([1, 3, 5, 7, 9, 11], 7); // 3
binarySearch([1, 3, 5, 7, 9, 11], 6); // -1
```

---

### 10. Máximo/mínimo no array

```php
function findMax(array $arr): ?int
{
    if (empty($arr)) {
        return null;
    }

    $max = $arr[0];

    foreach ($arr as $num) {
        if ($num > $max) {
            $max = $num;
        }
    }

    return $max;
}

// Ou a função nativa
$max = max($arr);
$min = min($arr);
```

---

## Dicas para resolver os problemas

**Processo:**

```
1. Esclareça o problema
   "A string pode ser vazia?"
   "É case sensitive?"

2. Invente exemplos
   Input: "hello"
   Output: "olleh"

   Edge cases:
   - String vazia: ""
   - Um caractere: "a"
   - Caracteres especiais: "a-b-c"

3. Discuta a abordagem
   "Dá para resolver com array reverse
    ou dois ponteiros"

4. Escreva o código
   Comece pela solução simples

5. Teste
   Cheque os edge cases

6. Otimize
   Dá para melhorar a complexidade?
```

**Complexidade dos algoritmos:**

```
O(1) — constante:
  array access, hash lookup

O(log n) — logarítmica:
  binary search

O(n) — linear:
  foreach, array_map

O(n log n):
  merge sort, quick sort (average)

O(n²):
  loops aninhados, bubble sort

O(2^n):
  recursive fibonacci (sem memo)
```

---

## Na entrevista

> "Coding challenges: palíndromo, anagrama, dois números com a soma, FizzBuzz, validação de parênteses, Fibonacci. Para string: strrev, preg_replace. Para array: dois ponteiros, hash map. Laravel: whereHas, withCount, selectRaw. Ordenação: bubble O(n²), quick O(n log n). Busca binária O(log n). Processo: esclarecer, exemplos, edge cases, código, testes, otimização. Complexidade: O(1), O(n), O(n log n), O(n²)."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
