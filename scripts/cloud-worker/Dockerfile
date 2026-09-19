# Supply a reviewed Node 24 image pinned by digest. Do not build from mutable latest.
ARG NODE_IMAGE
FROM ${NODE_IMAGE}
WORKDIR /worker
COPY worker.mjs /worker/worker.mjs
USER node
CMD ["node", "/worker/worker.mjs"]
