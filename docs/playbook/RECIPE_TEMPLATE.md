# RECIPE: <name>

**Outcome**: <the business result, one sentence>
**Trigger**: <what starts this recipe>
**Owner**: <accountable role — exactly one>
**Last verified**: <date + how>

## Steps

| # | Step | Owner | Definition of done | Evidence required | Status |
|---|------|-------|--------------------|-------------------|--------|
| 1 | <verb-first action> | <role> | <observable end state> | <validator/log/SHA> | ⚠️ |

## Failure paths

| Step | If it fails | Fallback / escalation |
|------|-------------|----------------------|
| 1 | <failure mode> | <retry rule, who is alerted> |

## Approval gates

- Step N requires Fritz's explicit approval before crossing. Prepare: exact change + rollback.

## Change log

- <date> — <what changed in this recipe and why>
