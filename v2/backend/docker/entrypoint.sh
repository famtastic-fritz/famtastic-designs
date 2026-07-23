#!/bin/sh
# FAMtastic Designs v2 — backend container entrypoint (Phase 2 scaffold).
#
# Runs both php-fpm (background, unix socket) and nginx (foreground PID 1)
# inside one image. The whole container executes as www-data; nginx carries
# cap_net_bind_service so it may bind port 80 without root.
set -e

# Socket/pid directory (best-effort: created and chowned in the Dockerfile;
# recreated here in case the runtime mounted a fresh tmpfs over /run).
mkdir -p /run/php-fpm 2>/dev/null || true

php-fpm --nodaemonize &
exec nginx -g 'daemon off;'
