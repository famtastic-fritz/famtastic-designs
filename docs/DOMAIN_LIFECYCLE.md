# Customer domain lifecycle

FAMtastic records domains as customer-owned. It does not purchase a domain or
change DNS during automated verification.

`customer_managed` means the customer makes DNS changes. `delegated` means the
customer has provided recorded authorization for FAMtastic to manage DNS. That
authorization is required before the domain can be registered in delegated mode.

After an immutable customer release is deployed, `domain.verify` checks supplied
read-only DNS and TLS evidence. A successful check records the observed target,
certificate status, and timestamp, then queues the included hosting entitlement.
The default verifier is disabled. The `fixture` verifier exists for deterministic
acceptance tests; a production read-only provider adapter must be explicitly
configured before live verification.

Domain purchases and DNS mutations remain separate, explicit approval-gated
operations and are intentionally absent from this service.
