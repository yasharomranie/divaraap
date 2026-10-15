#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
state_root="${DIVARAAP_ENV_ROOT:-/workspace/.environment/divaraap}"
action="${1:-start}"
mkdir -p "$state_root"
cd "$project_root"
php_run() {
    docker exec -e GIT_SSL_CAINFO=/run/ca-bundle.pem divaraap-php "$@"
}
ensure_containers() {
    docker network inspect divaraap-dev >/dev/null 2>&1 || docker network create divaraap-dev
    if docker container inspect divaraap-db >/dev/null 2>&1; then
        docker start divaraap-db >/dev/null
    else
        docker run -d --name divaraap-db --network divaraap-dev \
            -e POSTGRES_DB=openclassify -e POSTGRES_USER=openclassify -e POSTGRES_PASSWORD=secret \
            --mount type=volume,src=divaraap-pgdata,dst=/var/lib/postgresql/data \
            postgres:16-bookworm
    fi
    if docker container inspect divaraap-php >/dev/null 2>&1 &&
        [[ "$(docker inspect --format '{{.Image}}' divaraap-php)" != "$(docker image inspect --format '{{.Id}}' divaraap-php:8.4)" ]]; then
        docker rm -f divaraap-php >/dev/null
        rm -f "$state_root/web.pid" "$state_root/queue.pid" "$state_root/reverb.pid"
    fi
    if docker container inspect divaraap-php >/dev/null 2>&1; then
        docker start divaraap-php >/dev/null
    else
        docker run -d --name divaraap-php --network divaraap-dev --user "$(id -u):$(id -g)" \
            -p 127.0.0.1:8000:8000 -p 127.0.0.1:8080:8080 \
            --mount "type=bind,src=$project_root,dst=/app" \
            --mount "type=bind,src=$state_root,dst=/setup" \
            --mount type=bind,src=/etc/ssl/certs/ca-certificates.crt,dst=/run/ca-bundle.pem,readonly \
            -e COMPOSER_HOME=/setup/composer -e COMPOSER_CAFILE=/run/ca-bundle.pem \
            -e SSL_CERT_FILE=/run/ca-bundle.pem -e CURL_CA_BUNDLE=/run/ca-bundle.pem \
            divaraap-php:8.4 tail -f /dev/null
    fi
    for attempt in {1..30}; do
        if docker exec divaraap-db pg_isready -U openclassify >/dev/null; then return; fi
        sleep 1
    done
    return 1
}
initialize_env() {
    python3 - <<'PY'
from pathlib import Path
import secrets
p = Path('.env')
if not p.exists():
    overrides = {'DB_HOST': 'divaraap-db', 'FILESYSTEM_DISK': 'public', 'MEDIA_DISK': 'public', 'CLOUD_MEDIA_DISK': 'public', 'REVERB_APP_ID': 'divaraap-local', 'REVERB_APP_KEY': secrets.token_hex(16), 'REVERB_APP_SECRET': secrets.token_hex(32), 'TURNSTILE_SITE_KEY': '', 'TURNSTILE_SECRET_KEY': ''}
    result = []
    for line in Path('.env.example').read_text().splitlines():
        key, separator, value = line.partition('=')
        result.append(key + '=' + overrides.get(key, '' if key.startswith('AWS_') else value) if separator else line)
    p.write_text('\n'.join(result) + '\n')
    p.chmod(0o600)
PY
}
case "$action" in
    install)
        BUILDX_CONFIG=/workspace/.cache/buildx docker build \
            --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
            -t divaraap-php:8.4 docs/environment
        cp docs/environment/phpunit.xml "$state_root/phpunit.xml"
        initialize_env
        ensure_containers
        php_run composer install --no-interaction --prefer-source
        if grep -q '^APP_KEY=$' .env; then php_run php artisan key:generate --force; fi
        if [[ -f package-lock.json ]]; then
            npm --cache /workspace/.cache/npm ci --no-audit --no-fund
        else
            npm --cache /workspace/.cache/npm install --no-audit --no-fund
        fi
        npm run build
        php_run php artisan migrate --force
        ;;
    seed)
        ensure_containers
        php_run php artisan db:seed --force
        ;;
    test)
        ensure_containers
        if [[ "$(docker exec divaraap-db psql -U openclassify -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='openclassify_testing'")" != 1 ]]; then
            docker exec divaraap-db createdb -U openclassify openclassify_testing
        fi
        cp docs/environment/phpunit.xml "$state_root/phpunit.xml"
        docker exec -e APP_ENV=testing -e DB_DATABASE=openclassify_testing -e PUBLIC_STORAGE_ROOT=/setup/testing-public divaraap-php php artisan migrate --force
        if [[ "$(docker exec divaraap-db psql -U openclassify -d openclassify_testing -tAc 'SELECT count(*) FROM users')" == 0 ]]; then
            docker exec -e APP_ENV=testing -e DB_DATABASE=openclassify_testing -e PUBLIC_STORAGE_ROOT=/setup/testing-public divaraap-php php artisan db:seed --force
        fi
        php_run php vendor/bin/phpunit --configuration=/setup/phpunit.xml
        ;;
    start)
        if [[ ! -f vendor/autoload.php ]]; then
            printf '%s\n' 'PHP dependencies are incomplete; finish install before starting services.' >&2
            exit 1
        fi
        ensure_containers
        for service in web queue reverb; do
            if [[ -f "$state_root/$service.pid" ]] && docker exec divaraap-php php -r '$pid = trim(file_get_contents("/setup/".$argv[1].".pid")); $status = @file_get_contents("/proc/".$pid."/status"); exit($status && preg_match("/^State:\s+Z/m", $status) !== 1 ? 0 : 1);' "$service" 2>/dev/null; then continue; fi
            case "$service" in
                web) command='php artisan serve --host=0.0.0.0 --port=8000' ;;
                queue) command='php artisan queue:work --tries=1 --timeout=60' ;;
                reverb) command='php artisan reverb:start --host=0.0.0.0 --port=8080' ;;
            esac
            docker exec divaraap-php sh -c "nohup $command >/setup/$service.log 2>&1 </dev/null & echo \$! >/setup/$service.pid"
        done
        ;;
    php)
        shift
        php_run "$@"
        ;;
    *) exit 2 ;;
esac
