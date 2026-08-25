# 12.2 Dockerfile

## Resumo

> **Dockerfile** — arquivo com as instruções para criar a imagem Docker.
>
> **Instruções principais:** FROM (imagem base), RUN (comandos no build), COPY (arquivos), CMD (comando de start), WORKDIR (diretório de trabalho).
>
> **Importante:** multi-stage build para otimizar, cache de camadas, .dockerignore para excluir arquivos.

---

## Conteúdo

- [O que é](#o-que-é)
- [Dockerfile básico](#dockerfile-básico)
- [Instruções do Dockerfile](#instruções-do-dockerfile)
- [Dockerfile no Laravel](#dockerfile-no-laravel)
- [Multi-stage build](#multi-stage-build)
- [.dockerignore](#dockerignore)
- [Dicas práticas](#dicas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Dockerfile — arquivo com as instruções para criar a imagem Docker. Descreve o ambiente da app.

**Instruções principais:**
- `FROM` — imagem base
- `RUN` — rodar comando no build
- `COPY` — copiar arquivos
- `CMD` — comando quando o container inicia
- `EXPOSE` — documentar a porta

---

## Dockerfile básico

**Exemplo simples:**

```dockerfile
# Imagem base
FROM php:8.2-fpm

# Diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos
COPY . /var/www/html

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar dependências PHP
RUN composer install --no-dev --optimize-autoloader

# Expor a porta
EXPOSE 9000

# Comando de start
CMD ["php-fpm"]
```

**Build da imagem:**

```bash
# Build
docker build -t myapp:latest .

# Build com tag
docker build -t myapp:1.0.0 .

# Build com outro arquivo
docker build -f Dockerfile.prod -t myapp:prod .
```

---

## Instruções do Dockerfile

**FROM:**

```dockerfile
# Imagem oficial
FROM php:8.2-fpm

# Alpine (menor)
FROM php:8.2-fpm-alpine

# Versão pinada
FROM php:8.2.10-fpm
```

**WORKDIR:**

```dockerfile
# Definir o diretório de trabalho
WORKDIR /var/www/html

# Os comandos rodam relativos ao WORKDIR
```

**COPY vs ADD:**

```dockerfile
# COPY (prefira este)
COPY ./src /var/www/html

# ADD (descompacta archive e baixa por URL)
ADD https://example.com/file.tar.gz /tmp/
```

**RUN:**

```dockerfile
# Cada RUN cria uma camada nova
RUN apt-get update
RUN apt-get install -y git

# Melhor: juntar numa camada só
RUN apt-get update && apt-get install -y \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*
```

**ENV:**

```dockerfile
# Variáveis de ambiente
ENV APP_ENV=production
ENV APP_DEBUG=false

# Uso
RUN echo $APP_ENV
```

**ARG:**

```dockerfile
# Argumentos de build (não existem no runtime)
ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-fpm

# Passar no build
# docker build --build-arg PHP_VERSION=8.3 -t myapp .
```

**EXPOSE:**

```dockerfile
# Documentação: qual porta o container usa
EXPOSE 9000

# Não abre a porta! Precisa de -p no docker run
```

**CMD vs ENTRYPOINT:**

```dockerfile
# CMD (dá para sobrescrever no docker run)
CMD ["php-fpm"]

# ENTRYPOINT (sempre roda)
ENTRYPOINT ["php-fpm"]

# Combinando
ENTRYPOINT ["php"]
CMD ["artisan", "serve"]
# docker run myapp → php artisan serve
# docker run myapp tinker → php tinker
```

---

## Dockerfile no Laravel

**Production Dockerfile:**

```dockerfile
FROM php:8.2-fpm-alpine

# Instalar dependências do sistema
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip

# Instalar extensões PHP
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do Composer
COPY composer.json composer.lock ./

# Instalar dependências (sem dev)
RUN composer install --no-dev --no-scripts --no-autoloader

# Copiar o resto dos arquivos
COPY . .

# Finalizar o Composer
RUN composer dump-autoload --optimize

# Permissões
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Otimização do Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

EXPOSE 9000

CMD ["php-fpm"]
```

**Development Dockerfile:**

```dockerfile
FROM php:8.2-fpm

# Instalar Xdebug para debug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip

# Extensões PHP
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# No dev não copia o código, usa volume
# docker run -v $(pwd):/var/www/html

EXPOSE 9000

CMD ["php-fpm"]
```

---

## Multi-stage build

**Otimizar o tamanho:**

```dockerfile
# Stage 1: Build
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Production
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Copiar só o vendor do primeiro stage
COPY --from=composer /app/vendor ./vendor
COPY . .

# ... resto das instruções
```

**Com Node.js para os assets:**

```dockerfile
# Stage 1: Build assets
FROM node:18 AS node
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Composer
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev

# Stage 3: Production
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Copiar os assets compilados
COPY --from=node /app/public/build ./public/build

# Copiar o vendor
COPY --from=composer /app/vendor ./vendor

# Copiar o resto
COPY . .

CMD ["php-fpm"]
```

---

## .dockerignore

**Excluir da imagem:**

```
# .dockerignore
.git
.gitignore
.env
.env.*
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
tests
.phpunit.result.cache
README.md
docker-compose.yml
```

---

## Dicas práticas

**Cache de camadas:**

```dockerfile
# ❌ RUIM: copia o código inteiro, qualquer mudança derruba o cache
COPY . .
RUN composer install

# ✅ BOM: primeiro os arquivos do Composer, depois o código
COPY composer.json composer.lock ./
RUN composer install
COPY . .
# O cache do Composer fica se o composer.json não mudou
```

**Health check:**

```dockerfile
# Checagem de saúde do container
HEALTHCHECK --interval=30s --timeout=3s \
  CMD php artisan health:check || exit 1
```

**Usuário:**

```dockerfile
# Não rode como root
RUN addgroup -g 1000 laravel && \
    adduser -D -u 1000 -G laravel laravel

USER laravel
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Dockerfile — instruções para criar a imagem
- Cada instrução = uma camada na imagem
- As camadas entram em cache para o rebuild ficar rápido

**Instruções principais:**
- **FROM** — imagem base
- **RUN** — comandos no build (apt-get, composer)
- **COPY** — copiar arquivos do host
- **WORKDIR** — diretório de trabalho
- **CMD** — comando de start do container
- **EXPOSE** — documenta a porta

**Multi-stage build:**
- Stages separados para tarefas diferentes
- Composer num stage, Node em outro
- A imagem final copia só o resultado
- Diminui o tamanho da imagem

**Otimização:**
- Imagens Alpine (menor)
- Juntar comandos RUN (menos camadas)
- .dockerignore (tirar o que não entra)
- Cache: primeiro as dependências, depois o código

**Laravel:**
- Composer install antes do COPY do código
- Permissões em storage e bootstrap/cache
- config:cache, route:cache, view:cache em production

---

## Exercícios práticos

### Exercício 1: Crie um Dockerfile de production otimizado

Crie um Dockerfile para Laravel com multi-stage build, tamanho mínimo e cache.

<details>
<summary>Solução</summary>

```dockerfile
# Stage 1: Composer dependencies
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

# Stage 2: Node assets (se precisar)
FROM node:18-alpine AS node
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# Stage 3: Production image
FROM php:8.2-fpm-alpine

# Instalar dependências do sistema (o mínimo)
RUN apk add --no-cache \
    libpng \
    libzip \
    mysql-client \
    && apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    gd \
    opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Opcache em production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copiar o vendor do stage composer
COPY --from=composer /app/vendor ./vendor

# Copiar os assets buildados do stage node
COPY --from=node /app/public/build ./public/build

# Copiar o código da app
COPY --chown=www-data:www-data . .

# Finalizar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --classmap-authoritative

# Permissões (só storage e cache)
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Otimização do Laravel em production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Remover o Composer (não precisa em production)
RUN rm /usr/bin/composer

# Trocar para www-data
USER www-data

EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s \
    CMD php artisan inspire || exit 1

CMD ["php-fpm"]
```

**Resultado:**
- Tamanho da imagem: ~100-150MB (em vez de 500MB+)
- Só dependências de production
- Opcache ligado
- Cache do Laravel já gerado
- Segurança: roda como www-data

</details>

### Exercício 2: Dockerfile de development com hot reload

Crie um Dockerfile de development com Xdebug e reload automático.

<details>
<summary>Solução</summary>

```dockerfile
FROM php:8.2-fpm

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    bcmath

# Instalar Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Config do Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Config de development do PHP
RUN echo "display_errors=On" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/dev.ini

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar Node.js para o Vite
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

WORKDIR /var/www/html

# No dev não copia o código — usa volume
# docker run -v $(pwd):/var/www/html

# Entrypoint que instala as dependências sozinho
RUN echo '#!/bin/bash\n\
if [ ! -d "vendor" ]; then\n\
    composer install\n\
fi\n\
if [ ! -d "node_modules" ]; then\n\
    npm install\n\
fi\n\
php-fpm\n\
' > /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
```

**docker-compose.yml para development:**

```yaml
version: '3.8'

services:
  php:
    build:
      context: .
      dockerfile: Dockerfile.dev
    volumes:
      - ./:/var/www/html
      # Isolar vendor e node_modules do host
      - /var/www/html/vendor
      - /var/www/html/node_modules
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - XDEBUG_MODE=debug
    ports:
      - "9000:9000"
      - "9003:9003"  # Xdebug
    extra_hosts:
      - "host.docker.internal:host-gateway"

  vite:
    image: node:18
    working_dir: /app
    volumes:
      - ./:/app
    command: npm run dev -- --host
    ports:
      - "5173:5173"
```

**Uso:**
```bash
# Rodar
docker-compose up -d

# O Xdebug conecta sozinho na request
# VS Code: instale a extension PHP Debug
# Config do launch.json:
{
    "name": "Listen for Xdebug",
    "type": "php",
    "request": "launch",
    "port": 9003,
    "pathMappings": {
        "/var/www/html": "${workspaceFolder}"
    }
}

# Hot reload do Vite funciona sozinho
# http://localhost:5173
```

</details>

### Exercício 3: Dockerfile com secrets (sem .env na imagem)

Crie um Dockerfile que não inclui .env na imagem final.

<details>
<summary>Solução</summary>

```dockerfile
# Production Dockerfile SEM .env na imagem

FROM php:8.2-fpm-alpine

# Instalar dependências
RUN apk add --no-cache \
    libpng libzip mysql-client

# Extensões PHP
RUN docker-php-ext-install pdo_mysql zip gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar arquivos do Composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# ✅ Copiar o código SEM .env
COPY --chown=www-data:www-data . .

# ❌ NÃO copie o arquivo .env!
# As variáveis vêm do ambiente do container

# O .dockerignore precisa ter:
# .env
# .env.*
# .env.example

# Permissões
RUN chown -R www-data:www-data storage bootstrap/cache

# Criar .env a partir de um template (sem secrets)
RUN echo "APP_NAME=Laravel\n\
APP_ENV=production\n\
APP_KEY=\n\
APP_DEBUG=false\n\
# O resto das variáveis vem do ambiente\n\
" > .env.docker

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
```

**.dockerignore (IMPORTANTE!):**
```
.env
.env.*
!.env.example
.git
node_modules
vendor
tests
```

**docker-compose.yml com secrets:**

```yaml
version: '3.8'

services:
  php:
    build: .
    environment:
      # Secrets das variáveis de ambiente do host
      - APP_KEY=${APP_KEY}
      - DB_PASSWORD=${DB_PASSWORD}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
    env_file:
      # Ou de um arquivo (fora do git!)
      - .env.production
    secrets:
      - db_password
      - app_key

secrets:
  db_password:
    file: ./secrets/db_password.txt
  app_key:
    file: ./secrets/app_key.txt
```

**Opção: usar Docker secrets:**

```bash
# Criar os secrets
echo "secret_password" | docker secret create db_password -
echo "base64:xxx" | docker secret create app_key -

# Dockerfile lendo secrets
RUN --mount=type=secret,id=app_key \
    APP_KEY=$(cat /run/secrets/app_key) \
    php artisan config:cache
```

**Kubernetes ConfigMap + Secrets:**

```yaml
# configmap.yaml (config que não é secret)
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
data:
  APP_ENV: "production"
  APP_DEBUG: "false"

---
# secret.yaml (dados secretos)
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secrets
type: Opaque
data:
  APP_KEY: base64_encoded_key
  DB_PASSWORD: base64_encoded_password
```

**Boas práticas:**
1. ❌ Nunca faça COPY .env na imagem
2. ✅ Use variáveis de ambiente
3. ✅ Docker secrets / Kubernetes secrets
4. ✅ .dockerignore para proteger
5. ✅ Escaneie as imagens em busca de vazamento de secret

```bash
# Conferir se o .env não entrou na imagem
docker run myapp:latest cat .env
# cat: can't open '.env': No such file or directory ✅

# Escanear a imagem em busca de secrets
docker scan myapp:latest
```

</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
