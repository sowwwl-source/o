#!/usr/bin/env bash

set -euo pipefail

token=${CLOUDFLARED_TUNNEL_TOKEN:-}
token_file=${CLOUDFLARED_TUNNEL_TOKEN_FILE:-}
loglevel=${CLOUDFLARED_LOGLEVEL:-info}

if [[ -n "$token_file" && "$token_file" != CHANGE_ME_* ]]; then
	if [[ ! -s "$token_file" ]]; then
		echo "Missing token file: $token_file" >&2
		exit 1
	fi

	exec /usr/bin/cloudflared tunnel --loglevel "$loglevel" run --token-file "$token_file"
fi

if [[ -z "$token" || "$token" == CHANGE_ME_* ]]; then
	echo "Missing CLOUDFLARED_TUNNEL_TOKEN or CLOUDFLARED_TUNNEL_TOKEN_FILE." >&2
	exit 1
fi

exec /usr/bin/cloudflared tunnel --loglevel "$loglevel" run --token "$token"
