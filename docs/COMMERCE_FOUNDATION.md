# Drupal Commerce foundation

Drupal Commerce is the catalog, cart, promotion, checkout, and order foundation
for future FAMtastic offers and upsells. The existing pipeline checkout remains
the live payment path until Commerce Stripe is installed, configured in test
mode, verified by webhook, and explicitly promoted to live mode.

## Product architecture

- **Service**: the primary website, application, consulting, or implementation
  purchase.
- **Add-on**: an optional upgrade or post-purchase upsell.
- Both use Commerce's default order item and digital/default order workflow.
- Commerce Promotions supplies coupons, order discounts, and Buy X Get Y rules.

Keeping Service and Add-on as separate product types makes upsell eligibility
and reporting explicit without forcing the existing pipeline order entity to
change before the Commerce checkout is proven.

## Initial setup

Install the locked dependencies, then provide the real business address and
store email through environment-owned values:

```bash
export FAMTASTIC_COMMERCE_STORE_EMAIL='store@example.com'
export FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON='{"country_code":"US","address_line1":"...","locality":"...","administrative_area":"FL","postal_code":"..."}'
./setup-commerce.sh
```

The setup is idempotent. It enables the Commerce foundation, creates one online
USD store, and creates the Service and Add-on product types. It never creates a
live payment gateway or stores payment credentials in Git.

## Payment migration gate

Before sending any customer through Commerce checkout:

1. Add the stable `drupal/commerce_stripe` package compatible with Drupal 11.
2. Configure Stripe Payment Element in **test** mode.
3. Configure and verify the Commerce Stripe webhook.
4. Test successful, declined, abandoned, duplicate-webhook, refund, and zero
   dollar order behavior.
5. Map a paid Commerce order to the pipeline project/fulfillment record.
6. Obtain explicit approval before changing to live credentials or charging a
   real customer.

## Production constraint

Commerce is a runtime dependency addition, so it requires a reviewed platform
migration in addition to the custom-module deployment. Production must have
enough disk headroom for Composer installation, backup creation, database
updates, and rollback artifacts before the migration starts.
