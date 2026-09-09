/**
 * filmLibrary — the public catalog behind /watch.
 *
 * WHY THIS FILE EXISTS AT ALL:
 * Twelve films were rendered for the campaigns and none of them earned anything,
 * because the only planned destinations were YouTube (expired OAuth token) and
 * TikTok (not approved for public posting). A film that lives only on disk is
 * indistinguishable from a film that was never made. This module gives every
 * finished film a permanent, indexable home on our own domain, so the channels
 * become a bonus rather than a dependency.
 *
 * WHY IT IS PLAIN DATA AND NOT A COMPONENT:
 * Two very different consumers read it:
 *   1. `pages/WatchHubPage.jsx` / `pages/WatchFilmPage.jsx` (browser, React)
 *   2. `scripts/generate-seo-shells.mjs` (Node, at build time) — which writes a
 *      real `dist/watch/<slug>/index.html` per film carrying VideoObject
 *      JSON-LD. Without that file the route is invisible to crawlers, because
 *      the app is a client-rendered SPA.
 * So this file must import nothing, touch no DOM, and stay serialisable.
 *
 * WHY EVERY NUMBER HERE IS MEASURED:
 * `durationSeconds` and `bytes` come from `ffprobe`/`stat` against the exact
 * file in `public/video/`, not from a README (the ghost-town README, for one,
 * still reports its renders as silent — they carry narration now). `duration`
 * is the field Google actually reads out of VideoObject, and a wrong one is
 * worse than an absent one. Re-derive with:
 *
 *   ffprobe -v error -select_streams v:0 -show_entries stream=width,height \
 *     -show_entries format=duration,size -of csv=p=0:nk=1 public/video/<f>.mp4
 *
 * WHY SOME FILMS HAVE A TRANSCRIPT AND SOME DO NOT:
 * A transcript is the single highest-value field on this page — it is the only
 * part of a film a search engine can actually read. It is therefore also the
 * easiest thing to get wrong by paraphrasing. `transcript` is populated ONLY
 * where a verbatim script exists in this repository and the audio was verified
 * to match it. Three films (`the-sign-that-isnt-there`, `the-dm-trap`,
 * `found-then-kept`) carry real narration whose script is not in the repo —
 * NOTE 2026-09-05: the {ghost,dm,seo} narration text WAS authored (it is what
 * was fed to the TTS) and has since been written to
 * `marketing/hyperframes/_narration/*.txt`, so those three now carry verbatim
 * transcripts rather than null. Only `somebody-elses-app` legitimately has
 * none: it carries a music bed, not narration. Original note follows —
 * so they get `transcript: null` and lead with their on-screen copy instead.
 * Inventing the words would have been trivial and wrong.
 *
 * PRODUCT ACCURACY (backend/config/famtastic-products.json):
 *   FAM-FOOT-199 ($199, "about 55 cents a day across the first year") is ONE
 *   landing-page website + ONE year of managed hosting + the first-year domain
 *   registration, or connection of a domain already owned. Business email
 *   (FAM-BUSINESS-EMAIL, $99 one time) and maintenance (FAM-MAINTENANCE) are
 *   separate products. Hosting renews at $9.99/month from year two and the
 *   domain renews separately. Nothing on these pages may imply otherwise — two
 *   of the films exist specifically to correct that exact confusion.
 */

export const WATCH_BASE = '/watch';
const SITE_URL = 'https://famtasticdesigns.com';

/**
 * ISO 8601 duration for schema.org.
 *
 * Rounded to a tenth of a second, then trailing `.0` dropped, so 27.5 s stays
 * `PT27.5S` (a real half-second the film actually holds) while 33.045333 s
 * becomes `PT33S` rather than pretending to millisecond precision the metadata
 * consumer will never use. Derived from ffprobe every time — never typed by hand.
 */
export function isoDuration(seconds) {
  const tenths = Math.round(Number(seconds) * 10);
  const whole = Math.floor(tenths / 10);
  const remainder = tenths % 10;
  const mins = Math.floor(whole / 60);
  const secs = whole - mins * 60;
  const secondPart = remainder ? `${secs}.${remainder}S` : `${secs}S`;
  return mins ? `PT${mins}M${secondPart}` : `PT${secondPart}`;
}

/** Human running time: "0:29", "1:04". Used in the UI, never in structured data. */
export function runningTime(seconds) {
  const total = Math.round(Number(seconds));
  const mins = Math.floor(total / 60);
  return `${mins}:${String(total - mins * 60).padStart(2, '0')}`;
}

/**
 * Every film. Ordered as a viewing order, not by date: the two films that
 * correct a scope misunderstanding sit late, after the films that establish
 * why an owned address matters at all.
 *
 * Field notes:
 *  - `tagline` is the `message:` line from the film's own STORYBOARD.md, verbatim.
 *  - `onScreen` is the film's own on-screen copy, verbatim from its storyboard
 *    and confirmed against extracted frames. It is the readable substance of a
 *    film for anyone who cannot or will not play it.
 *  - `art` names the block in lib/blogArt.js this film's argument earns. It is a
 *    content decision, not decoration — same rule the blog art engine applies.
 */
export const FILMS = [
  {
    slug: 'borrowed-land',
    title: 'Borrowed Land',
    eyebrow: 'Platform dependency',
    series: 'Own the address',
    tagline:
      'The platform helps people find you, but the address they find isn’t yours — own the one they type.',
    summary:
      'A 29-second film about the difference between being findable and being reachable at an address you own.',
    argument: [
      'The film opens by conceding the two things a working business owner will not argue with: the business is real, and the customers are real. Only then does it turn on the third — the place those customers find you is an address inside somebody else’s building.',
      'It does not name a platform and it does not claim one treats you badly. The argument is structural: the rules, the ranking and the reach belong to whoever owns the building, and they can change without anyone asking you first. A page at a domain you own works differently, because it answers at two in the morning without you.',
    ],
    audience:
      'For a business that genuinely is being found today — through a profile, a listing, or an app it does not control — and has never had a reason to ask what happens if that changes.',
    file: '/video/borrowed-land.mp4',
    poster: '/video/borrowed-land.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 29.0,
    bytes: 15583421,
    uploadDate: '2026-09-05',
    sound: 'Narrated throughout.',
    transcript: [
      'Your business is real.',
      'Your customers are real.',
      'But the place they find you, you do not own it.',
      'A profile is an address inside somebody else’s building.',
      'The rules can change, and nobody asks you first.',
      'A site of your own works differently.',
      'It sits at a domain you own, and it answers the question at two in the morning, without you.',
    ],
    onScreen: [
      { beat: 'The hook', lines: ['Your business is real.', 'Your customers are real.'] },
      { beat: 'The address', lines: ['The place they find you isn’t yours.'] },
      {
        beat: 'The mechanism',
        lines: [
          'A profile is an address inside somebody else’s building.',
          'Their rules. Their ranking. Their reach.',
          'What a search engine sees: a list of links. Almost nothing.',
        ],
      },
      {
        beat: 'The fix',
        lines: [
          'A site of your own works differently.',
          'It sits at a domain you own, and it answers the question at two in the morning — without you.',
        ],
      },
      {
        beat: 'The scope',
        lines: [
          'Web Basics Bundle — $199, about 55 cents a day across the first year',
          'One landing-page website',
          'One year of managed hosting',
          'Your first-year domain — new, or connect one you already own',
          'Business email and maintenance are separate.',
        ],
      },
      { beat: 'The close', lines: ['Own the address they type.', 'famtasticdesigns.com'] },
    ],
    art: 'ownedVsRented',
    keywords: ['platform dependency', 'own your domain', 'small business website', 'booking app'],
  },

  {
    slug: 'not-a-home-base',
    title: 'A Booking Link Is Not a Home Base',
    eyebrow: 'Website launch',
    series: 'Own the address',
    tagline:
      'Platforms can help you get found. Their rules, ranking, and reach are still theirs.',
    summary:
      'A 33-second cut of the borrowed-land argument that ends on the actual launch scope, item by item.',
    argument: [
      'The same argument as Borrowed Land, built by a different system and cut differently — six scenes that open on the claim, hand it to a presenter, draw the distinction between a booking link and a home base, and then say plainly what an owned website gives you.',
      'It is the version to send someone who has already agreed with the idea and wants to know what is actually being sold. The offer beat names one focused landing page, the first year of managed hosting, and a new domain’s first year or the connection of one already owned — and puts the separate $99 business-email setup on the same frame so it cannot be misread as included.',
    ],
    audience:
      'For someone weighing whether the profile or booking link they already have is enough, and who wants the scope on one screen before they talk to anybody.',
    file: '/video/not-a-home-base.mp4',
    poster: '/video/not-a-home-base.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 33.045333,
    bytes: 5741186,
    uploadDate: '2026-09-05',
    sound: 'Narrated across the presenter beat; the remaining beats play silent.',
    // Only the first eight seconds of the presenter take are used. Verified by
    // per-second loudness: the film's 4-12 s window matches the take's 0-8 s
    // window to within 0.9 dB per second, and every other second of the film
    // measures -91 dB. The take's fourth sentence begins inside that window and
    // is cut before it finishes, so it is not listed as spoken.
    transcript: [
      'Your business is real.',
      'Your customers are real.',
      'But the place they find you, you do not own it.',
    ],
    onScreen: [
      {
        beat: 'Platform dependency',
        lines: ['Your business should not live on borrowed land.'],
      },
      { beat: 'Why it matters', lines: ['Own the address customers type.'] },
      {
        beat: 'The distinction',
        lines: [
          'A booking link is not a home base.',
          'Platforms can help you get found. Their rules, ranking, and reach are still theirs.',
          'Your address should stay at the center.',
        ],
      },
      {
        beat: 'Keep the front door',
        lines: [
          'What an owned website gives you',
          'A domain customers remember',
          'One clear place to send people',
          'A direct path to your own next step',
          'The platform can remain useful. It just should not be your only address.',
        ],
      },
      {
        beat: 'Website Launch',
        lines: [
          'Build the home base first. — $199',
          'One focused landing page. First year of managed hosting. A new domain’s first year when needed — or connect the one you own.',
          'Business email setup is a separate $99 add-on.',
        ],
      },
      {
        beat: 'The close',
        lines: ['Build a place that stays yours.', 'Scope and intake at famtasticdesigns.com'],
      },
    ],
    art: 'ownershipMarker',
    keywords: ['booking link', 'owned website', 'website launch', '$199 website'],
  },

  {
    slug: 'the-sign-that-isnt-there',
    title: 'The Sign That Isn’t There',
    eyebrow: 'Get found',
    series: 'Get found',
    tagline:
      'Outside one booking app you are a ghost — and being findable is just a surface with your name on it.',
    summary:
      'A 44-second film that starts from a search a customer actually types and asks whether you appear in it.',
    argument: [
      'The film opens on a search — “hair stylist near me” — and asks whether you are in the results. It then names the real position a lot of personal-service businesses are in: they exist in exactly one place, and that place is an app.',
      'The turn is the part that matters and the part most versions of this argument skip: this is true even if the fees are fine. The complaint is not about cost. It is that one page with your own address on it is the thing a stranger can find, read and come back to, and a profile is not.',
    ],
    audience:
      'For a stylist, barber, or any appointment-based business whose entire findable presence is a booking profile someone else operates.',
    file: '/video/the-sign-that-isnt-there.mp4',
    poster: '/video/the-sign-that-isnt-there.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 44.0,
    bytes: 21944192,
    uploadDate: '2026-09-05',
    sound: 'Narrated throughout.',
    transcript: [
      "Search \"hair stylist near me\" in your own city.",
      "Are you in those results?",
      "Not the app's results.",
      "The ones a stranger sees.",
      "Outside that app, you exist in exactly one place.",
      "This isn't about fees.",
      "Even if the fees are fine, you already know what the app costs you.",
      "This is the quieter one.",
      "The fix isn't complicated.",
      "One page.",
      "Your own address.",
      "Your work, not a template thumbnail.",
      "A web address that belongs to you.",
      "One page a stranger can find and read.",
      "One hundred and ninety-nine dollars for the first year.",
      "About fifty-five cents a day.",
      "Stop being a ghost.",
    ],
    onScreen: [
      {
        beat: 'The hook',
        lines: ['Search: “hair stylist near me”', 'Are you in the results?'],
      },
      { beat: 'The absence', lines: ['You exist in exactly one place.'] },
      { beat: 'The reframe', lines: ['Even if the fees are fine.'] },
      {
        beat: 'The fix',
        lines: [
          'One page. Your own address.',
          'Your work, not a template thumbnail',
          'A web address that belongs to you',
          'One page a stranger can find and read',
        ],
      },
      {
        beat: 'The offer',
        lines: [
          '$199 — 55 cents a day',
          'One focused landing-page website',
          'One year of managed hosting',
          'First-year domain — new, or bring your own',
          'Then $9.99/mo hosting if you keep it, and only with your authorization. Domain renewal, business email and maintenance are separate.',
        ],
      },
      { beat: 'The close', lines: ['Stop being a ghost.', 'famtasticdesigns.com'] },
    ],
    art: 'ownershipMarker',
    keywords: ['hair stylist near me', 'local search', 'booking profile', 'own your website'],
  },

  {
    slug: 'the-dm-trap',
    title: 'The DM Trap',
    eyebrow: 'Get found',
    series: 'Get found',
    tagline: 'If the only way to book you is a DM, every new client is one algorithm change away.',
    summary:
      'A 15-second short: one sentence, one turn, one address. The compressed version of the argument.',
    argument: [
      'Three beats on one photograph — a phone face-down beside a bedside lamp — and a single slow push across the whole fifteen seconds. There are no cuts because the picture is the argument.',
      'It makes one point and stops: booking entirely through direct messages means the flow of new clients is downstream of a feed you do not control. The answer offered is not a strategy, it is an address.',
    ],
    audience:
      'For anyone whose booking process is “message me” — and for sharing, because it is short enough to actually be watched.',
    file: '/video/the-dm-trap.mp4',
    poster: '/video/the-dm-trap.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 6604022,
    uploadDate: '2026-09-05',
    sound: 'Narrated throughout.',
    transcript: [
      "If the only way to book you is a direct message, every new client is one algorithm change away from never finding you.",
      "A page they can reach directly doesn't depend on anyone's feed.",
    ],
    onScreen: [
      { beat: '1', lines: ['If the only way to book you is a DM…'] },
      { beat: '2', lines: ['…every new client is one algorithm change away.'] },
      {
        beat: '3',
        lines: ['One page you own.', '$199 first year · 55¢ a day', 'famtasticdesigns.com'],
      },
    ],
    art: 'ownershipMarker',
    keywords: ['dm booking', 'algorithm change', 'one page website', 'own your address'],
  },

  {
    slug: 'somebody-elses-app',
    title: 'Somebody Else’s App',
    eyebrow: 'Campus entrepreneurs',
    series: 'Own the address',
    tagline:
      'You already run a business between classes — it just lives in somebody else’s app instead of at an address you own.',
    summary:
      'A 27-second film for student operators who are already trading and have never called it a business.',
    argument: [
      'The film’s only move is one thing getting straight. A photograph lands on the paper at an angle, the way something drops on a desk; later the same shape sits square, sharing the type’s exact left edge. Nothing else changes, so the only thing the eye reads between the two is that it got straight.',
      'The argument underneath is the same: the work is already real, it is just scattered across a DM, a bio link and an app. One page with your name on it is where it stops being scattered.',
    ],
    audience:
      'For a student running something real between classes — resale, food, hair, design, repairs — who has customers but no address of their own.',
    file: '/video/somebody-elses-app.mp4',
    poster: '/video/somebody-elses-app.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 27.5,
    bytes: 15584726,
    uploadDate: '2026-09-05',
    sound: 'Music bed only, no narration.',
    transcript: null,
    onScreen: [
      { beat: 'The hook', lines: ['You already run a business.'] },
      {
        beat: 'The desk',
        lines: ['In a DM.', 'In a bio link.', 'In somebody else’s app.'],
      },
      {
        beat: 'The address',
        lines: [
          'One page with your name on it.',
          'Not a profile inside an app. A web address that is yours — one a customer can find, type, and come back to.',
        ],
      },
      {
        beat: 'The offer',
        lines: [
          '$199 — 55 cents a day',
          'One focused landing-page website',
          'One year of managed hosting',
          'First-year domain — new, or bring your own',
        ],
      },
      { beat: 'The close', lines: ['Stop renting. Start owning.', 'famtasticdesigns.com'] },
    ],
    art: 'ownedVsRented',
    keywords: ['student business', 'campus entrepreneur', 'bio link', 'first website'],
  },

  {
    slug: 'fifty-five-cents',
    title: 'Fifty-Five Cents',
    eyebrow: 'Cost is not the reason',
    series: 'What the price is',
    tagline:
      'Cost is not the reason. Here is the sum, here is the scope, and here is the part most price films leave off.',
    summary:
      'A 28-second film that shows the arithmetic instead of asserting it, then states what renews.',
    argument: [
      'Most price films state a number and move on. This one writes the division out — $199 one time, 365 days, $0.545 per day — and then says so you can do it yourself. A price a viewer is invited to check should look like a quote, not an advertisement, which is why the whole film is set on paper rather than in the site’s black and lime.',
      'The beat it exists for is the fourth one. After the scope comes what happens next: hosting renews at $9.99 a month from year two, the domain renews separately, and business email and maintenance are named as separate products before anything above them can be read as including them.',
    ],
    audience:
      'For anyone whose reason for not having a website is the price — and who would rather see the sum than be told it is affordable.',
    file: '/video/fifty-five-cents.mp4',
    poster: '/video/fifty-five-cents.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 28.0,
    bytes: 24457164,
    uploadDate: '2026-09-05',
    sound: 'Narrated for the first ten seconds; the scope and renewal beats play silent.',
    transcript: [
      'Of all the reasons you do not have a professional website yet, cost is not one of them.',
      'At 55 cents a day, $199 for your entire first year,',
    ],
    onScreen: [
      { beat: 'The objection', lines: ['Cost is not the reason.'] },
      {
        beat: 'The arithmetic',
        lines: [
          '55¢ a day',
          'First year, one time — $199.00',
          'Days in the year — 365',
          'Cost per day — $0.545',
          '$0.545 rounds to 55¢. That is the whole sum — you can do it yourself.',
        ],
      },
      {
        beat: 'What the $199 is',
        lines: [
          'One page, one year, one address.',
          'One focused landing-page website',
          'One year of managed hosting',
          'First-year domain registration, or connect a domain you already own',
          'That is the whole bundle. Nothing else is included in it.',
        ],
      },
      {
        beat: 'After the first year',
        lines: [
          'The part most prices leave off.',
          'Hosting, from year two — $9.99 / month',
          'Your domain renews separately',
          'Separate products: business email $99 one time; website maintenance $49.99 / month',
          'Neither one is included in the $199.',
        ],
      },
      {
        beat: 'The close',
        lines: [
          'Cost is not the reason.',
          'famtasticdesigns.com/packages',
          'Read the whole scope before you buy anything.',
        ],
      },
    ],
    art: 'dayCost',
    keywords: ['$199 website', '55 cents a day', 'website cost', 'hosting renewal'],
  },

  {
    slug: 'two-different-jobs',
    title: 'Two Different Jobs',
    eyebrow: 'Business email',
    series: 'What the price is',
    tagline: 'Does the $199 website come with business email? It does not. Two different jobs.',
    summary:
      'A 31-second answer to the question we get most often, said plainly instead of discovered later.',
    argument: [
      'This film exists to correct one thing. Some campaign copy has implied that the $199 bundle includes a branded business email address. It does not, and the film says so in the first six seconds, in an inverted block that is the only one in the piece — the answer lands before the narration reaches it.',
      'The rest is bookkeeping done out loud: what Web Basics actually is, what renews after the first year, and what business email costs on its own. The last frame carries both products side by side, priced separately, so the closing image cannot be read as a bundle.',
    ],
    audience:
      'For anyone about to buy, or already comparing — and for us, because saying it plainly beforehand is cheaper than saying it afterwards.',
    file: '/video/two-different-jobs.mp4',
    poster: '/video/two-different-jobs.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 31.5,
    bytes: 18586187,
    uploadDate: '2026-09-05',
    sound: 'Narrated throughout.',
    transcript: [
      'Here is a question we get a lot.',
      'Does the one hundred ninety-nine dollar website come with business email?',
      'It does not.',
      'We would rather say that plainly than let you find out later.',
      'Web Basics is one landing-page website, one year of managed hosting, and your domain, registered new, or connected if you already own it.',
      'After the first year, hosting is nine ninety-nine a month, and the domain renews separately.',
      'Business email is a separate setup. Ninety-nine dollars, one time.',
      'Two different jobs. Ask us which one you need first.',
    ],
    onScreen: [
      {
        beat: 'A question we get a lot',
        lines: [
          'Does the $199 website come with business email?',
          'It does not.',
          'We would rather say that plainly than let you find out later.',
        ],
      },
      {
        beat: 'Web Basics Bundle — $199',
        lines: [
          'Three things.',
          'One landing-page website',
          'One year of managed hosting',
          'Your domain, new or already yours',
        ],
      },
      {
        beat: 'After the first year',
        lines: ['What renews.', 'Hosting $9.99 a month', 'Your domain renews separately'],
      },
      {
        beat: 'Business email setup',
        lines: [
          '$99, one time',
          'A separate setup, on the domain you already own.',
          'You keep your own mailbox provider. We never resell mailboxes.',
        ],
      },
      {
        beat: 'The close',
        lines: [
          'Two different jobs.',
          'Web Basics Bundle — $199 one time',
          'Business Email Setup — $99 one time',
          'Ask us which one you need first.',
        ],
      },
    ],
    art: 'scopeBoundary',
    scope: {
      included: [
        'One landing-page website',
        'One year of managed hosting',
        'Your domain, registered new or connected if you already own it',
      ],
      excluded: ['Business email', 'Maintenance'],
    },
    keywords: ['business email', '$199 website', 'what is included', 'web basics bundle'],
  },

  {
    slug: 'found-then-kept',
    title: 'Found, Then Kept',
    eyebrow: 'Local SEO',
    series: 'What the price is',
    tagline:
      'The shop is open and search finds nothing. One product makes you readable, another keeps you that way. Neither is in the $199 bundle.',
    summary:
      'A 28-second film about what local search can actually read, and the honest limit of what anyone can promise.',
    argument: [
      'The film’s most important line is a footnote: nobody can promise you a ranking, and this is the part that can be done. Everything before it is a list of things a machine can genuinely consume — the name spelled one way everywhere, an address that matches the listings, hours that parse, the services written in words.',
      'It then names two separate products at their own prices and says twice, in as many words, that neither is part of the $199 bundle. It closes by asking which one you need first rather than selling both.',
    ],
    audience:
      'For a business that is open and trading but does not come up in local search, and wants to know what is actually fixable before spending anything.',
    file: '/video/found-then-kept.mp4',
    poster: '/video/found-then-kept.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 28.0,
    bytes: 16154598,
    uploadDate: '2026-09-05',
    sound: 'Narrated throughout.',
    transcript: [
      "The shop is open.",
      "Search finds nothing.",
      "Local search reads facts, not adjectives.",
      "Your name, spelled the same way everywhere.",
      "An address that matches your listings.",
      "Hours a machine can parse.",
      "Nobody can promise you a ranking.",
      "This is the part that can be done.",
      "Local SEO setup is two hundred and ninety-nine dollars, one time.",
      "Maintenance is forty-nine ninety-nine a month.",
      "Two separate products.",
      "Neither one is included in the one hundred and ninety-nine dollar bundle.",
    ],
    onScreen: [
      { beat: 'Get found', lines: ['The shop is open. Search finds nothing.'] },
      {
        beat: 'What local search reads',
        lines: [
          'It reads facts, not adjectives.',
          'The name spelled one way everywhere',
          'An address that matches the listings',
          'Hours a machine can parse',
          'The services, in words',
          'Nobody can promise you a ranking. This is the part that can be done.',
        ],
      },
      {
        beat: 'Local SEO Setup — $299 one time',
        lines: [
          'Structured local data on your site',
          'Your core profiles, set up and consistent',
          'Analytics verified, so you can see the visits',
          'A one-time setup. Not a subscription, and not part of the $199 bundle.',
        ],
      },
      {
        beat: 'Website Maintenance — $49.99 a month',
        lines: [
          'Updates checked',
          'Backups verified',
          'Small content touches',
          'Cancel any time. Its own product, and not part of the $199 bundle.',
        ],
      },
      {
        beat: 'Two separate products',
        lines: [
          'Get found. Then stay found.',
          'famtasticdesigns.com/packages',
          'Neither one is included in the $199 bundle. Ask which you need first.',
        ],
      },
    ],
    art: null,
    keywords: ['local seo', 'local search', 'google business profile', 'website maintenance'],
  },

  /**
   * ------------------------------------------------------------------
   * UGC Character Flood (plans/ugc-character-flood/plan.md, Task T7) —
   * 24 films across three campaigns: signal-and-static (sas-), whats-
   * your-secret (wys-), front-desk (fd-). `campaign` on each entry is
   * what CAMPAIGN_BLOG_SLUGS and campaignFilms() below key off of.
   *
   * Sourcing, so every field traces to a real repo artifact rather than
   * a guess: `onScreen` for signal-and-static is the verbatim
   * headline/subline/price_line from marketing/hyperframes/signal-and-
   * static/rows.json (the actual HyperFrames render input); for
   * whats-your-secret it is rows.json's captionA/captionB plus the
   * disclosure and close-card text read directly out of that project's
   * index.html; for front-desk it is the literal text pulled from each
   * drop's own hook.html/main.html/close.html. `transcript` is set only
   * for signal-and-static (marketing/hyperframes/signal-and-static/
   * narration/lines.json — an explicitly-verbatim kept script) and
   * whats-your-secret (marketing/campaigns/whats-your-secret/evidence/
   * narration-scripts/wys-drop-0N.txt, also verbatim). front-desk has no
   * verbatim narration-script file in repo, so per this file's own rule
   * its transcript is null rather than reconstructed from the answer
   * captions.
   * ------------------------------------------------------------------
   */
  {
    slug: 'sas-drop-01',
    title: 'A borrowed inbox is not your signal',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'A rented sign is not your signal.',
    summary: 'A 15-second film in the Signal and Static series — a Gmail address and a link-in-bio page still run through somebody else’s platform.',
    argument: [
      'Theo opens on the two tools almost every small business already uses to be reachable — a Gmail inbox and a link-in-bio page — and names what they actually are: a borrowed inbox and a borrowed shelf, both running through somebody else’s platform.',
      'The film does not say either tool is broken. It says they work fine right up until the platform changes its rules or its price, and that a business reachable only through them has no address of its own to fall back on.',
    ],
    audience: 'For a business whose entire public presence is a Gmail address and a link-in-bio page, and has never had a reason to ask what happens if either one changes.',
    file: '/video/sas-drop-01.mp4',
    poster: '/video/sas-drop-01.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 13071800,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'A Gmail address and a link-in-bio page still run through somebody else’s platform, not yours.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['A borrowed inbox is not your signal.'] },
      { beat: 'The subline', lines: ['A Gmail address and a link-in-bio page still run through somebody else’s platform.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'A rented sign is not your signal.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/why-running-business-on-gmail-and-linktree-costs-revenue/'] },
    ],
    art: 'ownedVsRented',
    keywords: ['gmail business address', 'link in bio', 'own your domain', 'small business website'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-02',
    title: 'A link page is a rented signal',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'A link page is a rented signal.',
    summary: 'A 15-second film in the Signal and Static series — it works until the platform changes what it lets you say.',
    argument: [
      'The argument narrows to one specific tool: a link-in-bio page. It routes people somewhere, and does that one job cleanly — but it is a frequency you are borrowing, not one you hold.',
      'Theo’s line is structural, not alarmist: the layout, the rules about what you can say, and whether the page shows up at all belong to the platform, and can change without you being asked first.',
    ],
    audience: 'For a business using a link-in-bio page as its whole online presence, weighing whether it is enough or just convenient.',
    file: '/video/sas-drop-02.mp4',
    poster: '/video/sas-drop-02.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 13022197,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'A link-in-bio page works right up until the platform changes what it lets you say.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['A link page is a rented signal.'] },
      { beat: 'The subline', lines: ['It works until the platform changes what it lets you say.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'A link page is a rented signal.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/linktree-vs-real-website-what-you-trade-away/'] },
    ],
    art: 'ownedVsRented',
    keywords: ['linktree alternative', 'link in bio page', 'rented platform', 'own website'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-03',
    title: 'Keep the app. Add your own signal',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'Keep the app. Add your own signal.',
    summary: 'A 15-second film in the Signal and Static series — you don’t have to leave a booking app to also have a page that’s yours.',
    argument: [
      'This drop heads off the false choice directly: keeping a booking app and having a page of your own are not in competition. The app is good at demand it created for itself; a page of your own is good at demand you created yourself.',
      'Theo states it as an addition, not a replacement — the app stays exactly as it is, and one address the platform doesn’t control sits alongside it.',
    ],
    audience: 'For a business booked entirely through a marketplace app, worried that having a website of their own would mean giving up what already works.',
    file: '/video/sas-drop-03.mp4',
    poster: '/video/sas-drop-03.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 13008299,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'You do not have to leave the booking app. You just need one address the app doesn’t control.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['Keep the app. Add your own signal.'] },
      { beat: 'The subline', lines: ['You don’t have to leave a booking app to also have a page that’s yours.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'Keep the app. Add your own signal.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/do-you-have-to-leave-the-booking-app/'] },
    ],
    art: null,
    keywords: ['booking app', 'do i need a website', 'keep booking app', 'own domain'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-04',
    title: 'Who’s actually holding your client list?',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'Who’s actually holding your client list?',
    summary: 'A 15-second film in the Signal and Static series — a platform directory is not the same thing as a list you own.',
    argument: [
      'The question turns from the page itself to what sits behind it: the client list. Theo draws the line between being able to see your clients inside a platform’s directory and actually owning that list yourself.',
      'If the account changes terms, raises its cut, or disappears, a directory entry goes with it — a list you hold does not.',
    ],
    audience: 'For a business that has never separated “can I see my clients in this app” from “do I actually own this list.”',
    file: '/video/sas-drop-04.mp4',
    poster: '/video/sas-drop-04.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 12904278,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'A platform’s directory is not the same thing as a client list you own.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['Who’s actually holding your client list?'] },
      { beat: 'The subline', lines: ['A platform directory is not the same thing as a list you own.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'Who’s actually holding your client list?', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/who-owns-your-client-list-booking-app/'] },
    ],
    art: 'ownershipMarker',
    keywords: ['who owns client list', 'booking app data', 'client list ownership', 'own your customers'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-05',
    title: 'One page. One signal. Yours',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'One page. One signal. Yours.',
    summary: 'A 15-second film in the Signal and Static series — one page, one year of hosting, a domain that’s yours — the whole bundle.',
    argument: [
      'Price appears for the first time in the campaign, stated as the whole bundle rather than a teaser: one page, one year of managed hosting, and a domain that’s yours — new, or one you already own — for $199 one time.',
      'The same card discloses what happens after: $9.99 a month from year two, plus the separate cost of renewing the domain. Nothing above the fold implies otherwise.',
    ],
    audience: 'For anyone who has followed the argument this far and now wants the number and the scope on one screen.',
    file: '/video/sas-drop-05.mp4',
    poster: '/video/sas-drop-05.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 13223039,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'One hundred ninety-nine dollars. One page, one year of hosting, a domain that’s yours. That’s the whole bundle.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['One page. One signal. Yours.'] },
      { beat: 'The subline', lines: ['One page, one year of hosting, a domain that’s yours — the whole bundle.'] },
      { beat: 'Price card', lines: ['$199 one-time: one page, one year hosting, first-year domain. Then $9.99/mo, plus the domain.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'One page. One signal. Yours.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/199-website-inclusions-and-boundaries/'] },
    ],
    art: 'scopeBoundary',
    scope: {
      included: ['One focused landing-page website', 'One year of managed hosting', 'First-year domain — new, or connect one you already own'],
      excluded: ['Business email', 'Maintenance'],
    },
    keywords: ['$199 website', 'web basics bundle', 'what does 199 include', 'website cost'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-06',
    title: 'The signal doesn’t cut off in year two',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'The signal doesn’t cut off in year two.',
    summary: 'A 15-second film in the Signal and Static series — the terms for what happens after year one, in writing, up front.',
    argument: [
      'The renewal terms get their own drop rather than a footnote: first year $199, then $9.99 a month if you keep the hosting, plus whatever the domain itself costs to renew.',
      'Theo’s point is that none of this should arrive as a surprise email a year later — it’s disclosed at the same time as the price, not after it.',
    ],
    audience: 'For anyone deciding whether to buy who wants to know what happens after year one before, not after, they pay anything.',
    file: '/video/sas-drop-06.mp4',
    poster: '/video/sas-drop-06.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 13073020,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'First year is one hundred ninety-nine dollars. After that, nine dollars and ninety-nine cents a month, plus the domain.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['The signal doesn’t cut off in year two.'] },
      { beat: 'The subline', lines: ['The terms for what happens after year one, in writing, up front.'] },
      { beat: 'Price card', lines: ['First year $199, then $9.99/mo, plus renewing the domain. No surprise renewal.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'The signal doesn’t cut off in year two.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/what-happens-when-first-year-hosting-ends/'] },
    ],
    art: null,
    keywords: ['hosting renewal cost', 'what happens after first year hosting', 'domain renewal', 'website subscription'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-07',
    title: 'A rented sign doesn’t reach Google',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'A rented sign doesn’t reach Google.',
    summary: 'A 15-second film in the Signal and Static series — a link-in-bio page is often invisible to search entirely.',
    argument: [
      'The argument moves from ownership to visibility: a link-in-bio page is frequently invisible to a search engine entirely, because it was built to be linked to from one place, not to be found on its own.',
      'Theo does not promise a ranking — only that a page built to be found is a structurally different thing than one built only to redirect.',
    ],
    audience: 'For a business relying on a link-in-bio page who has never checked whether it shows up in a search for their own name.',
    file: '/video/sas-drop-07.mp4',
    poster: '/video/sas-drop-07.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 12912776,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'A link-in-bio page is often invisible to search entirely. It was never built to be found.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['A rented sign doesn’t reach Google.'] },
      { beat: 'The subline', lines: ['A link-in-bio page is often invisible to search entirely.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'A rented sign doesn’t reach Google.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/why-link-in-bio-page-doesnt-show-up-in-google/'] },
    ],
    art: null,
    keywords: ['does linktree show up on google', 'link in bio seo', 'website search visibility', 'small business seo'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'sas-drop-08',
    title: 'See your signal before you pay for it',
    eyebrow: 'Own the address',
    series: 'Own the address',
    tagline: 'See your signal before you pay for it.',
    summary: 'A 15-second film in the Signal and Static series — a real preview, built before you commit to anything.',
    argument: [
      'The campaign closes on process rather than argument: a real preview of the page gets built before any commitment, which Theo frames as proof rather than a sales promise.',
      'It is the practical answer to everything the first seven drops raised — you do not have to take the mechanism on faith before seeing what your own page would actually look like.',
    ],
    audience: 'For anyone who agrees with the argument but wants to see their own page before deciding anything.',
    file: '/video/sas-drop-08.mp4',
    poster: '/video/sas-drop-08.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 15.0,
    bytes: 12855354,
    uploadDate: '2026-09-06',
    sound: 'Narrated throughout (Voicebox), AAC 48kHz stereo.',
    transcript: [
      'See it built before you pay for it. That’s proof, not a promise.',
    ],
    onScreen: [
      { beat: 'The headline', lines: ['See your signal before you pay for it.'] },
      { beat: 'The subline', lines: ['A real preview, built before you commit to anything.'] },
      { beat: 'The close', lines: ['FAMTASTIC', 'See your signal before you pay for it.', 'famtasticdesigns.com', 'famtasticdesigns.com/blog/proof-first-website-see-before-you-pay/'] },
    ],
    art: null,
    keywords: ['see website before you pay', 'proof first website', 'website preview', 'try before you buy website'],
    campaign: 'signal-and-static',
  },

  {
    slug: 'wys-drop-01',
    title: 'Corey: “Since when do you have a website?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Corey: “Since when do you have a website?” Malik: “Couple months. Why?”',
    summary: 'A 13.5-second dramatization of Corey and Malik at a gym floor at dusk — one notices a website, the other explains it, plainly.',
    argument: [
      'Malik mentions, almost in passing, that he has had a website for a couple of months. Corey’s question is not about results — it is simple notice that something changed, which is the only kind of observation this campaign ever makes.',
      'Nothing is claimed about what the page has done for Malik’s business. The film’s whole premise is the presence of an owned address, never a performance behind it.',
    ],
    audience: 'For a reader who relates to Malik’s position — has the page already — or to Corey’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-01.mp4',
    poster: '/video/wys-drop-01.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 9450903,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Corey asks / Malik answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Corey: Since when do you have a website?',
      'Malik: Couple months. Why?',
    ],
    onScreen: [
      { beat: 'Corey asks', lines: ['Since when do you have a website?'] },
      { beat: 'Malik answers', lines: ['Couple months. Why?'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'see what’s included'] },
    ],
    art: null,
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-02',
    title: 'Corey: “What’s it even for?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Corey: “What’s it even for?” Malik: “It’s my own spot online. FAMtastic built it.”',
    summary: 'A 13.5-second dramatization of Corey and Malik at a gym floor at dusk — one notices a website, the other explains it, plainly.',
    argument: [
      'Malik’s answer names the thing plainly: a spot online that is his, not a bigger story than that. It is a page he owns, sitting at an address that belongs to him, instead of a profile he is renting from somebody else’s app.',
      'FAMtastic is named as who built it, not as the source of any outcome — the distinction §F rule 1 requires this campaign to hold in every drop.',
    ],
    audience: 'For a reader who relates to Malik’s position — has the page already — or to Corey’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-02.mp4',
    poster: '/video/wys-drop-02.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 9479991,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Corey asks / Malik answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Corey: What’s it even for?',
      'Malik: It’s my own spot online. FAMtastic built it.',
    ],
    onScreen: [
      { beat: 'Corey asks', lines: ['What’s it even for?'] },
      { beat: 'Malik answers', lines: ['It’s my own spot online. FAMtastic built it.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'see what’s included'] },
    ],
    art: 'ownedVsRented',
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-03',
    title: 'Corey: “Isn’t that just a Linktree?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Corey: “Isn’t that just a Linktree?” Malik: “No — it’s a real site. An address, not a link list.”',
    summary: 'A 13.5-second dramatization of Corey and Malik at a gym floor at dusk — one notices a website, the other explains it, plainly.',
    argument: [
      'Corey asks the question a lot of people would actually ask: isn’t a website just a fancier link-in-bio page? Malik’s answer draws the real distinction — a link-in-bio page points at other people’s platforms, a site is a page he controls that a search engine can actually index.',
      'It sits at a domain with his name on it, which a list of links never does.',
    ],
    audience: 'For a reader who relates to Malik’s position — has the page already — or to Corey’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-03.mp4',
    poster: '/video/wys-drop-03.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 10664893,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Corey asks / Malik answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Corey: Isn’t that just a Linktree?',
      'Malik: No — it’s a real site. An address, not a link list.',
    ],
    onScreen: [
      { beat: 'Corey asks', lines: ['Isn’t that just a Linktree?'] },
      { beat: 'Malik answers', lines: ['No — it’s a real site. An address, not a link list.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'linktree vs. a real site'] },
    ],
    art: 'ownershipMarker',
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-04',
    title: 'Corey: “So what’s the catch?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Corey: “So what’s the catch?” Malik: “No catch. One page, a year hosted, the domain. $199.”',
    summary: 'A 13.5-second dramatization of Corey and Malik at a gym floor at dusk — one notices a website, the other explains it, plainly.',
    argument: [
      'The scene ends where every honest pricing conversation should: with the whole scope on the table. Malik states it as a flat list — one page, a year of hosting, the domain — for $199, with no hidden tier and nothing bundled in that wasn’t asked for.',
      'It is the entire pitch, stated plainly rather than qualified.',
    ],
    audience: 'For a reader who relates to Malik’s position — has the page already — or to Corey’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-04.mp4',
    poster: '/video/wys-drop-04.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 10723017,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Corey asks / Malik answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Corey: So what’s the catch?',
      'Malik: No catch. One page, a year hosted, the domain. $199.',
    ],
    onScreen: [
      { beat: 'Corey asks', lines: ['So what’s the catch?'] },
      { beat: 'Malik answers', lines: ['No catch. One page, a year hosted, the domain. $199.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'start your own page'] },
    ],
    art: 'scopeBoundary',
    scope: {
      included: ['One focused landing-page website', 'One year of managed hosting', 'First-year domain — new, or connect one you already own'],
      excluded: ['Business email', 'Maintenance'],
    },
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-05',
    title: 'Dale: “Wait, you have a website now?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Dale: “Wait, you have a website now?” Wyatt: “Yeah. Got it a few months back.”',
    summary: 'A 13.5-second dramatization of Dale and Wyatt at a truck tailgate at golden hour, end of shift — one notices a website, the other explains it, plainly.',
    argument: [
      'The same notice beat, rewritten for a different trade and a different idiom entirely — end of shift, a truck tailgate, golden hour. Dale’s surprise is the same shape as Corey’s, but the words and the setting are specific to two people who work with their hands.',
      'Nothing about the exchange implies a result. It is a fact being noticed, nothing more.',
    ],
    audience: 'For a reader who relates to Wyatt’s position — has the page already — or to Dale’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-05.mp4',
    poster: '/video/wys-drop-05.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 14105009,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Dale asks / Wyatt answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Dale: Wait, you have a website now?',
      'Wyatt: Yeah. Got it a few months back.',
    ],
    onScreen: [
      { beat: 'Dale asks', lines: ['Wait, you have a website now?'] },
      { beat: 'Wyatt answers', lines: ['Yeah. Got it a few months back.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'see what’s included'] },
    ],
    art: null,
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-06',
    title: 'Dale: “What’s it actually do?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Dale: “What’s it actually do?” Wyatt: “It’s my own page. FAMtastic built it, one flat price.”',
    summary: 'A 13.5-second dramatization of Dale and Wyatt at a truck tailgate at golden hour, end of shift — one notices a website, the other explains it, plainly.',
    argument: [
      'Wyatt’s answer is deliberately plainer than Malik’s — “my own page, one flat price” — matching the idiom of a contractor rather than restating the gym-floor version with the trade swapped out.',
      'It is a place people land when they look him up, belonging to him and not to anyone else’s platform.',
    ],
    audience: 'For a reader who relates to Wyatt’s position — has the page already — or to Dale’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-06.mp4',
    poster: '/video/wys-drop-06.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 14165154,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Dale asks / Wyatt answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Dale: What’s it actually do?',
      'Wyatt: It’s my own page. FAMtastic built it, one flat price.',
    ],
    onScreen: [
      { beat: 'Dale asks', lines: ['What’s it actually do?'] },
      { beat: 'Wyatt answers', lines: ['It’s my own page. FAMtastic built it, one flat price.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'see what’s included'] },
    ],
    art: 'ownedVsRented',
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-07',
    title: 'Dale: “Isn’t that what the booking app already gives me?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Dale: “Isn’t that what the booking app already gives me?” Wyatt: “The app’s not yours. This page is.”',
    summary: 'A 13.5-second dramatization of Dale and Wyatt at a truck tailgate at golden hour, end of shift — one notices a website, the other explains it, plainly.',
    argument: [
      'Dale raises the objection this whole flood keeps circling back to from a different angle: doesn’t a booking app already cover this? Wyatt’s answer is the shortest, sharpest version of the ownership argument in the entire campaign — the app hands you access as long as you keep using it, the page is yours whether you keep the app or not.',
    ],
    audience: 'For a reader who relates to Wyatt’s position — has the page already — or to Dale’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-07.mp4',
    poster: '/video/wys-drop-07.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 14189034,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Dale asks / Wyatt answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Dale: Isn’t that what the booking app already gives me?',
      'Wyatt: The app’s not yours. This page is.',
    ],
    onScreen: [
      { beat: 'Dale asks', lines: ['Isn’t that what the booking app already gives me?'] },
      { beat: 'Wyatt answers', lines: ['The app’s not yours. This page is.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'who owns your client list'] },
    ],
    art: 'ownershipMarker',
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'wys-drop-08',
    title: 'Dale: “What’s it run you?”',
    eyebrow: 'What’s Your Secret',
    series: 'What the price is',
    tagline: 'Dale: “What’s it run you?” Wyatt: “$199. Site, hosting, the domain — one year.”',
    summary: 'A 13.5-second dramatization of Dale and Wyatt at a truck tailgate at golden hour, end of shift — one notices a website, the other explains it, plainly.',
    argument: [
      'The v2 pair lands on the same close as v1: the whole bundle, stated plainly. One focused page, a year of hosting, and the domain, for $199 up front — no different a number for a different trade, no upsell folded into the answer.',
    ],
    audience: 'For a reader who relates to Wyatt’s position — has the page already — or to Dale’s — is only now noticing everyone around them seems to have one.',
    file: '/video/wys-drop-08.mp4',
    poster: '/video/wys-drop-08.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 13.5,
    bytes: 14169072,
    uploadDate: '2026-09-06',
    sound: 'Dialogue captioned on screen (Dale asks / Wyatt answers), voiced by a single shared narrator reading both parts; no lip-sync.',
    transcript: [
      'Dale: What’s it run you?',
      'Wyatt: $199. Site, hosting, the domain — one year.',
    ],
    onScreen: [
      { beat: 'Dale asks', lines: ['What’s it run you?'] },
      { beat: 'Wyatt answers', lines: ['$199. Site, hosting, the domain — one year.'] },
      { beat: 'Disclosure', lines: ['Dramatization. Characters are illustrative — not real customers.'] },
      { beat: 'The close', lines: ['FAMtastic', 'One page · one year hosting · the domain', 'First year only. Then $9.99/mo, plus the domain’s own renewal cost.', 'start your own page'] },
    ],
    art: 'scopeBoundary',
    scope: {
      included: ['One focused landing-page website', 'One year of managed hosting', 'First-year domain — new, or connect one you already own'],
      excluded: ['Business email', 'Maintenance'],
    },
    keywords: ['what does 199 include', 'website vs linktree', 'small business website dramatization', 'own website ownership'],
    campaign: 'whats-your-secret',
  },

  {
    slug: 'fd-drop-01',
    title: 'Do I have to leave my booking app?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'She’s filling your calendar. That’s the job. The real question is what happens if it stops.',
    summary: 'A 19-second Front Desk drop: Priya answers “Do I have to leave my booking app?” in one direct take.',
    argument: [
      'Priya answers the objection at the front of this whole flood: no, you do not have to leave your booking app. If it is filling your calendar, it is doing its job, and a channel that brings paying work is not a problem to be solved.',
      'The real question, she says, is what happens if it ever stops — a policy change, a billing lapse, a category reshuffle — and what is left standing is only what you own outside it.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-01.mp4',
    poster: '/video/fd-drop-01.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12821552,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['Do I have to leave my booking app?'] },
      { beat: 'The answer', lines: ['She’s filling your calendar. That’s the job.', 'The real question is what happens if it stops.'] },
      { beat: 'The close', lines: ['Read the full answer', 'The address you’d still own.', 'famtasticdesigns.com/blog/do-you-have-to-leave-the-booking-app/', 'FAMtastic Web Basics: $199 first year (site + hosting + domain), then $9.99/mo hosting, plus domain renewal.'] },
    ],
    art: null,
    keywords: ['front desk faq', 'do you have to leave the booking app', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-02',
    title: 'Who actually owns my client list?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'You can see it, often export some of it. But access is a permission the platform grants — not ownership.',
    summary: 'A 19-second Front Desk drop: Priya answers “Who actually owns my client list?” in one direct take.',
    argument: [
      'Priya draws a distinction most people booking through an app have never had reason to make: being able to see your client list, and export some of it, is not the same thing as owning it.',
      'What any specific platform actually lets you do is set by the agreement you accepted — and the only reliable way to know yours, she says, is to read it.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-02.mp4',
    poster: '/video/fd-drop-02.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12874136,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['Who actually owns my client list?'] },
      { beat: 'The answer', lines: ['You can see it, often export some of it.', 'But access is a permission the platform grants — not ownership.'] },
      { beat: 'The close', lines: ['Read the full answer', 'Access isn’t ownership.', 'famtasticdesigns.com/blog/who-owns-your-client-list-booking-app/', 'FAMtastic Web Basics: websites start at $199. Renewal terms disclosed before anything renews.'] },
    ],
    art: 'ownershipMarker',
    keywords: ['front desk faq', 'who owns your client list booking app', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-03',
    title: 'Do you guarantee Google rankings?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'No. Nobody honest does. We build the technical foundation — and show you proof.',
    summary: 'A 19-second Front Desk drop: Priya answers “Do you guarantee Google rankings?” in one direct take.',
    argument: [
      'Asked the question directly, Priya answers directly: no. Nobody honest guarantees a Google ranking, because no outside vendor controls the algorithm that decides it, and a vendor who promises a specific position is either promising something outside their control or quietly narrowing that promise later.',
      'What can actually be built is the real technical foundation a search engine needs to evaluate a site at all — and shown as proof, not asserted as a promise.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-03.mp4',
    poster: '/video/fd-drop-03.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12817730,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['Do you guarantee Google rankings?'] },
      { beat: 'The answer', lines: ['No. Nobody honest does.', 'We build the technical foundation — and show you proof.'] },
      { beat: 'The close', lines: ['Read the full answer', 'Proof, not a promise.', 'famtasticdesigns.com/blog/do-you-guarantee-google-rankings/', 'FAMtastic Web Basics: websites start at $199. Renewal terms disclosed before anything renews.'] },
    ],
    art: null,
    keywords: ['front desk faq', 'do you guarantee google rankings', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-04',
    title: 'What does the $199 actually include?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'One website, first-year hosting, and your domain. That’s the whole bundle — nothing hidden after.',
    summary: 'A 19-second Front Desk drop: Priya answers “What does the $199 actually include?” in one direct take.',
    argument: [
      'Priya states the scope of the $199 offer as a flat list rather than a pitch: one focused website, first-year hosting, and a domain — new, or one already owned. That is the whole bundle.',
      'After year one, hosting is $9.99 a month, and only starts once the customer separately approves it; the domain renews on its own line, at whatever the registrar actually charges.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-04.mp4',
    poster: '/video/fd-drop-04.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12899456,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['What does the $199 actually include?'] },
      { beat: 'The answer', lines: ['One website, first-year hosting, and your domain.', 'That’s the whole bundle — nothing hidden after.'] },
      { beat: 'The close', lines: ['Read the full answer', 'The whole bundle, in writing.', 'famtasticdesigns.com/blog/199-website-inclusions-and-boundaries/', '$199 first year: the site, hosting, and the domain. Then $9.99/mo hosting, plus domain renewal — disclosed before anything renews.'] },
    ],
    art: 'scopeBoundary',
    scope: {
      included: ['One focused landing-page website', 'One year of managed hosting', 'First-year domain — new, or connect one you already own'],
      excluded: ['Business email', 'Maintenance'],
    },
    keywords: ['front desk faq', '199 website inclusions and boundaries', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-05',
    title: 'What happens after my first year of hosting?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'Nothing charges automatically. Nothing. Hosting renews only after you separately approve it.',
    summary: 'A 19-second Front Desk drop: Priya answers “What happens after my first year of hosting?” in one direct take.',
    argument: [
      'Priya answers a question every prepaid-first-year offer eventually earns: nothing charges automatically, and nothing gets billed without explicit, separate say-so first.',
      'At month thirteen, hosting becomes $9.99 a month for Web Basics sites — but only after the customer approves it — and the domain renews on its own line, disclosed before payment, at whatever the registrar charges.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-05.mp4',
    poster: '/video/fd-drop-05.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12941776,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['What happens after my first year of hosting?'] },
      { beat: 'The answer', lines: ['Nothing charges automatically. Nothing.', 'Hosting renews only after you separately approve it.'] },
      { beat: 'The close', lines: ['Read the full answer', 'Nothing renews without your say-so.', 'famtasticdesigns.com/blog/what-happens-when-first-year-hosting-ends/', 'Hosting: $9.99/mo starting month 13, only with your approval. Domain renewal billed separately, at the registrar’s cost.'] },
    ],
    art: null,
    keywords: ['front desk faq', 'what happens when first year hosting ends', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-06',
    title: 'Why does “DM me for pricing” cost me bookings?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'Picture a shop with no price tags. Every stranger who has to ask first is a chance to lose the booking.',
    summary: 'A 19-second Front Desk drop: Priya answers why “DM me for pricing” costs bookings, in one direct take.',
    argument: [
      'Priya reaches for a physical image rather than a statistic: a shop with no price tags, where everything is good and the owner knows the stock cold, but learning what anything costs means finding the owner and asking out loud in front of strangers.',
      'Some people will do it. Most will pick it up, put it back, and leave without speaking — and a profile that says “DM for pricing” is exactly that shop.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-06.mp4',
    poster: '/video/fd-drop-06.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12961139,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['Why does “DM me for pricing” cost me bookings?'] },
      { beat: 'The answer', lines: ['Picture a shop with no price tags.', 'Every stranger who has to ask first is a chance to lose the booking.'] },
      { beat: 'The close', lines: ['Read the full answer', 'Answer it before they ask.', 'famtasticdesigns.com/blog/how-much-do-you-charge-dms-costs-bookings/', 'FAMtastic Web Basics: websites start at $199. Renewal terms disclosed before anything renews.'] },
    ],
    art: null,
    keywords: ['front desk faq', 'how much do you charge dms costs bookings', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-07',
    title: 'What’s actually wrong with just using Linktree?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'Nothing, if it’s routing people somewhere. But it’s a hallway — it has nothing for them once they arrive.',
    summary: 'A 19-second Front Desk drop: Priya answers “What’s actually wrong with just using Linktree?” in one direct take.',
    argument: [
      'Priya gives Linktree real credit before drawing the line: nothing is wrong with it if it is routing people somewhere — it is fast, free, and solves the one-link problem cleanly, and it is fine to keep using it if it currently works.',
      'But it is a hallway, not a room. It moves people in the right direction and then has nothing for them once they arrive — no prices, no proof, no address that is actually theirs.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-07.mp4',
    poster: '/video/fd-drop-07.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12879365,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['What’s actually wrong with just using Linktree?'] },
      { beat: 'The answer', lines: ['Nothing, if it’s routing people somewhere.', 'But it’s a hallway — it has nothing for them once they arrive.'] },
      { beat: 'The close', lines: ['Read the full answer', 'A room, not a hallway.', 'famtasticdesigns.com/blog/linktree-vs-real-website-what-you-trade-away/', 'FAMtastic Web Basics: websites start at $199. Renewal terms disclosed before anything renews.'] },
    ],
    art: 'ownershipMarker',
    keywords: ['front desk faq', 'linktree vs real website what you trade away', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },

  {
    slug: 'fd-drop-08',
    title: 'Why is my Gmail address costing me business?',
    eyebrow: 'Front Desk',
    series: 'Front Desk FAQ',
    tagline: 'A Gmail address and a Linktree page both work. Neither one is yours — and that costs bookings.',
    summary: 'A 19-second Front Desk drop: Priya answers “Why is my Gmail address costing me business?” in one direct take.',
    argument: [
      'The closing drop ties the whole campaign’s argument to the two tools almost everyone starts with: a Gmail address and a Linktree page both genuinely work, and neither one is owned by the business using it.',
      'When someone has to message first just to ask what something costs, Priya notes, some of them will book with whoever answers first — and that has nothing to do with whose work is better.',
    ],
    audience: 'For a business booked through an app or working from a Gmail/Linktree setup who has this exact question and has never had it answered on camera.',
    file: '/video/fd-drop-08.mp4',
    poster: '/video/fd-drop-08.jpg',
    width: 1080,
    height: 1920,
    durationSeconds: 19.0,
    bytes: 12935995,
    uploadDate: '2026-09-06',
    sound: 'Hook and close cards are silent; the answer beat carries a Voicebox voiceover over progressive captions, AAC 48kHz stereo.',
    transcript: null,
    onScreen: [
      { beat: 'Front Desk · FAQ', lines: ['Why is my Gmail address costing me business?'] },
      { beat: 'The answer', lines: ['A Gmail address and a Linktree page both work.', 'Neither one is yours — and that costs bookings.'] },
      { beat: 'The close', lines: ['Read the full answer', 'An address that’s actually yours.', 'famtasticdesigns.com/blog/why-running-business-on-gmail-and-linktree-costs-revenue/', 'FAMtastic Web Basics: websites start at $199. Renewal terms disclosed before anything renews.'] },
    ],
    art: 'ownedVsRented',
    keywords: ['front desk faq', 'why running business on gmail and linktree costs revenue', 'small business website questions', 'famtastic web basics'],
    campaign: 'front-desk',
  },
];

export const FILM_SERIES = [...new Set(FILMS.map((film) => film.series))];

export function getFilm(slug) {
  return FILMS.find((film) => film.slug === slug) || null;
}

export function filmPath(film) {
  return `${WATCH_BASE}/${film.slug}`;
}

export function filmCanonical(film) {
  return `${SITE_URL}${WATCH_BASE}/${film.slug}/`;
}

/**
 * The meta description a film page ships with.
 *
 * Deliberately built from `summary` + `audience` rather than the first line of
 * `argument`: a description is a promise about who the page is for, and "who it
 * is for" is the half a searcher is actually deciding on.
 */
export function filmMetaDescription(film) {
  return `${film.summary} ${film.audience.replace(/^For /, 'For ')}`.replace(/\s+/g, ' ').trim().slice(0, 300);
}

export function filmSeoTitle(film) {
  return `${film.title} | Films | FAMtastic Designs`;
}

/**
 * VideoObject for one film — the entire reason this route exists.
 *
 * Every required property is present and every one of them is real:
 * `duration` is ffprobe-derived, `thumbnailUrl` points at a JPEG extracted from
 * the film itself, and `contentUrl`/`embedUrl` both resolve because the MP4 is
 * served from our own document root. `transcript` is included only where a
 * verified verbatim script exists (see the module header) — a film with a real
 * transcript is the one that can actually rank, and a film with a paraphrased
 * one is a liability.
 */
export function filmVideoObject(film) {
  const canonical = filmCanonical(film);
  return {
    '@type': 'VideoObject',
    '@id': `${canonical}#video`,
    name: film.title,
    description: `${film.summary} ${film.argument[0]}`.replace(/\s+/g, ' ').trim(),
    thumbnailUrl: [`${SITE_URL}${film.poster}`],
    uploadDate: film.uploadDate,
    duration: isoDuration(film.durationSeconds),
    contentUrl: `${SITE_URL}${film.file}`,
    embedUrl: canonical,
    encodingFormat: 'video/mp4',
    width: { '@type': 'QuantitativeValue', value: film.width, unitCode: 'E37' },
    height: { '@type': 'QuantitativeValue', value: film.height, unitCode: 'E37' },
    contentSize: `${film.bytes}`,
    inLanguage: 'en-US',
    isFamilyFriendly: true,
    keywords: film.keywords.join(', '),
    creator: { '@id': `${SITE_URL}/#organization` },
    publisher: { '@id': `${SITE_URL}/#organization` },
    mainEntityOfPage: canonical,
    ...(film.transcript ? { transcript: film.transcript.join(' ') } : {}),
  };
}

/** The hub's ItemList — one entry per film, in the catalog's viewing order. */
export function watchItemList() {
  return {
    '@type': 'ItemList',
    '@id': `${SITE_URL}${WATCH_BASE}/#films`,
    name: 'FAMtastic Designs films',
    numberOfItems: FILMS.length,
    itemListElement: FILMS.map((film, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      url: filmCanonical(film),
      name: film.title,
    })),
  };
}

/**
 * ------------------------------------------------------------------
 * Campaign film embeds (T7, plans/ugc-character-flood/plan.md).
 *
 * Each of the three successful UGC-flood campaigns gets exactly one
 * companion blog post that embeds its own 8 films as a gallery. This
 * mirrors BlogPostPage.jsx's pre-existing `campaignBodyHtml` precedent
 * for the '55 Cents a Day' series (a fixed figure injected after a
 * specific paragraph) but is written here, in the plain-data module,
 * so BOTH consumers render byte-identical markup:
 *   1. BlogPostPage.jsx (client) — via dangerouslySetInnerHTML.
 *   2. scripts/generate-seo-shells.mjs (Node, build time) — via its
 *      blog_post body path (fieldMarkup()), so the gallery is present
 *      in the prerendered shell a crawler sees, not only after
 *      hydration.
 * ------------------------------------------------------------------
 */
export const CAMPAIGN_BLOG_SLUGS = {
  'signal-and-static': 'own-your-signal-not-a-rented-one',
  'whats-your-secret': 'the-secret-is-a-page-of-your-own',
  'front-desk': 'real-answers-to-the-questions-we-actually-get',
};

const BLOG_SLUG_TO_CAMPAIGN = Object.fromEntries(
  Object.entries(CAMPAIGN_BLOG_SLUGS).map(([campaign, slug]) => [slug, campaign]),
);

/** Which campaign (if any) a blog post slug is the companion article for. */
export function campaignForBlogSlug(slug) {
  return BLOG_SLUG_TO_CAMPAIGN[slug] || null;
}

/** Every film belonging to one campaign, in the order they were shot. */
export function campaignFilms(campaignId) {
  return FILMS.filter((film) => film.campaign === campaignId);
}

function escapeAttr(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

/**
 * The film-gallery `<figure>` embedded into a campaign's companion post.
 * Pure string building (no DOM, no JSX) so it works identically from a
 * Node build script and from React's dangerouslySetInnerHTML.
 */
export function campaignFilmGalleryHtml(campaignId) {
  const films = campaignFilms(campaignId);
  if (!films.length) return '';
  const cards = films
    .map(
      (film) =>
        `<a class="film-strip__card" href="${WATCH_BASE}/${film.slug}/">` +
        `<img src="${film.poster}" alt="${escapeAttr(film.title)}" width="270" height="480" loading="lazy">` +
        `<span class="film-strip__title">${escapeAttr(film.title)}</span>` +
        `<span class="film-strip__runtime">${escapeAttr(runningTime(film.durationSeconds))}</span>` +
        `</a>`,
    )
    .join('');
  return (
    `<figure class="article-inline-visual article-inline-visual--films">` +
    `<div class="film-strip">${cards}</div>` +
    `<figcaption><img src="/brand/famtastic-mark.svg" alt="" width="28" height="28">` +
    `FAMtastic Designs — watch all ${films.length} short films made for this series at ` +
    `<a href="${WATCH_BASE}">${WATCH_BASE}</a>.</figcaption>` +
    `</figure>`
  );
}

/**
 * Insert a campaign's film gallery into a blog post's body HTML, after the
 * second paragraph (or appended at the end for a body with fewer than two).
 * Pure string transform — shared verbatim by BlogPostPage.jsx (client render)
 * and scripts/generate-seo-shells.mjs (prerendered shell body), which is the
 * whole point: a crawler and a browser must see byte-identical markup.
 */
export function injectCampaignFilmGallery(bodyHtml, campaignId) {
  if (!bodyHtml) return bodyHtml || '';
  const gallery = campaignFilmGalleryHtml(campaignId);
  if (!gallery) return bodyHtml;
  let paragraph = 0;
  let injected = false;
  const withGallery = bodyHtml.replace(/<\/p>/g, (closing) => {
    paragraph += 1;
    if (!injected && paragraph === 2) {
      injected = true;
      return closing + gallery;
    }
    return closing;
  });
  return injected ? withGallery : withGallery + gallery;
}
