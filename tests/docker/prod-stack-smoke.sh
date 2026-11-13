#!/usr/bin/env bash
#
# Smoke test of the production stack (.docker/prod/compose.yaml)
#
# Starts the whole stack (web, php, cron, database) under a throwaway
# compose project, plain HTTP on a random loopback port, with a fresh
# database and generated configuration in a temporary directory. Everything
# (containers, volumes, network, files) is removed on exit; nothing outside
# that project is touched.
#
# Usage: tests/docker/prod-stack-smoke.sh
#   Images are built from the working tree, unless APP_IMAGE, WEB_IMAGE and
#   APP_VERSION designate already built images (CI).
#   The generated configuration is bind-mounted: TMPDIR must be visible to
#   the Docker daemon (not a private /tmp).
#
set -uo pipefail

readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly COMPOSE_FILE="${PROJECT_DIR}/.docker/prod/compose.yaml"

PASSED=0
FAILED=0

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
check() { # check <description> <command...>
    local description="$1"; shift
    if "$@" >/dev/null 2>&1; then pass "${description}"; else fail "${description}"; fi
}
not() { ! "$@"; }

WORK_DIR="$(mktemp -d)"
readonly WORK_DIR
readonly PROJECT="gcsmoke$$"
readonly HTTP_PORT="$((20000 + RANDOM % 20000))"
readonly BASE_URL="http://127.0.0.1:${HTTP_PORT}"

export APP_IMAGE="${APP_IMAGE:-gestion-compte-smoke}"
export WEB_IMAGE="${WEB_IMAGE:-gestion-compte-smoke-web}"
export APP_VERSION="${APP_VERSION:-local}"

dc() { docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${WORK_DIR}/stack.env" "$@"; }

cleanup() {
    dc down --volumes --remove-orphans >/dev/null 2>&1
    rm -f "${WORK_DIR}/stack.env" "${WORK_DIR}/app.env" "${WORK_DIR}/body"
    rmdir "${WORK_DIR}"
}
trap cleanup EXIT

db_password="$(head -c 18 /dev/urandom | base64 | tr -d '+/=')"

cat > "${WORK_DIR}/stack.env" <<EOF
APP_VERSION=${APP_VERSION}
APP_ENV_FILE=${WORK_DIR}/app.env
SITE_ADDRESS=:80
HTTP_PUBLISH=127.0.0.1:${HTTP_PORT}
HTTPS_PUBLISH=127.0.0.1:$((HTTP_PORT + 1))
MARIADB_DATABASE=gestion_compte
MARIADB_USER=gestion_compte
MARIADB_PASSWORD=${db_password}
MARIADB_ROOT_PASSWORD=${db_password}root
EOF

# The application configuration: the template, switched to production
sed -e 's/^APP_ENV=.*/APP_ENV=prod/' \
    -e "s/^APP_SECRET=.*/APP_SECRET=${db_password}secret/" \
    -e "s|^DATABASE_URL=.*|DATABASE_URL=\"mysql://gestion_compte:${db_password}@database:3306/gestion_compte?serverVersion=mariadb-10.11.19\&charset=utf8mb4\"|" \
    -e 's|^MAILER_DSN=.*|MAILER_DSN=null://null|' \
    "${PROJECT_DIR}/.env.dist" > "${WORK_DIR}/app.env"
chmod 0644 "${WORK_DIR}/app.env"

printf '\nprod stack (project %s, %s)\n' "${PROJECT}" "${BASE_URL}"

if [ "${APP_IMAGE}" = gestion-compte-smoke ]; then
    printf '  building images...\n'
    if ! dc build >"${WORK_DIR}/body" 2>&1; then
        tail -n 40 "${WORK_DIR}/body"
        fail "the prod and web images build"
        exit 1
    fi
fi

if ! dc up --detach --wait --wait-timeout 300 >"${WORK_DIR}/body" 2>&1; then
    tail -n 20 "${WORK_DIR}/body"
    dc logs --tail 40
    fail "the stack starts and becomes healthy"
    exit 1
fi
pass "the stack starts and becomes healthy"

# --- Image -------------------------------------------------------------------

check "PHP is the patched 7.4.33" \
    dc exec -T php php -r 'exit(PHP_VERSION === "7.4.33" ? 0 : 1);'
check "php-fpm carries the CVE-2026-109618/109619 fix" \
    dc exec -T php grep -q zend_new_array_singleton /usr/local/sbin/php-fpm
check "session upload progress is disabled" \
    dc exec -T php php -r 'exit(ini_get("session.upload_progress.enabled") ? 1 : 0);'
check "php runs as www-data" \
    test "$(dc exec -T php id -un)" = www-data
check "cron runs as www-data" \
    test "$(dc exec -T cron id -un)" = www-data
check "the image refuses to start without the instance's .env" \
    not docker run --rm "${APP_IMAGE}:${APP_VERSION}" true

# --- Network exposure --------------------------------------------------------

# The images EXPOSE their ports: what matters is a binding on the host
published() { dc ps --format json "$1" | jq -r '.Publishers[]? | select(.PublishedPort != 0) | .TargetPort'; }
check "php-fpm publishes no port" test -z "$(published php)"
check "the database publishes no port" test -z "$(published database)"
check "cron publishes no port" test -z "$(published cron)"

# --- Database ----------------------------------------------------------------

if dc exec -T php php bin/console doctrine:migrations:migrate --no-interaction >"${WORK_DIR}/body" 2>&1; then
    pass "migrations run against MariaDB 10.11"
else
    tail -n 20 "${WORK_DIR}/body"
    fail "migrations run against MariaDB 10.11"
fi
check "the Doctrine mapping is valid" \
    dc exec -T php php bin/console doctrine:schema:validate --skip-sync

# --- HTTP --------------------------------------------------------------------

status() { curl -s -o "${WORK_DIR}/body" -w '%{http_code}' "${BASE_URL}$1"; }

check "the login page answers 200" test "$(status /login)" = 200
check "build assets are served by Caddy" test "$(status /build/manifest.json)" = 200
check "build assets are cached as immutable" \
    sh -c "curl -sI '${BASE_URL}/build/manifest.json' | grep -qi 'cache-control: .*immutable'"

dc exec -T php sh -c 'printf "uploaded" > public/uploads/event/smoke.txt'
dc exec -T php sh -c 'printf "<?php echo \"EXEC\" . \"UTED\";" > public/uploads/event/smoke.php'

check "an uploaded file is served from the shared volume" \
    sh -c "test \"\$(curl -s '${BASE_URL}/uploads/event/smoke.txt')\" = uploaded"
check "an uploaded PHP file is never executed" \
    sh -c "! curl -s '${BASE_URL}/uploads/event/smoke.php' | grep -q EXECUTED"
check "an uploaded PHP file is never executed (path info)" \
    sh -c "! curl -s '${BASE_URL}/uploads/event/smoke.php/x' | grep -q EXECUTED"
check "an uploaded PHP file is not served as source" \
    sh -c "! curl -s '${BASE_URL}/uploads/event/smoke.php' | grep -q '<?php'"
check "dotfiles are not served" test "$(status /uploads/.htaccess)" != 200
check "the front controller source is not served" \
    sh -c "! curl -s '${BASE_URL}/index.php' | grep -q '<?php'"

# --- Cron --------------------------------------------------------------------

check "supercronic accepts the crontab" \
    dc exec -T cron supercronic -test /etc/supercronic/crontab
# Scheduling is only logged at debug level: a short debug run lists the jobs
check "supercronic schedules the 6 jobs" \
    test "$(dc exec -T cron sh -c 'timeout 3 supercronic -debug /etc/supercronic/crontab 2>&1' | grep -c 'job will run next')" -eq 6
check "a scheduled command runs (date arithmetic included)" \
    dc exec -T cron sh -c 'php bin/console app:shift:generate "$(date -d "+27 days" +%Y-%m-%d)"'

# --- Persistence -------------------------------------------------------------

dc exec -T php sh -c 'mkdir -p var/sessions/prod && printf x > var/sessions/prod/sess_smoke'
dc up --detach --wait --force-recreate php >/dev/null 2>&1
check "sessions survive a php container recreation" \
    dc exec -T php test -f var/sessions/prod/sess_smoke

printf '\n%d passed, %d failed\n' "${PASSED}" "${FAILED}"
[ "${FAILED}" -eq 0 ]
