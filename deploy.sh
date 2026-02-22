#!/bin/bash
# Pull the latest code
git pull origin main

chown -R www-data:www-data /var/www/html/

# Rebuild the image (fast thanks to Docker layer caching)
docker compose build

# Update containers (Docker will only restart what changed)
# --remove-orphans cleans up old services if you rename them
docker compose up -d --remove-orphans
