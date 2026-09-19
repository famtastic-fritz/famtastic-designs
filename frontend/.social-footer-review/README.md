# Local footer review

This Vite development-only entry imports the actual production SiteFooter and badges.
It is not a router entry and is absent from `vite build` output. Sample CMS link data
is visibly labeled; the actual app at `/` still obtains its data through Layout.

From repository root (Node 22):

```
npm --prefix frontend ci
npm --prefix frontend run dev -- --host 127.0.0.1 --port 5196
```

- Actual app: http://127.0.0.1:5196/
- Integrated footer with sample props: http://127.0.0.1:5196/.social-footer-review/index.html
- Full icon/interaction gallery: http://127.0.0.1:5196/.social-footer-review/index.html?gallery=1

Gallery controls exercise empty CMS, absent/throwing analytics, CSS 200% zoom and the
exact reduced-motion rule body. Reduced-motion control is a local rule simulation,
not operating-system media-preference emulation. All unconfigured icon previews are
noninteractive. Static state references are labeled and scoped to this local entry.
Real footer links navigate immediately and are not intercepted; recording uses only
local React state and installs no tracker. No click establishes a follow.
