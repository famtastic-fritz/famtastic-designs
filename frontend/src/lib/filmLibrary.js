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
