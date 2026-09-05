import type { DropConfig } from '../system/types';

/**
 * A source-backed pillar cut for the own-website-vs-rented-platforms series.
 *
 * The copy deliberately makes no claim about a particular booking platform's
 * rules, fees, or data policy. It argues for an owned front door, then names
 * the current Website Launch scope exactly as configured in the product
 * registry. The plate binding is aspect-aware: each format uses an image made
 * for its geometry rather than cropping the landscape hook into a vertical.
 */
export const platformDependency: DropConfig = {
  slug: 'platform-dependency',
  title: 'Your business should not live on borrowed land',
  source: 'marketing/blog/clusters/cluster-own-website-vs-rented-platforms/cluster-plan.json',
  palette: 'paper',
  paletteArgument:
    'An owned front door should feel durable and calm, not like another dark-platform warning; paper makes the argument feel like a useful field guide.',
  ctaUrl: 'https://famtasticdesigns.com/buy/?sku=FAM-FOOT-199&utm_source=campaign&utm_medium=video&utm_campaign=platform-dependency',
  scenes: [
    {
      kind: 'plate',
      seconds: 4,
      eyebrow: 'Platform dependency',
      head: ['Your business', 'should not live', 'on borrowed land.'],
      accentFrom: 2,
      plate: {
        src: {
          '16x9': 'plate-01-pillar-hero-16x9.jpg',
          '1x1': 'plate-08-ghost-hook-square-1x1.jpg',
          '9x16': 'plate-06-c1-hand-phone-vertical-9x16.jpg',
          default: 'plate-01-pillar-hero-16x9.jpg',
        },
        focus: { default: [0.5, 0.5] },
        pan: 'in',
      },
    },
    {
      /*
       * THE ANCHOR. The one bought asset in this campaign: a HeyGen take of the
       * existing "FAMtastic Guide" avatar. Under CHEAP_PRODUCTION_ECONOMICS_V1
       * it is also the style reference the free tiers are graded to, so it plays
       * inside the kit rather than being bolted on as a separate cut.
       * Source take: marketing/creative/heygen/renders/take-a-platform-dependency.mp4
       */
      kind: 'presenter',
      seconds: 8,
      eyebrow: 'Why it matters',
      src: 'presenter/take-a.mp4',
      head: ['Own the address', 'customers type.'],
    },
    {
      kind: 'split',
      seconds: 5,
      eyebrow: 'The distinction',
      head: ['A booking link', 'is not a home base.'],
      body: [
        'Platforms can help you get found. Their rules, ranking, and reach are still theirs.',
        'Your address should stay at the center.',
      ],
    },
    {
      kind: 'checklist',
      seconds: 6,
      eyebrow: 'Keep the front door',
      head: ['What an owned', 'website gives you'],
      items: ['A domain customers remember', 'One clear place to send people', 'A direct path to your own next step'],
      note: 'The platform can remain useful. It just should not be your only address.',
    },
    {
      kind: 'offer-card',
      seconds: 6,
      eyebrow: 'Website Launch',
      chip: 'One focused start',
      head: 'Build the home base first.',
      price: '$199',
      terms:
        'One focused landing page. First year of managed hosting. A new domain’s first year when needed — or connect the one you own.',
      cta: 'See the launch scope',
      disclosure: 'Business email setup is a separate $99 add-on.',
    },
    {
      kind: 'outro',
      seconds: 4,
      head: ['Build a place', 'that stays yours.'],
      cta: 'Start with Website Launch',
      terms: 'Scope and intake at famtasticdesigns.com',
    },
  ],
};
