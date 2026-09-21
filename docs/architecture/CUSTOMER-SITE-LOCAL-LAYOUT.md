# Customer-site local layout

## Customer-site checkout location — owner correction, 2026-09-21

Create customer source repositories at `/Users/famtastic-fritz/Development/FAMtastic/sites/site-<business-slug>` (portable form: `~/Development/FAMtastic/sites/site-<business-slug>`). StockandShip98 belongs at `~/Development/FAMtastic/sites/site-stockandship98`. `FAMtastic-Repos` is not the default customer-site collection.

Each site must own its Git root, common directory, manifest and verified remote. The ecosystem ignores `/sites/`; independent repositories beneath that ignored directory are valid. A tracked folder, submodule/gitlink, or worktree sharing the agency/platform Git common directory is not an independent customer repository. Verify the parent ignore rule and absence of tracked target paths before creation.

Studio/library checkouts retain their separately configured locations. Explicit sandbox roots remain supported. New customer identities should use `site-<business-slug>`; preserve existing IDs and registry bindings on continuation. Check existing source and registry before creating a duplicate. This rule does not automatically move existing repositories or authorize deployment, credentials, DNS or customer communication. Fritz assigned the StockandShip98 move to its own task.

Historical migration receipts retain their original paths as evidence; this current rule supersedes their use as defaults. When an authorized move is performed, preserve history, dirty work and remotes, then update the local project/launcher mappings and current handoff documents. Confirm the actual new checkout before claiming migration complete.

## Verification — 2026-09-21

Component Studio guard release `2937a3b` passed six foundation tests. Site Studio release `bf1ef9c` captures that source and passed 19 focused path/build tests plus both lint gates. New builds succeed under an ignored ecosystem sites collection; tracked nested repositories, foreign identities and dirty source remain rejected. The installed repository skill passes its validator. The local Studio launcher was reloaded after checking no child tasks or active TCP connections; runtime path/health readback confirms the new root. No customer source was moved, deployed or emailed. Documentation changes do not require a public-site deployment.
