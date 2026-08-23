# RUNBOOK A1 — Production mail integrity verification & fix

**Recipe step**: AUTONOMOUS_CUSTOMER_SERVICE Phase A, step A1 (prereq for A2–A6, LEAD_TO_LAUNCH 7–12, T4 waves).
**Executor**: Fritz (production ops). Prepared by CEO 2026-08-23.
**Ground truth**: `backend/web/modules/custom/famtastic_pipeline/src/Service/OutreachMailer.php` fails closed with exactly four exception modes; success emits watchdog info `OUTREACH EMAIL accepted by SMTP`.
**Standing rule**: verify before writing any code. Ranked hypothesis #1 is that production `smtp.settings` is unconfigured → every send throws `notification_transport_not_configured` and lands in dead-letter.
**Never**: commit credentials, send to any real customer during testing, or let an agent run these commands autonomously.

## Phase 0 — Read-only diagnostics (changes nothing)

SSH target is the same one the deploy scripts use (`FAMTASTIC_SSH_TARGET`, default `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net`). Set `$DRUSH` to the production drush binary (try `$HOME/deploy/famtastic-designs/backend/vendor/bin/drush -r $HOME/public_html`, else locate under `~/public_html`).

```bash
# 0.1 Transport selection + SMTP settings shape (values masked, nothing secret printed)
$DRUSH php:eval '$c=\Drupal::config("smtp.settings");printf("transport_env=%s\nsmtp_on=%s host=%q port=%s user_set=%s pass_len=%d proto=%s autotls=%s\n", getenv("FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT") ?: "(unset)", var_export($c->get("smtp_on"),true), $c->get("smtp_host"), $c->get("smtp_port"), $c->get("smtp_username")!=="", strlen((string)$c->get("smtp_password")), $c->get("smtp_protocol"), var_export($c->get("smtp_autotls"),true));'
# ALSO check the CRON user sees the same env: crontab -l | grep FAMTASTIC

# 0.2 Recent mail failures in watchdog
$DRUSH php:eval 'foreach(\Drupal::database()->select("watchdog","w")->fields("w",["timestamp","severity","message"])->condition("message","%OUTREACH%","LIKE")->orderBy("timestamp","DESC")->range(0,15)->execute()->fetchAllAssoc("timestamp") as $r){printf("%d sev=%d %s\n",$r->timestamp,$r->severity,$r->message);}'

# 0.3 Outbox reality (dead letters, retries, queue age)
$DRUSH sqlq "SELECT status, COUNT(*) AS n FROM famtastic_notification_outbox GROUP BY status;"
$DRUSH sqlq "SELECT category, recipient, subject, attempts, last_error FROM famtastic_notification_outbox WHERE status='dead_letter' ORDER BY updated DESC LIMIT 10;"
```

Cross-check in browser (read-only): `/admin/famtastic/metric/notifications` and `/admin/famtastic/metric/workers`.

**Decision point.** Map Phase 0 output to the failure mode:

| Finding | Meaning |
|---|---|
| `smtp_on=false` or empty host/port | `notification_transport_not_configured` — hypothesis #1 confirmed |
| `user_set=false` or `pass_len=0` | `notification_transport_credentials_invalid` |
| `transport_env=memory` | dev capture mode active in prod — every send captured, never delivered |
| Config looks complete + delivery errors in watchdog | `notification_delivery_failed` — network/provider problem, different fix |

## Phase 1 — Fix (Fritz only; approval gate)

1. **Snapshot for rollback**: save Phase 0 line 0.1 output verbatim to `~/backups/smtp-settings-before-A1-$(date +%Y%m%d-%H%M).txt`.
2. Configure (real credentials supplied interactively by Fritz; never stored in repo):
   ```bash
   $DRUSH config:set smtp.settings smtp_on 1
   $DRUSH config:set smtp.settings smtp_host '<host>'
   $DRUSH config:set smtp.settings smtp_port '<port>'
   $DRUSH config:set smtp.settings smtp_protocol 'tls'   # or ssl per provider
   $DRUSH config:set smtp.settings smtp_username '<user>'
   # password via secret prompt, not shell history:
   $DRUSH config:set smtp.settings smtp_password
   ```
   If `transport_env=memory` was found: remove/correct `FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT` in the web+cron environment (hosting panel env vars / crontab), then confirm 0.1 prints `(unset)` or `smtp`.
3. **Test fire — owner inbox only**:
   ```bash
   $DRUSH php:eval '\Drupal::classResolver()->getInstanceFromDefinition(\Drupal\famtastic_pipeline\Service\OutreachMailer::class)->send("<fritz-private-email>","A1 SMTP TEST","If you can read this, production transport works.");'
   ```

## Phase 2 — Evidence to close A1 (attach to recipe)

- Watchdog info line containing `OUTREACH EMAIL accepted by SMTP` for the test message, AND
- The test email visible in Fritz's inbox.
Record both (path/screenshot names) inline in AUTONOMOUS_CUSTOMER_SERVICE.md step A1, then proceed A2 (worker heartbeat) and A3 (requeue dead-letters — expect real customer notifications to finally deliver; review the dead-letter list from 0.3 BEFORE requeueing anything).

## Rollback

Re-apply the saved pre-change values from the snapshot file via the same `config:set` calls; unset any env change made in step 2. No other data is touched by this runbook.
