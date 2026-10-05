#!/bin/sh
set -eu

project_path=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
env_path="$project_path/.env.docker"

if [ -e "$env_path" ]; then
    printf '%s\n' '.env.docker já existe; configurações preservadas.'
    exit 0
fi

command -v openssl >/dev/null 2>&1 || {
    printf '%s\n' 'Instale o OpenSSL para gerar a chave e as senhas.' >&2
    exit 1
}

app_key=$(openssl rand -base64 32)
db_secret=$(openssl rand -base64 32)
root_secret=$(openssl rand -base64 32)

# Restringe as credenciais ao usuário e evita sobrescrever um arquivo existente.
(
    umask 077
    set -C
    printf '%s\n' 'HTTP_PORT=8080' "APP_KEY=base64:$app_key" \
        "DB_PASSWORD=$db_secret" "MYSQL_ROOT_PASSWORD=$root_secret" > "$env_path"
)

printf '%s\n' '.env.docker criado com chave e senhas aleatórias. Não versione esse arquivo.'
