# Social profile icon kit — September 17, 2026

Owner request: full finalized logo preferred; if too small for profile use, FAM
with crown. The supplied Downloads/fav-icon1.png is a prior direction/reference,
not the new canonical source. No generation or logo redraw is authorized.

## Source and output

Original: frontend/public/brand/famtastic-designs-logo-v1.png, immutable SHA256
ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950.
Crown: canonical source-derived famtastic-crown-flat.png, same as brand primitive.
Full variant preserves the entire lockup. Compact variant extracts the original
red/gold/blue FAM pixels, omits neighboring lettering/tagline and composites the
canonical crown above the M. No missing artwork is invented or relettered.
The extraction method and hashes are recorded in each pack's provenance.json.
The logo master, website/favicon, email and customer websites remain unchanged.

Build from agency repository root:

```sh
node scripts/build-social-profile-kit.cjs
```

Default output: .local-email-preview/social-profile-kit (ignored generated assets).
Script accepts an optional explicit output directory. It creates 22 upload/master
PNGs, a source-pixel extraction, README, provenance and a portable HTML preview.
Nine destination labels, both full-logo and fam-crown: Facebook, Instagram,
Threads, LinkedIn, X, YouTube, TikTok, Pinterest and WhatsApp Business. These are
asset presets, not an assertion that the business has accounts on every network.
1080/2048 square masters are resampled from raster source, not extra vector detail.
No banner/cover design or social-account publishing is included in this task.

## Usage and safety

Obsidian #070907 baked behind art; opaque square PNGs avoid unexpected transparent
background rendering. Upload without zoom, then inspect the actual platform crop.
Circle-safe margins protect the original logo and crown. The full logo fits but
small tagline/descriptor text is not legible at 32–48px: compact FAM+crown is the
recommended small-avatar version. Preview includes 260/96/48/32px circular views
and light/dark surrounds. The full-logo variant honors the owner's preference.

Prepared output dimensions: FB/IG/Threads/TikTok1080, LinkedIn/X400, YouTube800,
Pinterest1000, WhatsApp640. They are export choices, not a universal specification.
X's official guidance recommends400×400 and caps profile files at2MB:
https://help.x.com/en/managing-your-account/common-issues-when-uploading-profile-photo
YouTube's official guidance notes a98×98 rendered profile image:
https://support.google.com/youtube/answer/10456525?hl=en-GB
Other platform acceptance/crop UI must be checked at upload; no account upload was
performed or certified. Current source sizes and actual file checks accompany QA.

## Review/release classification

Local generated assets for owner review, not deployed site branding, a replacement
favicon, a second canonical logo or an automatically published profile update.
See scripts/review-social-profile-kit.cjs for image/file/crop evidence. The kit is
copied to Downloads and zipped for convenient manual use, without touching accounts.
