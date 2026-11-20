#!/bin/sh
set -e

git pull
docker compose pull --ignore-buildable   # mysql etc.
docker compose build --pull              # picks up a patched base image
docker compose up -d --remove-orphans
docker image prune -f
