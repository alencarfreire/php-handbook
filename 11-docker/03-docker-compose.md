# 12.3 Docker Compose

## Resumo

> **Docker Compose** — ferramenta para gerenciar apps multi-container via arquivo YAML.
>
> **Seções principais:** services (containers), networks (redes), volumes (dados). `docker-compose up -d` inicia todos os services em background.
>
> **No Laravel:** nginx + php + mysql + redis + queue worker num docker-compose.yml só. `docker-compose exec` para rodar comandos nos containers.

---

## Conteúdo

- [O que é](#o-que-é)
- [docker-compose.yml básico](#docker-composeyml-básico)
- [docker-compose.yml do Laravel](#docker-composeyml-do-laravel)
- [Configuração dos services](#configuração-dos-services)
- [Exemplos práticos](#exemplos-práticos)
- [Comandos úteis](#comandos-úteis)
- [Arquivos override](#arquivos-override)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Docker Compose gerencia apps multi-container. Você descreve os services num arquivo YAML.

**Para que serve:**
- Iniciar vários containers com um comando
- Ligar os containers entre si
- Gerenciar networks e volumes

---

## docker-compose.yml básico

**Exemplo simples:**

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    depends_on:
      - mysql

  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
    volumes:
      - mysql-data:/var/lib/mysql

volumes:
  mysql-data:
```

**Comandos:**

```bash
# Iniciar todos os services
docker-compose up

# Iniciar em background
docker-compose up -d

# Rebuild das images
docker-compose up --build

# Parar
docker-compose down

# Parar e remover volumes
docker-compose down -v

# Ver os logs
docker-compose logs

# Logs de um service específico
docker-compose logs app

# Rodar um comando
docker-compose exec app php artisan migrate
```

---

## docker-compose.yml do Laravel

**Stack completo:**

```yaml
version: '3.8'

services:
  # Nginx
  nginx:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - laravel

  # PHP-FPM
  php:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=mysql
      - DB_DATABASE=laravel
      - DB_USERNAME=laravel
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - laravel

  # MySQL
  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - laravel

  # Redis
  redis:
    image: redis:alpine
    ports:
      - "6379:6379"
    networks:
      - laravel

  # Queue Worker
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan queue:work --tries=3
    volumes:
      - ./:/var/www/html
    depends_on:
      - mysql
      - redis
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  mysql-data:
```

**Config do Nginx (docker/nginx/default.conf):**

```nginx
server {
    listen 80;
    index index.php index.html;
    root /var/www/html/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Configuração dos services

**build:**

```yaml
services:
  app:
    # Build simples
    build: .

    # Com parâmetros
    build:
      context: .
      dockerfile: Dockerfile.prod
      args:
        PHP_VERSION: 8.2

    # Ou usar uma image pronta
    image: php:8.2-fpm
```

**environment:**

```yaml
services:
  app:
    # Variáveis de ambiente
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - DB_HOST=mysql

    # Ou a partir de um arquivo
    env_file:
      - .env
```

**volumes:**

```yaml
services:
  app:
    volumes:
      # Bind mount (pasta local)
      - ./:/var/www/html

      # Named volume
      - app-storage:/var/www/html/storage

      # Read-only
      - ./config:/config:ro

volumes:
  app-storage:
```

**ports:**

```yaml
services:
  app:
    ports:
      # host:container
      - "8080:80"

      # Só o container (porta do host aleatória)
      - "80"

      # IP:host:container
      - "127.0.0.1:8080:80"
```

**depends_on:**

```yaml
services:
  app:
    depends_on:
      - mysql
      - redis
    # Inicia mysql e redis antes do app
    # MAS não espera o MySQL ficar pronto!
```

**networks:**

```yaml
services:
  app:
    networks:
      - frontend
      - backend

networks:
  frontend:
  backend:
```

---

## Exemplos práticos

**Setup de development:**

```yaml
version: '3.8'

services:
  php:
    build: .
    volumes:
      - ./:/var/www/html
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
    ports:
      - "8000:8000"
    command: php artisan serve --host=0.0.0.0

  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"

volumes:
  mysql-data:
```

**Comandos:**

```bash
# Iniciar
docker-compose up -d

# Instalar as dependências
docker-compose exec php composer install

# Migrations
docker-compose exec php php artisan migrate

# Criar o controller
docker-compose exec php php artisan make:controller UserController

# Testes
docker-compose exec php php artisan test

# Ver os emails
# http://localhost:8025
```

**Setup de production:**

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./public:/var/www/html/public:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/ssl:/etc/nginx/ssl:ro
    depends_on:
      - php

  php:
    build:
      context: .
      dockerfile: Dockerfile.prod
    restart: always
    volumes:
      - ./storage:/var/www/html/storage
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    env_file:
      - .env.production

  mysql:
    image: mysql:8
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
    volumes:
      - mysql-data:/var/lib/mysql

  redis:
    image: redis:alpine
    restart: always

  queue:
    build:
      context: .
      dockerfile: Dockerfile.prod
    restart: always
    command: php artisan queue:work --tries=3 --timeout=90
    depends_on:
      - mysql
      - redis

volumes:
  mysql-data:
```

---

## Comandos úteis

**Gerenciar:**

```bash
# Recriar os containers
docker-compose up -d --force-recreate

# Parar sem remover
docker-compose stop

# Iniciar os que estão parados
docker-compose start

# Reiniciar
docker-compose restart

# Ver o status
docker-compose ps

# Rodar um comando pontual
docker-compose run --rm php composer install
```

**Logs:**

```bash
# Todos os logs
docker-compose logs

# Últimas 100 linhas
docker-compose logs --tail=100

# Em tempo real
docker-compose logs -f

# Um service específico
docker-compose logs -f php
```

**Escalar:**

```bash
# Iniciar 3 instâncias do queue worker
docker-compose up -d --scale queue=3
```

---

## Arquivos override

**docker-compose.override.yml (aplica sozinho):**

```yaml
# docker-compose.yml
version: '3.8'
services:
  php:
    image: php:8.2-fpm

# docker-compose.override.yml (dev local)
version: '3.8'
services:
  php:
    volumes:
      - ./:/var/www/html
    environment:
      - XDEBUG_MODE=debug
```

**Usar um arquivo específico:**

```bash
# Arquivo de production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Docker Compose gerencia apps multi-container
- Você descreve todos os services num YAML
- Inicia o stack inteiro com um comando

**Seções principais:**
- **services** — containers (app, mysql, redis)
- **networks** — redes para os containers se falarem
- **volumes** — dados persistentes

**Comandos:**
- `docker-compose up -d` — iniciar em background
- `docker-compose down` — parar e remover
- `docker-compose exec` — rodar comando no container
- `docker-compose logs` — ver os logs
- `--scale` — escalar services

**Configuração dos services:**
- **depends_on** — dependências (ordem de start)
- **environment** — variáveis de ambiente
- **volumes** — montar pastas
- **ports** — mapear portas (host:container)
- **restart: always** — restart automático

**Arquivos override:**
- `docker-compose.override.yml` — aplica sozinho
- Configs diferentes para dev/prod
- `-f` para apontar o arquivo

**Laravel:**
- Stack: nginx + php + mysql + redis + queue
- Rede compartilhada, conversam pelo nome (DB_HOST=mysql)
- Named volumes para os dados do MySQL

---

## Exercícios práticos

### Exercício 1: Monte o stack Laravel completo com docker-compose

**Enunciado:** Crie um docker-compose.yml para Laravel com nginx, php, mysql, redis e queue worker. Inclua Mailhog para testar email.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.yml
version: '3.8'

services:
  # Nginx
  nginx:
    image: nginx:alpine
    container_name: laravel-nginx
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - laravel

  # PHP-FPM
  php:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: laravel-php
    volumes:
      - ./:/var/www/html
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - DB_HOST=mysql
      - DB_DATABASE=laravel
      - DB_USERNAME=laravel
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - MAIL_MAILER=smtp
      - MAIL_HOST=mailhog
      - MAIL_PORT=1025
    depends_on:
      - mysql
      - redis
    networks:
      - laravel

  # MySQL
  mysql:
    image: mysql:8
    container_name: laravel-mysql
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - laravel

  # Redis
  redis:
    image: redis:alpine
    container_name: laravel-redis
    ports:
      - "6379:6379"
    networks:
      - laravel

  # Queue Worker
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: laravel-queue
    command: php artisan queue:work --tries=3 --timeout=90
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - laravel
    restart: unless-stopped

  # Mailhog
  mailhog:
    image: mailhog/mailhog
    container_name: laravel-mailhog
    ports:
      - "1025:1025"  # SMTP
      - "8025:8025"  # Web UI
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  mysql-data:
```

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    index index.php index.html;
    root /var/www/html/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

# Instalar dependências
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip

# Instalar as PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir o diretório de trabalho
WORKDIR /var/www/html

# Permissões
RUN chown -R www-data:www-data /var/www/html

USER www-data
```

```bash
# Iniciar o stack inteiro
docker-compose up -d

# Instalar as dependências
docker-compose exec php composer install

# Criar o .env
docker-compose exec php cp .env.example .env

# Gerar a key
docker-compose exec php php artisan key:generate

# Rodar as migrations
docker-compose exec php php artisan migrate

# Conferir a app
# http://localhost:8080

# Ver os emails
# http://localhost:8025

# Logs de todos os services
docker-compose logs -f

# Parar tudo
docker-compose down

# Parar e remover volumes
docker-compose down -v
```
</details>

### Exercício 2: Crie arquivos override para dev e prod

**Enunciado:** Você tem um docker-compose.yml base. Crie docker-compose.override.yml para dev com Xdebug e docker-compose.prod.yml para production, sem services extras.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.yml (config base)
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - laravel

  php:
    build:
      context: .
      dockerfile: Dockerfile
    networks:
      - laravel
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8
    environment:
      MYSQL_DATABASE: laravel
    networks:
      - laravel

  redis:
    image: redis:alpine
    networks:
      - laravel

networks:
  laravel:

volumes:
  mysql-data:
```

```yaml
# docker-compose.override.yml (dev — aplica sozinho)
version: '3.8'

services:
  nginx:
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html  # Bind mount para hot reload

  php:
    build:
      dockerfile: Dockerfile.dev  # Dev Dockerfile com Xdebug
    volumes:
      - ./:/var/www/html
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - XDEBUG_MODE=debug
      - XDEBUG_CONFIG=client_host=host.docker.internal

  mysql:
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"  # Acesso de fora para cliente GUI
    volumes:
      - mysql-data:/var/lib/mysql

  redis:
    ports:
      - "6379:6379"

  # Só no dev
  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - laravel

  # Só no dev
  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    environment:
      PMA_HOST: mysql
      PMA_USER: laravel
      PMA_PASSWORD: secret
    ports:
      - "8081:80"
    depends_on:
      - mysql
    networks:
      - laravel
```

```yaml
# docker-compose.prod.yml (production)
version: '3.8'

services:
  nginx:
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      # Read-only por segurança
      - ./public:/var/www/html/public:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/ssl:/etc/nginx/ssl:ro

  php:
    build:
      dockerfile: Dockerfile.prod  # Prod Dockerfile sem Xdebug
    restart: always
    volumes:
      # Só o storage (logs/cache)
      - ./storage:/var/www/html/storage
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    env_file:
      - .env.production

  mysql:
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    # Sem publicar porta (só acesso interno)

  redis:
    restart: always
    # Sem publicar porta

  # Queue worker para production
  queue:
    build:
      context: .
      dockerfile: Dockerfile.prod
    restart: always
    command: php artisan queue:work --tries=3 --timeout=90
    volumes:
      - ./storage:/var/www/html/storage
    env_file:
      - .env.production
    depends_on:
      - mysql
      - redis
    networks:
      - laravel

  # Scheduler para production
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile.prod
    restart: always
    command: sh -c "while true; do php artisan schedule:run; sleep 60; done"
    volumes:
      - ./storage:/var/www/html/storage
    env_file:
      - .env.production
    depends_on:
      - mysql
    networks:
      - laravel
```

```bash
# Development (usa o override sozinho)
docker-compose up -d

# Production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Build das images de production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build

# Ver a config final
docker-compose config

# Para production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml config
```
</details>

### Exercício 3: Escale os queue workers

**Enunciado:** Você tem uma app Laravel com filas. Configure o docker-compose para rodar vários queue workers e faça load balancing nos web servers.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.yml
version: '3.8'

services:
  # Nginx Load Balancer
  nginx-lb:
    image: nginx:alpine
    container_name: nginx-loadbalancer
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/lb.conf:/etc/nginx/nginx.conf:ro
    depends_on:
      - nginx-1
      - nginx-2
    networks:
      - laravel

  # Nginx Server 1
  nginx-1:
    image: nginx:alpine
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - laravel

  # Nginx Server 2
  nginx-2:
    image: nginx:alpine
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - laravel

  # PHP-FPM (compartilhado entre os nginx)
  php:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - laravel

  # MySQL
  mysql:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - laravel

  # Redis
  redis:
    image: redis:alpine
    networks:
      - laravel

  # Queue Worker (escalável)
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan queue:work --tries=3 --timeout=90 --sleep=3
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - laravel
    restart: unless-stopped
    # NÃO defina container_name — senão não escala

  # Horizon (alternativa a vários queue workers)
  horizon:
    build:
      context: .
      dockerfile: Dockerfile
    command: php artisan horizon
    volumes:
      - ./:/var/www/html
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
    networks:
      - laravel
    restart: unless-stopped

networks:
  laravel:

volumes:
  mysql-data:
```

```nginx
# docker/nginx/lb.conf (Load Balancer)
events {
    worker_connections 1024;
}

http {
    upstream backend {
        least_conn;  # Balanceia pela menor carga
        server nginx-1:80;
        server nginx-2:80;
    }

    server {
        listen 80;

        location / {
            proxy_pass http://backend;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        location /health {
            access_log off;
            return 200 "healthy\n";
        }
    }
}
```

```bash
# Iniciar com 3 queue workers
docker-compose up -d --scale queue=3

# Conferir se há 3 instâncias
docker-compose ps
# NAME                    COMMAND                  STATUS              PORTS
# laravel_queue_1         "php artisan queue:w…"   Up
# laravel_queue_2         "php artisan queue:w…"   Up
# laravel_queue_3         "php artisan queue:w…"   Up

# Ver os logs de todos os workers
docker-compose logs -f queue

# Escalar em tempo real (para 5 workers)
docker-compose up -d --scale queue=5 --no-recreate

# Reduzir para 2 workers
docker-compose up -d --scale queue=2 --no-recreate

# Ver o uso de recursos
docker stats

# Monitorar a queue no Redis CLI
docker-compose exec redis redis-cli
> LLEN queues:default  # Quantidade de jobs na fila

# Horizon dashboard (se você usa Horizon no lugar de queue)
# http://localhost/horizon

# Testar o load balancing
for i in {1..10}; do
  curl -s http://localhost | grep "Server:"
done
# Devem alternar nginx-1 e nginx-2
```

```yaml
# config/horizon.php (se você usa Horizon para auto-scale)
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 90,
        ],
    ],
],
```

```bash
# Alternativa: docker-compose com número fixo de workers
# docker-compose.scale.yml
version: '3.8'

services:
  queue-1:
    build: .
    command: php artisan queue:work
    networks:
      - laravel

  queue-2:
    build: .
    command: php artisan queue:work
    networks:
      - laravel

  queue-3:
    build: .
    command: php artisan queue:work
    networks:
      - laravel
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
