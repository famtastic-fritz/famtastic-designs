# Local install path

Exact repo path
- `~/famtastic/sites/site-famtastic-designs`

Install
```bash
cd ~/famtastic/sites/site-famtastic-designs
corepack enable
pnpm install
```

Run frontend
```bash
PORT=3001 pnpm dev
```

Run Directus
```bash
cd ~/famtastic/sites/site-famtastic-designs/directus
docker compose up -d
```

Note
- If Docker says it cannot connect to `/Users/famtasticfritz/.docker/run/docker.sock`, start Docker Desktop first and rerun `docker compose up -d`.

Build
```bash
cd ~/famtastic/sites/site-famtastic-designs
pnpm build
```

Preview
```bash
cd ~/famtastic/sites/site-famtastic-designs
PORT=3300 node .output/server/index.mjs
```

Important
- Do not run `pnpm dev` and preview from the same `.output` snapshot at the same time. Run dev when you are iterating, then stop it, rebuild, and start preview.
