#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
cd "$repo_root"

port=${PORT:-18765}
host_addr=127.0.0.1
server_log=$(mktemp)
router_file=$(mktemp)

cleanup() {
    if [[ -n "${server_pid:-}" ]] && kill -0 "$server_pid" >/dev/null 2>&1; then
        kill "$server_pid" >/dev/null 2>&1 || true
        wait "$server_pid" >/dev/null 2>&1 || true
    fi
    rm -f "$server_log" "$router_file"
}
trap cleanup EXIT

cat >"$router_file" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($uri) && $uri !== '' ? $uri : '/';
$host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$file = $root . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/') {
    require $host === '0wlslw0.com' ? $root . '/0wlslw0.php' : $root . '/index.php';
    return true;
}

$routes = [
    '/rejoindre' => '/rejoindre.php',
    '/0wlslw0' => '/0wlslw0.php',
    '/0wlslw0/voice' => '/0wlslw0_voice.php',
];

if (isset($routes[$path])) {
    require $root . $routes[$path];
    return true;
}

$candidate = $root . $path . '.php';
if (is_file($candidate)) {
    require $candidate;
    return true;
}

http_response_code(404);
echo 'not-found';
PHP

SOWWWL_PUBLIC_FORM_TOKEN_SECRET=local-public-token-secret \
SOWWWL_MAGIC_LINK_SECRET=local-public-token-secret \
php -S "$host_addr:$port" "$router_file" >"$server_log" 2>&1 &
server_pid=$!

for _ in $(seq 1 30); do
    if curl -fsS "http://$host_addr:$port/" >/dev/null 2>&1; then
        break
    fi
    sleep 0.2
done

if ! kill -0 "$server_pid" >/dev/null 2>&1; then
    echo "Local PHP server failed to start" >&2
    cat "$server_log" >&2 || true
    exit 1
fi

request() {
    local host=${1:?Missing host}
    local path=${2:?Missing path}
    local header_file=${3:?Missing header file}
    local body_file=${4:?Missing body file}
    curl -sS -D "$header_file" -o "$body_file" -H "Host: $host" "http://$host_addr:$port$path"
}

assert_status_code() {
    local header_file=${1:?Missing header file}
    local expected=${2:?Missing status}
    local status
    status=$(awk 'toupper($1) ~ /^HTTP/ { print $2; exit }' "$header_file")
    if [[ "$status" != "$expected" ]]; then
        echo "Expected HTTP $expected, got ${status:-none}" >&2
        cat "$header_file" >&2
        exit 1
    fi
}

assert_header_contains() {
    local header_file=${1:?Missing header file}
    local header_name=${2:?Missing header name}
    local pattern=${3:?Missing pattern}
    if ! grep -Eiq "^${header_name}:[[:space:]]*${pattern}" "$header_file"; then
        echo "Missing expected header $header_name matching $pattern" >&2
        cat "$header_file" >&2
        exit 1
    fi
}

assert_header_absent() {
    local header_file=${1:?Missing header file}
    local header_name=${2:?Missing header name}
    if grep -Eiq "^${header_name}:" "$header_file"; then
        echo "Unexpected header $header_name" >&2
        cat "$header_file" >&2
        exit 1
    fi
}

assert_body_contains() {
    local body_file=${1:?Missing body file}
    local pattern=${2:?Missing pattern}
    if ! grep -Eq "$pattern" "$body_file"; then
        echo "Missing expected body pattern: $pattern" >&2
        sed -n '1,160p' "$body_file" >&2
        exit 1
    fi
}

home_headers=$(mktemp)
home_body=$(mktemp)
login_headers=$(mktemp)
login_body=$(mktemp)
join_headers=$(mktemp)
join_body=$(mktemp)
guide_headers=$(mktemp)
guide_body=$(mktemp)
voice_headers=$(mktemp)
voice_body=$(mktemp)
trap 'rm -f "$home_headers" "$home_body" "$login_headers" "$login_body" "$join_headers" "$join_body" "$guide_headers" "$guide_body" "$voice_headers" "$voice_body"; cleanup' EXIT

request sowwwl.com / "$home_headers" "$home_body"
assert_status_code "$home_headers" 200
assert_header_contains "$home_headers" 'Cache-Control' 'public, max-age='
assert_header_absent "$home_headers" 'Set-Cookie'
assert_body_contains "$home_body" 'Trois portes|ouvrir la connexion|Passer par 0wlslw0'

request sowwwl.com '/?connexion=1' "$login_headers" "$login_body"
assert_status_code "$login_headers" 200
assert_header_absent "$login_headers" 'Set-Cookie'
assert_body_contains "$login_body" 'connection-meter__form'
assert_body_contains "$login_body" 'name="csrf_token" value="[^"]+"'

request sowwwl.com '/rejoindre?username=nox&step=6' "$join_headers" "$join_body"
assert_status_code "$join_headers" 200
assert_header_absent "$join_headers" 'Set-Cookie'
assert_body_contains "$join_body" 'name="csrf_token" value="[^"]+"'
assert_body_contains "$join_body" 'name="password"'

request 0wlslw0.com / "$guide_headers" "$guide_body"
assert_status_code "$guide_headers" 200
assert_header_absent "$guide_headers" 'Set-Cookie'
assert_body_contains "$guide_body" 'data-guide-voice-csrf="[^"]+"'
assert_body_contains "$guide_body" 'Parler à 0wlslw0'

guide_token=$(python3 - <<'PY' "$guide_body"
import re, sys
body = open(sys.argv[1], 'r', encoding='utf-8').read()
match = re.search(r'data-guide-voice-csrf="([^"]+)"', body)
print(match.group(1) if match else '')
PY
)

if [[ -z "$guide_token" ]]; then
    echo "Could not extract guide voice token" >&2
    sed -n '1,160p' "$guide_body" >&2
    exit 1
fi

curl -sS \
    -D "$voice_headers" \
    -o "$voice_body" \
    -H 'Host: 0wlslw0.com' \
    -H 'Content-Type: application/json' \
    -X POST \
    --data "{\"csrf_token\":\"$guide_token\",\"utterance\":\"\"}" \
    "http://$host_addr:$port/0wlslw0/voice"

assert_status_code "$voice_headers" 422
assert_header_absent "$voice_headers" 'Set-Cookie'
assert_body_contains "$voice_body" '"error":"empty_utterance"'

echo "public-stateless-contract-ok"
