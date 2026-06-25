# Portal auth plan

Status
- Client portal auth is still mocked/future in this branch.
- The current /portal and /client-portal-login pages are preview surfaces only.

Core rule
- Client portal is separate from admin.
- Portal registration should be invite-only.
- The portal should never be treated as the CMS/admin backend.

Required future client auth pages
- /client-portal-login
- /client-portal-accept-invite
- /client-portal-forgot-password
- /client-portal-reset-password
- /client-portal-dashboard

Future auth requirements
- invite token acceptance flow
- real session management
- client/user records
- role checks for client access
- protected dashboard data
- file access controls
- invoice visibility controls

Password recovery requirement
- forgot/reset password requires an email provider
- no production forgot/reset flow should launch without verified email delivery

Current proof stance
- UI language should stay client-facing
- auth remains mocked until real backend wiring is connected
- preview pages can explain the flow, but must not imply production auth is complete
