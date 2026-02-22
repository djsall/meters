#!/bin/bash
# Pull the latest code
git pull

# Rebuild the image (fast thanks to Docker layer caching)
docker compose build

# Update containers (Docker will only restart what changed)
# --remove-orphans cleans up old services if you rename them
docker compose up -d --build
