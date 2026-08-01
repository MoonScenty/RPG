# 배포 가이드 (RPG BackEnd)

Laravel API를 `rpg.moonscenty.me` 도메인으로 라이브 서비스하기 위한 절차. SSH 접속 → 프로젝트 배치 → 폴더 퍼미션 → Nginx 설정 순서로 정리했다.

프론트엔드(SPA)와 백엔드(API)를 **같은 도메인**(`rpg.moonscenty.me`), **같은 문서 루트**에서 서빙한다 — `routes/web.php`가 이미 그렇게 짜여 있다: 프론트 빌드 결과물(`index.html` + `assets/`)을 Laravel의 `public/` 폴더 안에 그대로 넣어두면, `api`/`sanctum`으로 시작하지 않는 모든 경로 요청에 Laravel이 그 `index.html`을 돌려준다(Vue Router 히스토리 모드 지원). 그래서 Nginx는 **표준 Laravel 사이트 설정 하나**만 있으면 되고, 프론트용 별도 문서 루트나 `/api` 경로 분기 같은 걸 따로 만들 필요가 없다. 같은 origin이라 Sanctum 인증에 크로스 오리진 쿠키 설정(`SameSite=None` 등)도 필요 없다.

---

## 0. 사전 준비물

- 서버: Ubuntu 22.04+ 기준 (다른 배포판이면 패키지 매니저만 바꿔서 적용)
- PHP 8.2+, Composer, MySQL 8+, Nginx, Certbot
- 로컬 머신에서 서버로 SSH 접속 가능한 상태(비밀번호 로그인이 아니라 키 기반)

---

## 1. SSH 접속 준비

키를 새로 발급하는 절차 자체는 따로 필요 없다 — 대부분 아래 둘 중 하나로 이미 끝나 있다.

- **호스팅 콘솔에서 서버(VM) 생성 시 공개키 등록**: AWS/DigitalOcean/Vultr 등 대부분의 호스팅은 서버 생성 화면에서 로컬 공개키(`~/.ssh/id_ed25519.pub` 등)를 붙여넣으면 서버가 뜰 때 자동으로 `authorized_keys`에 들어간다. 이 경우 바로 `ssh <유저>@<서버 IP>`로 접속하면 끝.
- **로컬에 키가 아예 없으면** 한 번만 만들고 `ssh-copy-id`로 등록:

```bash
ssh-keygen -t ed25519 -C "rpg-BackEnd-deploy"   # 기본 경로(~/.ssh/id_ed25519)로 생성
ssh-copy-id <유저>@<서버 IP>                      # 비밀번호로 한 번 접속해서 공개키를 서버에 등록
ssh <유저>@<서버 IP>                              # 이후로는 키로 바로 접속됨
```

이후 명령어들은 이렇게 접속한 서버 쉘에서 실행한다 (접속 유저는 `ubuntu`, 홈 디렉토리는 `/home/ubuntu/RPG` 기준).

---

## 2. DNS 설정

도메인 관리 콘솔(moonscenty.me)에서 A 레코드 등록 (프론트/백엔드가 같은 도메인이라 레코드 하나면 됨):

| 타입 | 호스트 | 값 |
| --- | --- | --- |
| A | `rpg` (→ `rpg.moonscenty.me`) | 서버 공인 IP |

전파 확인: `dig rpg.moonscenty.me +short`

---

## 3. 서버 패키지 설치

```bash
sudo apt update
sudo apt install -y nginx mysql-server certbot python3-certbot-nginx git unzip \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

MySQL 계정/DB 생성:

```sql
CREATE DATABASE rpg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rpg_app'@'localhost' IDENTIFIED BY '<강력한 비밀번호>';
GRANT ALL PRIVILEGES ON rpg.* TO 'rpg_app'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. 프로젝트 배치

```bash
cd ~
git clone git@github.com:MoonScenty/RPG.git   # ~/RPG 로 생성됨
cd RPG/BackEnd

composer install --no-dev --optimize-autoloader
```

프론트를 빌드한다 — `vite.config.ts`의 `outDir`가 `../BackEnd/public`으로 지정돼 있어서 `npm run build`만 하면 Laravel의 `public/`(`index.php`/`.htaccess` 옆) 안에 바로 빌드된다. 별도 복사 단계가 필요 없다 (`emptyOutDir: false`라 Laravel 파일도 안 지워짐):

```bash
cd ../FrontEnd
npm ci
npm run build   # 결과물이 ../BackEnd/public/ 안에 바로 들어감
cd ../BackEnd
```

Node가 서버에 없다면 로컬/CI에서 `npm run build`까지 돌린 뒤 `BackEnd/public/`(빌드 결과물만, `index.php` 등 라라벨 파일은 건드리지 말고) 내용을 `rsync`/`scp`로 올려도 된다.

---

## 5. `.env` 설정

```bash
cp .env.example .env
php artisan key:generate
```

`.env`에서 아래 값들을 운영 환경에 맞게 수정:

```env
APP_NAME="RPG"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rpg.moonscenty.me

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rpg
DB_USERNAME=rpg_app
DB_PASSWORD=<강력한 비밀번호>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=rpg.moonscenty.me

# 프론트/백엔드가 같은 origin이므로 값도 동일하게
SANCTUM_STATEFUL_DOMAINS=rpg.moonscenty.me
CORS_ALLOWED_ORIGINS=https://rpg.moonscenty.me
```

- 프론트(`/`)와 API(`/api`, `/sanctum`)가 같은 origin(`https://rpg.moonscenty.me`)에서 나가므로 브라우저 입장에서는 크로스 오리진 요청이 아니다. 그래서 `SESSION_SAME_SITE`를 서브도메인 분리 구성 때 쓰던 `none` 대신 더 안전한 기본값 `lax`로 둘 수 있다. `SESSION_SECURE_COOKIE=true`는 그대로 유지(HTTPS 필수 — HTTP로 열면 쿠키 저장 자체가 안 된다).
- `CORS_ALLOWED_ORIGINS`/`SANCTUM_STATEFUL_DOMAINS`는 같은 origin이면 사실상 필수는 아니지만, 나중에 앱(모바일)이나 별도 도구가 API를 호출할 경우를 대비해 명시해둔다.

마이그레이션:

```bash
php artisan migrate --force
```

캐시 빌드(운영 환경에서는 항상 캐시를 켠다):

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

- `view:cache`는 안 쓴다 — 이 프로젝트는 API + 정적 SPA 서빙(프론트가 `index.html`을 직접 반환)만 있고 Blade 뷰(`resources/views`) 자체가 없어서, 돌리면 `The "resources/views" directory does not exist` 에러만 난다.

---

## 6. 폴더 퍼미션

Laravel은 `storage/`와 `bootstrap/cache/`에 웹서버 프로세스가 직접 쓰기 권한을 가져야 한다(로그, 세션 파일, 캐시, 컴파일된 뷰 등). 나머지 코드 디렉토리는 배포 계정 소유로 두고 쓰기 권한을 주지 않는 게 안전하다.

```bash
cd ~/RPG/BackEnd

# php-fpm이 어느 유저로 도는지 먼저 확인 (보통 www-data)
grep '^user' /etc/php/8.3/fpm/pool.d/www.conf

# 프로젝트 전체는 배포 계정 소유, 그룹만 www-data로 맞춰서 php-fpm이 읽을 수 있게
sudo chown -R ubuntu:www-data ~/RPG
sudo find ~/RPG -type d -exec chmod 750 {} \;
sudo find ~/RPG -type f -exec chmod 640 {} \;

# storage/, bootstrap/cache/만 php-fpm이 쓸 수 있도록 그룹 쓰기 권한 + setgid
sudo chmod -R 775 storage bootstrap/cache
sudo chgrp -R www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
```

- `artisan` 파일은 실행 권한이 있어야 한다: `chmod +x artisan` (git clone 시 보통 유지되지만 확인).
- `storage/logs/laravel.log`가 안 생기거나 500 에러 화면에 `Permission denied` 계열 문구가 뜨면 십중팔구 이 단계 문제다. `tail -f storage/logs/laravel.log`로 원인 확인.
- 배포 스크립트를 만들 때는 매 배포마다 `storage`/`bootstrap/cache` 권한을 재적용하는 단계를 꼭 넣는다(코드가 git으로 새로 받아지면서 권한이 배포 계정 것으로 돌아오는 경우가 있다).
- **홈 디렉토리 안에 둘 때 특히 주의**: nginx(www-data)가 `/home/ubuntu/RPG/BackEnd/public`까지 도달하려면 그 경로의 모든 상위 디렉토리에 탐색 권한(실행 비트)이 있어야 한다. 리눅스 기본값상 홈 디렉토리 자체(`/home/ubuntu`)가 `750`(다른 그룹은 진입 불가)으로 잠겨있는 경우가 많아서, 이것만 따로 열어줘야 한다:

```bash
chmod o+x /home/ubuntu   # 또는 chmod 711 /home/ubuntu — www-data가 지나갈 수만 있으면 됨
```

이 단계를 빼먹으면 nginx가 파일 자체는 읽을 수 있는데도 `/home/ubuntu`를 못 지나가서 403이 난다(`/var/www` 아래에 두면 이 문제가 원래 없다 — `/var/www`는 보통 `755`).

---

## 7. PHP-FPM 소켓 확인

```bash
sudo systemctl status php8.3-fpm
ls -l /run/php/php8.3-fpm.sock   # nginx 설정에서 이 경로를 그대로 씀
```

---

## 8. Nginx 설정

프론트 빌드 결과물이 이미 `BackEnd/public/`(4번 단계) 안에 들어가 있으므로, Nginx는 표준 Laravel 사이트 설정 그대로 쓰면 된다 — `/api`, `/sanctum` 같은 경로 분기를 nginx에서 따로 할 필요가 없다(그 판단은 `routes/web.php`가 이미 하고 있다: api/sanctum이 아닌 요청엔 `index.html`을 돌려줌).

`/etc/nginx/sites-available/rpg.moonscenty.me`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name rpg.moonscenty.me;

    root /home/ubuntu/RPG/BackEnd/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
    }

    # .env, .git 등 숨김/비공개 파일 접근 차단
    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/rpg.moonscenty.me.access.log;
    error_log  /var/log/nginx/rpg.moonscenty.me.error.log;
}
```

- `try_files $uri $uri/ /index.php?$query_string;` 하나로 끝난다: 실제 파일(프론트의 `assets/*.js`, `*.css`, favicon 등)이 있으면 그대로 서빙하고, 없으면 전부 Laravel(`index.php`)로 넘어간다. 거기서 `/api/*`·`/sanctum/*`은 라라벨 라우터가 API로 처리하고, 나머지는 `routes/web.php`의 캐치올이 프론트 `index.html`을 돌려준다(Vue Router 히스토리 모드 새로고침도 이걸로 커버됨).
- 프론트를 다시 빌드해서 배포할 때도 이 nginx 설정은 그대로 두고 `public/`에 새 빌드 결과물만 덮어쓰면 된다.

활성화:

```bash
sudo ln -s /etc/nginx/sites-available/rpg.moonscenty.me /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9. HTTPS 발급 (Let's Encrypt)

```bash
sudo certbot --nginx -d rpg.moonscenty.me
```

certbot이 nginx 설정에 `listen 443 ssl`과 인증서 경로, 80→443 리다이렉트를 자동으로 추가해준다(프론트/백엔드가 한 서버 블록이라 인증서도 한 번만 받으면 됨). 자동 갱신은 certbot 설치 시 등록되는 systemd timer(`certbot.timer`)가 처리하므로 별도 cron 없이도 된다 (`sudo systemctl status certbot.timer`로 확인).

---

## 10. 방화벽

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'   # 80, 443
sudo ufw enable
sudo ufw status
```

MySQL(3306)은 로컬(`127.0.0.1`)에서만 접속하므로 외부에 열지 않는다.

---

## 11. 배포 확인

```bash
curl -I https://rpg.moonscenty.me/api/health   # 헬스체크 라우트가 있다면
```

브라우저에서 `https://rpg.moonscenty.me`로 접속해 프론트가 뜨는지, 로그인이 419 없이 되는지 확인 — 안 되면 대부분 `public/`에 프론트 빌드 결과물이 안 들어갔거나(4번 단계), `SESSION_SAME_SITE`/`SESSION_DOMAIN`이 잘못된 경우다.

---

## 12. 이후 재배포 체크리스트

```bash
cd ~/RPG
git pull
cd BackEnd
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache

# 프론트도 바뀌었으면 다시 빌드해서 public/에 덮어쓰기
cd ../FrontEnd && npm ci && npm run build && cd ../BackEnd   # 빌드 결과물이 public/에 바로 들어감

sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo systemctl reload php8.3-fpm
```

`.env`가 바뀌었다면 `config:cache`를 다시 돌려야 반영된다(캐시된 상태로는 `.env` 수정이 즉시 적용되지 않음).
