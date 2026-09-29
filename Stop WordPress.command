#!/bin/zsh
cd "$(dirname "$0")"
docker compose --env-file .local.env stop
