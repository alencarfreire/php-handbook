# 12.1 O básico do Docker

## Resumo

> **Docker** — plataforma de containerização de apps. Empacota o app com as dependências num container isolado.
>
> **Comandos principais:** `docker run` (rodar), `docker ps` (listar), `docker exec` (executar comando), `docker logs` (logs).
>
> **Importante:** Images (imagens) vs Containers (instâncias em execução), Volumes (dados persistentes), Networks (comunicação entre containers).

---

## Conteúdo

- [O que é](#o-que-é)
- [Comandos principais](#comandos-principais)
- [Docker para PHP/Laravel](#docker-para-phplaravel)
- [Networks](#networks)
- [Volumes](#volumes)
- [Exemplos práticos](#exemplos-práticos)
- [Limpeza](#limpeza)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Docker — plataforma de containerização de apps. Empacota o app com as dependências num container isolado.

**Conceitos principais:**
- **Image** — imagem (template)
- **Container** — instância em execução da image
- **Dockerfile** — instruções para criar a image
- **Registry** — repositório de images (Docker Hub)

---

## Comandos principais

**Images:**

```bash
# Baixar a image
docker pull php:8.2-fpm

# Listar as images
docker images

# Remover a image
docker rmi php:8.2-fpm

# Criar a image a partir do Dockerfile
docker build -t myapp:latest .

# Ver o histórico da image
docker history myapp:latest
```

**Containers:**

```bash
# Rodar o container
docker run nginx

# Rodar em background (-d detached)
docker run -d nginx

# Rodar com nome
docker run -d --name my-nginx nginx

# Rodar com portas (-p host:container)
docker run -d -p 8080:80 nginx

# Rodar com volumes
docker run -d -v /local/path:/container/path nginx

# Listar os containers em execução
docker ps

# Listar todos os containers (incluindo os parados)
docker ps -a

# Parar o container
docker stop my-nginx

# Remover o container
docker rm my-nginx

# Remover o container à força
docker rm -f my-nginx
```

**Logs e exec:**

```bash
# Ver os logs
docker logs my-nginx

# Logs em tempo real
docker logs -f my-nginx

# Rodar um comando no container
docker exec my-nginx ls /var/www

# Entrar no container (bash)
docker exec -it my-nginx bash

# Entrar no container (sh no alpine)
docker exec -it my-nginx sh
```

---

## Docker para PHP/Laravel

**Rodar um app PHP:**

```bash
# Rodar o PHP-FPM
docker run -d \
  --name php-app \
  -v $(pwd):/var/www/html \
  -p 9000:9000 \
  php:8.2-fpm

# Rodar o Nginx
docker run -d \
  --name nginx \
  -v $(pwd):/var/www/html \
  -v $(pwd)/nginx.conf:/etc/nginx/conf.d/default.conf \
  -p 8080:80 \
  --link php-app \
  nginx

# O app fica em http://localhost:8080
```

**Rodar o Composer:**

```bash
# Instalar as dependências
docker run --rm \
  -v $(pwd):/app \
  composer install

# Atualizar as dependências
docker run --rm \
  -v $(pwd):/app \
  composer update
```

**Rodar o Artisan:**

```bash
# Rodar as migrations
docker exec php-app php artisan migrate

# Limpar o cache
docker exec php-app php artisan cache:clear

# Criar um controller
docker exec php-app php artisan make:controller UserController
```

---

## Networks

**Criar e usar:**

```bash
# Criar a network
docker network create myapp-network

# Rodar os containers na network
docker run -d --name mysql --network myapp-network mysql:8
docker run -d --name php --network myapp-network php:8.2-fpm

# Os containers se falam pelo nome
# No PHP: DB_HOST=mysql

# Listar as networks
docker network ls

# Conectar o container na network
docker network connect myapp-network my-nginx

# Desconectar da network
docker network disconnect myapp-network my-nginx
```

---

## Volumes

**Tipos:**

```bash
# 1. Bind mount (pasta local)
docker run -v /local/path:/container/path nginx

# 2. Named volume (o Docker gerencia)
docker volume create myapp-data
docker run -v myapp-data:/var/lib/mysql mysql

# 3. Anonymous volume
docker run -v /var/lib/mysql mysql

# Listar os volumes
docker volume ls

# Remover o volume
docker volume rm myapp-data

# Remover volumes sem uso
docker volume prune
```

**Volumes no Laravel:**

```bash
# Storage para logs e cache
docker run -d \
  -v $(pwd)/storage:/var/www/html/storage \
  -v $(pwd)/bootstrap/cache:/var/www/html/bootstrap/cache \
  php:8.2-fpm
```

---

## Exemplos práticos

**MySQL container:**

```bash
# Rodar o MySQL
docker run -d \
  --name mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=laravel \
  -e MYSQL_USER=laravel \
  -e MYSQL_PASSWORD=secret \
  -p 3306:3306 \
  -v mysql-data:/var/lib/mysql \
  mysql:8

# Conectar no MySQL
docker exec -it mysql mysql -u root -p

# Ou pelo app
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```

**Redis container:**

```bash
# Rodar o Redis
docker run -d \
  --name redis \
  -p 6379:6379 \
  redis:alpine

# Conectar no Redis CLI
docker exec -it redis redis-cli
```

**Mailhog (para testar email):**

```bash
# Rodar o Mailhog
docker run -d \
  --name mailhog \
  -p 1025:1025 \
  -p 8025:8025 \
  mailhog/mailhog

# Laravel .env
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025

# Web UI: http://localhost:8025
```

---

## Limpeza

**Remover containers e images:**

```bash
# Parar todos os containers
docker stop $(docker ps -aq)

# Remover todos os containers parados
docker rm $(docker ps -aq)

# Remover todas as images sem uso
docker image prune -a

# Remover tudo (containers, images, networks, volumes)
docker system prune -a --volumes

# Ver quanto espaço o Docker está usando
docker system df
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Docker containeriza apps
- Image — template/imagem, Container — instância em execução
- Isola processos, filesystem e rede

**Comandos principais:**
- `docker run` — rodar o container
- `-d` — detached (background), `-p` — portas, `-v` — volumes
- `docker ps` — listar os containers em execução
- `docker exec` — rodar um comando no container
- `docker logs` — ver os logs

**Networks:**
- Containers na mesma network se falam pelo nome
- `docker network create` — criar a network
- `--network` — conectar na network

**Volumes:**
- Dados persistentes (sobrevivem à exclusão do container)
- Bind mount — pasta local
- Named volume — o Docker gerencia

**Laravel:**
- PHP-FPM + Nginx + MySQL + Redis
- Composer e Artisan via `docker exec`
- docker-compose para gerenciar vários containers

---

## Exercícios práticos

### Exercício 1: Rode um app Laravel no Docker

**Enunciado:** Crie e rode um app Laravel com MySQL e Redis, sem docker-compose.

<details>
<summary>Solução</summary>

```bash
# Passo 1: Criar a network
docker network create laravel-network

# Passo 2: Rodar o MySQL
docker run -d \
  --name mysql \
  --network laravel-network \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=laravel \
  -e MYSQL_USER=laravel \
  -e MYSQL_PASSWORD=secret \
  -v mysql-data:/var/lib/mysql \
  mysql:8

# Passo 3: Rodar o Redis
docker run -d \
  --name redis \
  --network laravel-network \
  redis:alpine

# Passo 4: Criar o Dockerfile do PHP
cat > Dockerfile <<'EOF'
FROM php:8.2-fpm

# Instalar as extensões
RUN docker-php-ext-install pdo pdo_mysql

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# No development: instala as dependências no build
COPY composer.json composer.lock ./
RUN composer install --no-scripts

COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache
EOF

# Passo 5: Fazer o build da image PHP
docker build -t laravel-app .

# Passo 6: Rodar o PHP-FPM
docker run -d \
  --name php \
  --network laravel-network \
  -v $(pwd):/var/www/html \
  -e DB_HOST=mysql \
  -e DB_DATABASE=laravel \
  -e DB_USERNAME=laravel \
  -e DB_PASSWORD=secret \
  -e REDIS_HOST=redis \
  laravel-app

# Passo 7: Criar o config do Nginx
mkdir -p nginx
cat > nginx/default.conf <<'EOF'
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

# Passo 8: Rodar o Nginx
docker run -d \
  --name nginx \
  --network laravel-network \
  -p 8080:80 \
  -v $(pwd):/var/www/html \
  -v $(pwd)/nginx/default.conf:/etc/nginx/conf.d/default.conf \
  nginx:alpine

# Passo 9: Rodar as migrations
docker exec php php artisan migrate

# Passo 10: Checar
# http://localhost:8080

# Ver os logs
docker logs php
docker logs nginx

# Entrar no container
docker exec -it php bash

# Parar e remover tudo
docker stop nginx php redis mysql
docker rm nginx php redis mysql
docker network rm laravel-network
docker volume rm mysql-data
```
</details>

### Exercício 2: Faça debug de um container com problema

**Enunciado:** O container não inicia ou está com problema. Ache e corrija.

<details>
<summary>Solução</summary>

```bash
# Situação: o container para na hora

# Passo 1: Checar o status
docker ps -a
# STATUS: Exited (1) 5 seconds ago

# Passo 2: Ver os logs
docker logs my-app
# Error: Could not connect to database

# Passo 3: Checar as variáveis de ambiente
docker inspect my-app | grep -A 10 Env
# DB_HOST não está definido!

# Passo 4: Recriar com as variáveis certas
docker rm my-app
docker run -d \
  --name my-app \
  -e DB_HOST=mysql \
  -e DB_DATABASE=laravel \
  --network myapp-network \
  php-app

# Situação 2: o container está no ar, mas não responde

# Checar as portas
docker ps
# PORTS: 9000/tcp (sem mapeamento!)

# Recriar com as portas
docker rm -f my-app
docker run -d \
  --name my-app \
  -p 8080:80 \
  my-app

# Situação 3: Permission denied no storage

# Entrar no container
docker exec -it my-app bash

# Checar o dono
ls -la storage/
# drwxr-xr-x root root

# Corrigir as permissões
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Sair e reiniciar
exit
docker restart my-app

# Comandos úteis para debug:

# Ver os processos no container
docker top my-app

# Estatística de recursos
docker stats my-app

# Informação detalhada
docker inspect my-app

# Ver o diff do filesystem
docker diff my-app

# Ver quais portas estão abertas
docker port my-app

# Testar a rede
docker exec my-app ping mysql
docker exec my-app nc -zv mysql 3306
```
</details>

### Exercício 3: Otimize a image Docker

**Enunciado:** Sua image pesa 2GB. Diminua o tamanho.

<details>
<summary>Solução</summary>

```bash
# Dockerfile original (ruim)
cat > Dockerfile.before <<'EOF'
FROM php:8.2-fpm

# ❌ Cada RUN = uma layer nova
RUN apt-get update
RUN apt-get install -y git
RUN apt-get install -y curl
RUN apt-get install -y zip

# ❌ Copia tudo de uma vez
COPY . /var/www/html

# ❌ Instala as dependências de dev
RUN composer install

WORKDIR /var/www/html
EOF

# Fazer o build
docker build -f Dockerfile.before -t myapp:before .
docker images myapp:before
# myapp      before   abc123   2.1GB

# Dockerfile otimizado
cat > Dockerfile <<'EOF'
# ✅ Usa alpine (menor)
FROM php:8.2-fpm-alpine

# ✅ Junta os RUN
RUN apk add --no-cache \
    git \
    curl \
    zip \
    && rm -rf /var/cache/apk/*

# ✅ Copia os arquivos do Composer separado (cache)
COPY composer.json composer.lock ./

# ✅ Instala as dependências de production
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader

# ✅ Copia só o que precisa
COPY app app/
COPY config config/
COPY database database/
COPY public public/
COPY resources resources/
COPY routes routes/
COPY bootstrap bootstrap/
COPY artisan ./

# Termina a instalação do Composer
RUN composer dump-autoload --optimize

# Permissões
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

CMD ["php-fpm"]
EOF

# .dockerignore (exclui o que não precisa)
cat > .dockerignore <<'EOF'
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
Dockerfile
EOF

# Build da versão otimizada
docker build -t myapp:after .
docker images myapp:after
# myapp      after   def456   350MB

# Comparar os tamanhos
docker images | grep myapp
# before: 2.1GB → after: 350MB (economia de 83%!)

# Bônus: Multi-stage build
cat > Dockerfile.multistage <<'EOF'
# Stage 1: Composer
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Production
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

# Copia só o vendor do primeiro stage
COPY --from=composer /app/vendor ./vendor

# Copia o código
COPY . .

# O resto...
CMD ["php-fpm"]
EOF

docker build -f Dockerfile.multistage -t myapp:multistage .
# Ainda menor!
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
