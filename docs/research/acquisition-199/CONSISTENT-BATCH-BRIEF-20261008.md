Title: Consistent admission of acquisition batches
Purpose: Make the approved sender schedule usable against its rolling account budget.
Goal: Explain actual partial batches, forecast allowance openings, and prepare a tested admission rule without changing approved limits or hours.

Tasks:
- [x] Verify live counts, releases and historical per-window budget.
- [x] Forecast next full-batch capacity and approved-slot availability.
- [x] Add opt-in complete-batch admission before any reservation, with meaningful no-network tests.
- [ ] Record review, activation decision, post-evaluation and source checkpoint.

Status: active
Started: 2026-10-08 12:34 America/New_York
Ended:
Execution: Existing codex/acquisition-199-20261005 worktree, current main eef69531; no new branch/worktree. Source candidate prepared; bounded apply will touch one executor file and the signed existing normal schedule input only, preserving hours/caps and basefrontend/backend markers.
Research: 48at9/5at10 from incoming verified root audit; fresh independent readback pending. Earlier evening sends remain in rolling24h budget.
Review: Preserve200TOTAL/day,50/hour/window,reserves250/400,history/suppression/exactkeys/releaseguards/uncertainhalt. Fritz directly selected50perbatch here; signed scheduled batch_size50 and exact executor hash required. Full admission cannot promise all sends complete if safety changes mid-batch.
Skills: Existing famtastic-build-review/client-owner-training evidence closeout.

Proof:
- Actual owner escalation verified in source human turn01a11c5c-7dfe-7050-b191-a9e3f779b1b3; investigation required.

- Fresh16:36:54Z:48at9/5at10 today; Oct7completed200. Currentrolling250 leaves0. Earliestmodel50roomOct8 18:16:18Eastern, outside approvedhours; nextapprovedOct9 09:00. Assumesno newtraffic/stops; notproviderquota or guaranteed sends.
- Source36SQLite tests/1961assertions; capacity28checks; frontendguard14checks; syntax and diff PASS.
