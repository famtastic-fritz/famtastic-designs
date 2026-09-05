/**
 * Beat + caption map for drop-06.
 *
 * Every timing below is taken from the HeyGen sidecar SRT for video
 * f6f8a4cae86a4fbf046aa066db983bfc (VO duration 39.628s), not estimated.
 * fps = 30.
 *
 * Provenance for the presenter asset (Tier 1):
 *   heygen video id     f6f8a4cae86a4fbf046aa066db983bfc
 *   avatar look         a7994fb69c394554b62585c1c7235211  "FAMtastic Guide" (photo_avatar)
 *   voice               PYOwc95OauMcZm0OasXx  (that look's default voice)
 *   brand kit           8d249f1d06b4440ea665c539f206ecb7  "FAMtastic Designs"
 *   render              16:9 1280x720 @25fps, 12 premium credits
 *
 * Provenance for the cinematic plates (Tier 2):
 *   marketing/campaigns/cost-is-not-the-reason/images/broll/receipt.json
 */
export const FPS = 30;
export const VO_SECONDS = 39.628;
export const TOTAL_FRAMES = 1320; // 44.0s: VO + outro card

const f = (s: number) => Math.round(s * FPS);

export type Caption = { from: number; to: number; text: string; hi?: string[] };

/** Bottom caption cues, grouped from the SRT into readable lines. */
export const CAPTIONS: Caption[] = [
  { from: f(0.0), to: f(3.72), text: 'Your business card, your Instagram bio, your invoices.' },
  { from: f(3.86), to: f(7.36), text: 'They all point to a Gmail address and a link-in-bio page.', hi: ['Gmail', 'link-in-bio'] },
  { from: f(7.52), to: f(8.62), text: "That's rented land.", hi: ['rented', 'land.'] },
  { from: f(8.74), to: f(11.42), text: "You don't own it, and it can't answer a question for you." },
  { from: f(11.63), to: f(15.44), text: 'So when someone wants to know what you charge, they have to message you,' },
  { from: f(15.47), to: f(16.08), text: 'and then wait.', hi: ['wait.'] },
  { from: f(16.21), to: f(18.64), text: 'Some of them book with whoever answered first.', hi: ['answered', 'first.'] },
  { from: f(18.92), to: f(22.30), text: 'A site of your own answers that at two in the morning, without you.', hi: ['two', 'in', 'the', 'morning,'] },
  { from: f(22.56), to: f(25.44), text: 'One hundred ninety-nine dollars covers your first year.' },
  { from: f(25.61), to: f(29.50), text: 'About fifty-five cents a day, with hosting and your domain included.' },
  { from: f(29.65), to: f(34.86), text: 'After year one, hosting is $9.99 a month, plus what the domain costs.' },
  // 35.02-36.88 is carried by the full-screen statement, not a bottom caption.
  { from: f(37.23), to: f(39.52), text: 'The full breakdown is on our packages page.' },
];

export type Beat =
  | { kind: 'presenter'; from: number; to: number; eyebrow: string; trimBefore?: number }
  | { kind: 'plate'; from: number; to: number; eyebrow: string; plate: string; pan: 'in' | 'out' }
  | { kind: 'offer'; from: number; to: number }
  | { kind: 'statement'; from: number; to: number; plate: string }
  | { kind: 'outro'; from: number; to: number };

export const BEATS: Beat[] = [
  { kind: 'presenter', from: 0, to: f(7.44), eyebrow: 'The excuse, honestly' },
  { kind: 'plate', from: f(7.44), to: f(11.5), eyebrow: 'Rented land', plate: '01-rented-land.jpg', pan: 'in' },
  { kind: 'plate', from: f(11.5), to: f(16.12), eyebrow: 'The wait', plate: '02-phone-at-night.jpg', pan: 'out' },
  { kind: 'plate', from: f(16.12), to: f(18.8), eyebrow: 'And then', plate: '03-waiting.jpg', pan: 'in' },
  { kind: 'plate', from: f(18.8), to: f(22.4), eyebrow: 'Two in the morning', plate: '04-works-while-you-sleep.jpg', pan: 'out' },
  { kind: 'offer', from: f(22.4), to: f(34.94) },
  { kind: 'statement', from: f(34.94), to: f(37.1), plate: '05-owner-confident.jpg' },
  { kind: 'presenter', from: f(37.1), to: f(39.62), eyebrow: 'See it for yourself', trimBefore: f(37.1) },
  { kind: 'outro', from: f(39.62), to: TOTAL_FRAMES },
];

/**
 * Every fact below is taken from backend/config/famtastic-products.json
 * (FAM-FOOT-199 / FAM-HOST-999) and verified against the live packages page.
 */
export const OFFER = {
  price: '$199',
  priceSub: 'first year — about 55¢ a day',
  includes: [
    'A focused landing-page website',
    'First year of managed hosting',
    'A new domain, or connect your own',
  ],
  disclosure: 'After year one, hosting is $9.99/mo. The domain renews separately.',
};

/** Verified HTTP 200 on 2026-09-05 before this URL was burned into pixels. */
export const CTA_URL = 'famtasticdesigns.com/packages/199-quick-start';
