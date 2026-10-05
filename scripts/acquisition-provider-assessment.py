#!/usr/bin/env python3
"""Published-price arithmetic, not procurement or measured sending capacity."""
import argparse
import json
import math

def assess(slots=10, per_domain=3, optional_mask=False, total_per_day=200, other_mail=0):
    billed = max(10, slots)
    domains = math.ceil(billed / per_domain)
    monthly_mailboxes = billed * 300
    annual_domains = domains * 1400
    monthly_optional = domains * 200 if optional_mask else 0
    return {
        "schema": "famtastic.acquisition-provider-assessment.v1", "reviewed_at": "2026-10-05", "provider": "mailforge", "status": "candidate_not_purchased_not_connected",
        "quote_basis": "published monthly pricing; arithmetic estimate excludes taxes, verification, warmup, optional sequencer, labor and processing costs; exact checkout quote pending",
        "currency": "USD", "billed_slots": billed, "domains_at_assumed_mailboxes_per_domain": domains, "assumed_mailboxes_per_domain": per_domain,
        "monthly_mailbox_minor": monthly_mailboxes, "annual_domain_minor": annual_domains, "monthly_optional_mask_minor": monthly_optional,
        "first_month_plus_domain_year_minor": monthly_mailboxes + monthly_optional + annual_domains,
        "twelve_month_arithmetic_minor": 12 * (monthly_mailboxes + monthly_optional) + annual_domains,
        "annual_discount_quote": None, "tax_minor": None, "measured_daily_capacity": None,
        "vendor_guidance_sends_per_mailbox_per_day": 30, "vendor_guidance_max_per_mailbox_per_day": 100,
        "native_integration": {"smtp": "existing PHPMailer path; provider-specific compatibility and auth not proven", "scheduling": "native day0/day3/day7 sequence; sending remains separately gated", "reply_ingestion": "existing native inbound path plus conservative recipient stop; Mailforge forwarding or IMAP receipt unproved", "provider_event_api": "native signed events are FAMtastic contract, not verified Mailforge event support", "mailforge_api": "infrastructure management only, not campaign sends/sequences/replies"},
        "capacity_arithmetic": {"cohort_new_businesses": 150, "max_sequence_messages": 450, "future_total_sends_per_workday": total_per_day, "other_mail_per_workday_assumption": other_mail, "five_workday_total_ceiling": total_per_day * 5, "three_touch_new_business_equivalent_per_week": max(0, total_per_day - other_mail) * 5 // 3, "1000_new_per_week_max_touches": 3000, "1000_new_per_week_satisfied": max(0, total_per_day - other_mail) * 5 >= 3000},
        "sources": ["https://www.mailforge.ai/terms", "https://www.mailforge.ai/pricing", "https://help.salesforge.ai/en/articles/10333644-how-to-use-the-mailforge-api", "https://help.salesforge.ai/en/articles/10333634-how-the-forge-stack-works-end-to-end"],
    }

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--slots", type=int, default=10)
    parser.add_argument("--mailboxes-per-domain", type=int, choices=[2, 3], default=3)
    parser.add_argument("--optional-mask", action="store_true")
    parser.add_argument("--total-per-day", type=int, default=200)
    parser.add_argument("--other-mail", type=int, default=0)
    args = parser.parse_args()
    if args.slots < 1 or args.total_per_day < 1 or args.other_mail < 0:
        parser.error("Positive slots/capacity and nonnegative other mail required.")
    print(json.dumps(assess(args.slots, args.mailboxes_per_domain, args.optional_mask, args.total_per_day, args.other_mail), indent=2))
