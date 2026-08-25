# 13.4 Otimização de frontend

## Resumo

> **Otimização de frontend** — acelerar o carregamento da página com minificação, compressão, CDN e lazy loading.
>
> **Métricas:** FCP (First Contentful Paint), LCP (Largest Contentful Paint), TTI (Time to Interactive), CLS (Layout Shift).
>
> **Métodos:** Vite no build, Gzip/Brotli, lazy loading de imagens, CDN para estáticos, code splitting, scripts com defer/async.

---

## Conteúdo

- [O que é](#o-que-é)
- [Laravel Mix / Vite](#laravel-mix--vite)
- [Minificação e compressão](#minificação-e-compressão)
- [Otimização de imagens](#otimização-de-imagens)
- [CDN](#cdn)
- [Cache do frontend](#cache-do-frontend)
- [Code Splitting](#code-splitting)
- [Exemplos práticos](#exemplos-práticos)
- [Monitoramento de performance](#monitoramento-de-performance)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Otimizar o frontend para a página carregar rápido. Minificação, compressão, CDN, lazy loading.

**Métricas:**
- **FCP** (First Contentful Paint) — primeiro conteúdo
- **LCP** (Largest Contentful Paint) — conteúdo principal
- **TTI** (Time to Interactive) — interatividade
- **CLS** (Cumulative Layout Shift) — estabilidade

---

## Laravel Mix / Vite

**Vite (Laravel 9+):**

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['vue', 'axios'],
                },
            },
        },
    },
});
```

**Build de production:**

```bash
# Build com minificação
npm run build

# Resultado em public/build/
# - assets/app-[hash].js
# - assets/app-[hash].css
```

**No Blade:**

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}
</body>
</html>
```

---

## Minificação e compressão

**Gzip/Brotli (Nginx):**

```nginx
# /etc/nginx/nginx.conf
http {
    # Gzip
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
    gzip_min_length 1000;
    gzip_comp_level 6;

    # Brotli (se o módulo estiver instalado)
    brotli on;
    brotli_types text/plain text/css application/json application/javascript text/xml application/xml;
    brotli_comp_level 6;
}
```

**Checagem:**

```bash
# Checar gzip
curl -H "Accept-Encoding: gzip" -I http://example.com/app.js

# Deve devolver:
# Content-Encoding: gzip
```

---

## Otimização de imagens

**Lazy loading:**

```blade
{{-- Lazy loading nativo --}}
<img src="/images/photo.jpg" loading="lazy" alt="Foto">

{{-- Para imagem de fundo --}}
<div class="lazy-bg" data-bg="/images/hero.jpg"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lazyBg = document.querySelectorAll('.lazy-bg');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.backgroundImage = `url(${entry.target.dataset.bg})`;
                observer.unobserve(entry.target);
            }
        });
    });

    lazyBg.forEach(el => observer.observe(el));
});
</script>
```

**Responsive images:**

```blade
<picture>
    <source srcset="/images/photo.webp" type="image/webp">
    <source srcset="/images/photo.jpg" type="image/jpeg">
    <img src="/images/photo.jpg" alt="Foto">
</picture>

{{-- Tamanhos diferentes --}}
<img srcset="/images/photo-320.jpg 320w,
             /images/photo-640.jpg 640w,
             /images/photo-1280.jpg 1280w"
     sizes="(max-width: 640px) 100vw, 640px"
     src="/images/photo-640.jpg"
     alt="Foto">
```

**Image optimization package:**

```bash
composer require spatie/laravel-image-optimizer
```

```php
use Spatie\ImageOptimizer\OptimizerChainFactory;

$optimizerChain = OptimizerChainFactory::create();
$optimizerChain->optimize($pathToImage);
```

---

## CDN

**Configuração:**

```env
# .env
ASSET_URL=https://cdn.example.com
```

```php
// config/filesystems.php
'cloud' => env('FILESYSTEM_CLOUD', 's3'),

's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
],
```

**Uso:**

```php
// Enviar para o CDN
Storage::disk('s3')->put('avatars/1.jpg', $file);

// URL do CDN
$url = Storage::disk('s3')->url('avatars/1.jpg');
// https://cdn.example.com/avatars/1.jpg
```

**Blade helper:**

```blade
{{-- Usa ASSET_URL automaticamente --}}
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
```

---

## Cache do frontend

**HTTP Cache headers:**

```php
// routes/web.php
Route::get('/images/{file}', function ($file) {
    $path = storage_path("app/public/images/$file");

    return response()->file($path, [
        'Cache-Control' => 'public, max-age=31536000',  // 1 ano
        'Expires' => now()->addYear()->toRfc7231String(),
    ]);
});
```

**Service Worker (PWA):**

```js
// public/sw.js
const CACHE_NAME = 'v1';
const urlsToCache = [
    '/',
    '/css/app.css',
    '/js/app.js',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => response || fetch(event.request))
    );
});
```

---

## Code Splitting

**Import dinâmico:**

```js
// resources/js/app.js

// ❌ RUIM: carrega tudo de uma vez
import Chart from 'chart.js';

// ✅ BOM: carrega quando precisa
document.getElementById('show-chart').addEventListener('click', async () => {
    const { Chart } = await import('chart.js');
    // Usa o Chart
});
```

**Vue lazy loading:**

```js
// router/index.js
const routes = [
    {
        path: '/dashboard',
        // ❌ RUIM
        component: require('./views/Dashboard.vue').default
    },
    {
        path: '/admin',
        // ✅ BOM: lazy load
        component: () => import('./views/Admin.vue')
    }
];
```

---

## Exemplos práticos

**Otimização de fontes:**

```blade
{{-- Preload das fontes críticas --}}
<link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>

{{-- CSS --}}
<style>
@font-face {
    font-family: 'Inter';
    src: url('/fonts/inter.woff2') format('woff2');
    font-display: swap;  /* Mostra o fallback enquanto carrega */
}
</style>
```

**Critical CSS:**

```blade
{{-- Critical CSS inline --}}
<style>
    /* Estilos do conteúdo above-the-fold */
    body { font-family: sans-serif; }
    .header { background: #fff; }
</style>

{{-- Resto do CSS de forma assíncrona --}}
<link rel="preload" href="{{ asset('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="{{ asset('css/app.css') }}"></noscript>
```

**Scripts defer/async:**

```blade
{{-- Async: carrega em paralelo, executa na hora --}}
<script async src="https://www.google-analytics.com/analytics.js"></script>

{{-- Defer: carrega em paralelo, executa depois do DOM --}}
<script defer src="{{ asset('js/app.js') }}"></script>

{{-- Normal: bloqueia o parse --}}
<script src="{{ asset('js/app.js') }}"></script>
```

**Prefetch/Preload:**

```blade
{{-- Preload: carrega agora (prioridade alta) --}}
<link rel="preload" href="/js/app.js" as="script">
<link rel="preload" href="/fonts/font.woff2" as="font" type="font/woff2" crossorigin>

{{-- Prefetch: carrega quando o browser estiver livre (prioridade baixa) --}}
<link rel="prefetch" href="/admin/dashboard.js">

{{-- DNS prefetch --}}
<link rel="dns-prefetch" href="https://cdn.example.com">

{{-- Preconnect --}}
<link rel="preconnect" href="https://api.example.com">
```

---

## Monitoramento de performance

**Lighthouse:**

```bash
# CLI
npm install -g lighthouse
lighthouse https://example.com --view

# No Chrome DevTools
# Aba Lighthouse → Generate report
```

**Web Vitals:**

```js
// resources/js/app.js
import {getCLS, getFID, getFCP, getLCP, getTTFB} from 'web-vitals';

function sendToAnalytics(metric) {
    fetch('/api/analytics', {
        method: 'POST',
        body: JSON.stringify(metric),
    });
}

getCLS(sendToAnalytics);
getFID(sendToAnalytics);
getFCP(sendToAnalytics);
getLCP(sendToAnalytics);
getTTFB(sendToAnalytics);
```

---

## Na entrevista

> "Otimização de frontend: Vite no build com minificação. Compressão Gzip/Brotli. Lazy loading de imagens (loading=lazy). Responsive images (srcset). CDN para estáticos. HTTP cache headers. Code splitting (import dinâmico). Critical CSS inline. Scripts com defer/async. Preload nos recursos críticos. Service Worker para PWA. Web Vitals no monitoramento. Lighthouse para auditar."

---

## Exercícios práticos

### Exercício 1: Configure o Vite com code splitting

**Enunciado:** Configure o Vite para separar automaticamente o código vendor e os async chunks.

<details>
<summary>Solução</summary>

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
    build: {
        rollupOptions: {
            output: {
                // Separar o código vendor
                manualChunks: {
                    'vendor': ['vue', 'axios'],
                    'ui': ['primevue', '@headlessui/vue'],
                    'charts': ['chart.js', 'vue-chartjs'],
                },
            },
        },
        // Tamanho do chunk para o split
        chunkSizeWarningLimit: 1000,
    },
});

// resources/js/app.js
import { createApp } from 'vue';

const app = createApp({});

// Lazy load dos componentes
app.component('UserDashboard', () => import('./components/UserDashboard.vue'));
app.component('AdminPanel', () => import('./components/AdminPanel.vue'));

app.mount('#app');

// Resultado depois do build:
// public/build/assets/
// ├── app-[hash].js          (código principal)
// ├── vendor-[hash].js       (vue, axios)
// ├── ui-[hash].js           (libs de UI)
// ├── charts-[hash].js       (gráficos)
// └── UserDashboard-[hash].js (lazy chunk)
```
</details>

### Exercício 2: Otimize imagens com lazy loading

**Enunciado:** Implemente um componente para carregar imagens otimizadas com WebP, responsive sizes e lazy loading.

<details>
<summary>Solução</summary>

```blade
{{-- resources/views/components/optimized-image.blade.php --}}
@props([
    'src',
    'alt',
    'width' => null,
    'height' => null,
    'sizes' => '100vw',
    'lazy' => true,
])

@php
    $srcWithoutExt = pathinfo($src, PATHINFO_DIRNAME) . '/' . pathinfo($src, PATHINFO_FILENAME);
    $ext = pathinfo($src, PATHINFO_EXTENSION);
@endphp

<picture>
    {{-- WebP para browsers modernos --}}
    <source
        type="image/webp"
        srcset="{{ $srcWithoutExt }}-320.webp 320w,
                {{ $srcWithoutExt }}-640.webp 640w,
                {{ $srcWithoutExt }}-1280.webp 1280w"
        sizes="{{ $sizes }}"
    >

    {{-- Fallback para browsers antigos --}}
    <source
        type="image/{{ $ext }}"
        srcset="{{ $srcWithoutExt }}-320.{{ $ext }} 320w,
                {{ $srcWithoutExt }}-640.{{ $ext }} 640w,
                {{ $srcWithoutExt }}-1280.{{ $ext }} 1280w"
        sizes="{{ $sizes }}"
    >

    {{-- Imagem principal --}}
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        @if($lazy) loading="lazy" @endif
        {{ $attributes->merge(['class' => 'w-full h-auto']) }}
    >
</picture>

{{-- Uso --}}
<x-optimized-image
    src="/images/hero.jpg"
    alt="Imagem hero"
    sizes="(max-width: 640px) 100vw, 50vw"
    class="rounded-lg"
/>

{{-- Service para gerar as versões otimizadas --}}
// app/Services/ImageOptimizationService.php
use Intervention\Image\Facades\Image;

class ImageOptimizationService
{
    private array $sizes = [320, 640, 1280];

    public function optimize(string $path): void
    {
        $image = Image::make($path);
        $pathInfo = pathinfo($path);

        foreach ($this->sizes as $width) {
            // Resize
            $resized = $image->resize($width, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Salva como WebP
            $webpPath = "{$pathInfo['dirname']}/{$pathInfo['filename']}-{$width}.webp";
            $resized->save($webpPath, 80, 'webp');

            // Salva no formato original
            $originalPath = "{$pathInfo['dirname']}/{$pathInfo['filename']}-{$width}.{$pathInfo['extension']}";
            $resized->save($originalPath, 80);
        }
    }
}
```
</details>

### Exercício 3: Critical CSS e carregamento async

**Enunciado:** Coloque o critical CSS inline e carregue o resto de forma assíncrona.

<details>
<summary>Solução</summary>

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Meu App')</title>

    {{-- Critical CSS inline do conteúdo above-the-fold --}}
    <style>
        /* Estilos mínimos da primeira tela */
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.5;
        }
        .header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }
    </style>

    {{-- Preload dos recursos críticos --}}
    <link rel="preload" href="{{ asset('fonts/inter.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- CSS principal em async --}}
    <link rel="preload" href="{{ mix('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ mix('css/app.css') }}"></noscript>

    {{-- Script inline para CSS async (polyfill) --}}
    <script>
        !function(e){"use strict";var t=function(t,n,r){var o,i=e.document,c=i.createElement("link");if(n)o=n;else{var a=(i.body||i.getElementsByTagName("head")[0]).childNodes;o=a[a.length-1]}var d=i.styleSheets;c.rel="stylesheet",c.href=t,c.media="only x",function e(t){if(i.body)return t();setTimeout(function(){e(t)})}(function(){o.parentNode.insertBefore(c,n?o:o.nextSibling)});var f=function(e){for(var t=c.href,n=d.length;n--;)if(d[n].href===t)return e();setTimeout(function(){f(e)})};return c.addEventListener&&c.addEventListener("load",r),c.onloadcssdefined=f,f(r),c};"undefined"!=typeof exports?exports.loadCSS=t:e.loadCSS=t}("undefined"!=typeof global?global:this);
    </script>

    {{-- DNS prefetch de recursos externos --}}
    <link rel="dns-prefetch" href="//cdn.example.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
    <header class="header">
        <div class="container">
            {{-- Conteúdo do header --}}
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    {{-- Scripts com defer --}}
    <script src="{{ mix('js/app.js') }}" defer></script>

    {{-- Analytics em async --}}
    <script async src="https://www.google-analytics.com/analytics.js"></script>
</body>
</html>

{{-- Comando para gerar o critical CSS --}}
// npm install --save-dev critical

// package.json
{
    "scripts": {
        "critical": "critical http://localhost:8000 --base public --inline --minify > resources/views/critical.css"
    }
}

// Resultado:
// - FCP melhorou 40%
// - LCP melhorou 30%
// - Lighthouse score: 95+
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
