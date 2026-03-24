# app-visiting-rocada — Деплой на Timeweb VPS

> **ОС сервера:** Ubuntu 22.04 LTS  
> **Стек:** Node.js 20+, Nginx, HTTPS (Let's Encrypt)

---

## 1. Подготовка сервера

```bash
# Обновить пакеты
sudo apt update && sudo apt upgrade -y

# Установить Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Проверить версии
node -v   # v20.x.x
npm -v    # 10.x.x

# Установить Nginx
sudo apt install -y nginx

# Установить certbot (HTTPS обязателен — без него геолокация не работает)
sudo apt install -y certbot python3-certbot-nginx
```

---

## 2. Клонирование и сборка

```bash
# Клонировать репозиторий
git clone <URL_РЕПОЗИТОРИЯ> /var/www/rocada-pwa
cd /var/www/rocada-pwa

# Установить зависимости
npm install

# Собрать production-билд
npm run build
```

После сборки появится папка `dist/` — готовое приложение.

---

## 3. Настройка Nginx

```bash
sudo nano /etc/nginx/sites-available/rocada-pwa
```

Вставить (заменить `your-domain.ru` и `office.rocadatech.ru` на свои значения):

```nginx
server {
    listen 80;
    server_name your-domain.ru;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.ru;

    # SSL — certbot заполнит эти строки автоматически
    ssl_certificate     /etc/letsencrypt/live/your-domain.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.ru/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    root  /var/www/rocada-pwa/dist;
    index index.html;

    # ── Прокси /b24api → Bitrix24 модуль ──────────────────────────────
    # Аналог vite proxy: /b24api/visits?page=1
    #   → /local/modules/rocada.visits/lib/router.php?route=visits&page=1
    location /b24api/ {
        rewrite ^/b24api/([^?]*)$        /local/modules/rocada.visits/lib/router.php?route=$1       break;
        rewrite ^/b24api/([^?]*)\?(.*)$  /local/modules/rocada.visits/lib/router.php?route=$1&$2    break;

        proxy_pass          https://office.rocadatech.ru;
        proxy_set_header    Host office.rocadatech.ru;
        proxy_set_header    X-Real-IP $remote_addr;
        proxy_ssl_server_name on;
        proxy_ssl_verify    off;
    }

    # ── Прокси /b24oauth → oauth.bitrix.info ──────────────────────────
    # /b24oauth/token → /oauth/token
    location /b24oauth/ {
        rewrite ^/b24oauth/(.*)$ /oauth/$1 break;
        proxy_pass          https://oauth.bitrix.info;
        proxy_set_header    Host oauth.bitrix.info;
        proxy_ssl_server_name on;
        proxy_ssl_verify    off;
    }

    # ── Прокси /b24rest → REST API Bitrix24 ───────────────────────────
    # /b24rest/user.current → /rest/user.current
    location /b24rest/ {
        rewrite ^/b24rest/(.*)$ /rest/$1 break;
        proxy_pass          https://office.rocadatech.ru;
        proxy_set_header    Host office.rocadatech.ru;
        proxy_ssl_server_name on;
        proxy_ssl_verify    off;
    }

    # ── SPA-роутинг (Vue Router history mode) ─────────────────────────
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Кэш статики
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 4. Активация сайта

```bash
# Включить сайт
sudo ln -s /etc/nginx/sites-available/rocada-pwa /etc/nginx/sites-enabled/

# Проверить конфиг на ошибки — ОБЯЗАТЕЛЬНО перед reload
sudo nginx -t

# Перезапустить Nginx
sudo systemctl reload nginx
```

---

## 5. HTTPS-сертификат

> Домен должен уже указывать на IP сервера!

```bash
sudo certbot --nginx -d your-domain.ru

# Certbot сам обновит конфиг Nginx
# Проверить автообновление сертификата
sudo systemctl status certbot.timer
```

---

## 6. Обновление приложения

```bash
cd /var/www/rocada-pwa
git pull
npm install       # если изменились зависимости
npm run build     # пересобрать dist/
# Nginx перезапускать не нужно
```

---

## 7. Чеклист после деплоя

- [ ] `https://your-domain.ru` → открывается страница входа
- [ ] `https://your-domain.ru/b24api/ping` → возвращает JSON, а не 404/HTML
- [ ] При обновлении страницы на `/visits/123` → не 404 (nginx `try_files` работает)
- [ ] Кнопка геолокации в приложении → не блокируется браузером (HTTPS есть)

---

## Структура на сервере

```
/var/www/rocada-pwa/
├── dist/           ← сюда смотрит Nginx (production build)
│   ├── index.html
│   └── assets/
├── src/            ← исходники (не нужны для работы)
├── package.json
└── vite.config.js
```

---

## Примечания

> **`proxy_ssl_verify off`** — нужно если у Bitrix24-портала самоподписанный SSL. Если сертификат от нормального CA — уберите эту строку.

> **Node.js на продакшене не нужен.** Nginx отдаёт готовую статику из `dist/`, никакой `npm run dev` или `node` процесс не запускается.

> **HTTPS обязателен** — браузеры разрешают `navigator.geolocation` только на защищённых соединениях.