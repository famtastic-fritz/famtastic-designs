# syntax=docker/dockerfile:1

###############################################################################
# FAMtastic Designs v2 — React 18 SPA image (Phase 2 deployment scaffold).
#
#   Stage 1 (build):   node:20-alpine — npm ci + vite build → dist/
#   Stage 2 (runtime): nginx:alpine — serves dist/ and proxies /jsonapi +
#                      /oauth to the backend service (see docker/nginx.conf).
#
# The bundle is built with an EMPTY VITE_DRUPAL_BASE_URL so src/api/drupal.js
# issues same-origin requests and the runtime nginx proxy forwards them —
# no CORS needed inside docker-compose. For a split deployment, bake an
# absolute API origin instead:
#   docker build --build-arg VITE_DRUPAL_BASE_URL=https://api.example.com .
###############################################################################

# ---------------------------------------------------------------------------
# Stage 1: build — compile the Vite production bundle.
# ---------------------------------------------------------------------------
FROM node:20-alpine AS build

WORKDIR /app

# Manifests first for dependency-layer caching.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

ARG VITE_DRUPAL_BASE_URL=""
ENV VITE_DRUPAL_BASE_URL=$VITE_DRUPAL_BASE_URL

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: runtime — static bundle + API proxy on :80.
# ---------------------------------------------------------------------------
FROM nginx:alpine AS runtime

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1/ || exit 1
