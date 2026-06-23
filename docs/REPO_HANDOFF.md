# Repo handoff

Current branch
- `famtastic/site-v1-integration`

Origin remote
- `git@github.com:famtastic-fritz/famtastic-designs.git`

Backup tag
- `backup/pre-famtastic-designs-site-v1-20260623-114724`

Backup archive path
- `/Users/famtasticfritz/famtastic/backups/site-famtastic-desgins.com-pre-v1-20260623-114724.tar.gz`

Files changed
- Legacy Drupal/PHP site replaced in the active branch by the Nuxt proof app
- Added local/mock proof docs
- Added local Directus proof folder
- Added local lead example file
- Added CMS/lead adapter scaffolding for Directus mode

What should be pushed
- The final proof-site integration commit on `famtastic/site-v1-integration`

What must not be pushed
- `.env`
- `.env.local`
- `.data/`
- `directus/database/`
- `directus/uploads/`
- any real credentials or real lead data

Suggested push command
```bash
git push -u origin famtastic/site-v1-integration
```

Suggested pull command for another local clone/final location
```bash
git fetch origin && git switch famtastic/site-v1-integration && git pull --ff-only origin famtastic/site-v1-integration
```
