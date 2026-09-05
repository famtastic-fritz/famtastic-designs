import { useEffect } from 'react';
import { Link, useParams } from 'react-router';
import { Hero, Section, CTABanner, FadeUp } from '../components/v1/index.js';
import SocialShareButtons from '../components/SocialShareButtons.jsx';
import { applySeo } from '../components/SEO.jsx';
import { filmSeo } from '../seo.js';
import {
  FILMS,
  getFilm,
  filmCanonical,
  filmMetaDescription,
  filmPath,
  filmSeoTitle,
  filmVideoObject,
  runningTime,
} from '../lib/filmLibrary.js';
import {
  artDayCost,
  artDivider,
  artOwnedVsRented,
  artOwnershipMarker,
  artScopeBoundary,
  hashString,
} from '../lib/blogArt.js';

/**
 * Which generated art block a film has earned.
 *
 * This mirrors the rule in lib/blogArt.js rather than inventing a second one:
 * art is placed where the argument is actually about the thing the diagram
 * draws, and a film that has earned nothing gets nothing but an ornamental
 * divider. `found-then-kept` is the deliberate `null` — its subject is what a
 * machine can read about a business, and none of the shipped blocks draw that.
 */
function filmArt(film) {
  const seed = hashString(film.slug);
  switch (film.art) {
    case 'ownedVsRented':
      return {
        html: artOwnedVsRented({ seed }),
        wrap: true,
        caption:
          'Placements inside somebody else’s platform sit under rules the business does not set. An owned domain sits on ground it controls.',
      };
    case 'ownershipMarker':
      return {
        html: artOwnershipMarker({ seed }),
        wrap: true,
        caption:
          'Three loose rented placements, then the same three squared onto a foundation the business owns.',
      };
    case 'dayCost':
      return {
        html: artDayCost({ seed }),
        wrap: true,
        caption:
          'The one-time $199 spread across the included first year — an affordability comparison, not a subscription.',
      };
    case 'scopeBoundary':
      return {
        html: artScopeBoundary({
          seed,
          included: film.scope?.included ?? [],
          excluded: film.scope?.excluded ?? [],
        }),
        wrap: false,
        caption: null,
      };
    default:
      return null;
  }
}

/**
 * /watch/:slug — one film.
 *
 * WHY THE BODY IS `.v1-panel > .v1-prose`:
 * This is the same container a blog post's body renders into, so the type
 * scale, the link colour, the list indents and every generated-art rule in
 * index.css apply here for free and stay in sync forever. The library is
 * supposed to read as part of the blog; the cheapest way to guarantee that is
 * to literally be the blog's prose container rather than a lookalike.
 *
 * WHY `preload="none"`:
 * These are 6-24 MB files and there are eight of them. A page that begins
 * fetching video before anyone presses play spends a phone's data on a decision
 * the visitor has not made. The poster carries the frame; the bytes wait.
 */
export default function WatchFilmPage() {
  const { slug } = useParams();
  const film = getFilm(slug);

  useEffect(() => {
    if (!film) return undefined;
    const description = filmMetaDescription(film);
    applySeo(filmSeo(film, { title: filmSeoTitle(film), description }));

    const canonical = filmCanonical(film);

    // The build writes this film's VideoObject into the shell's own JSON-LD, so
    // on a hard load it is already in <head> before React runs. Appending a
    // second copy would put two VideoObject entities with the same @id on one
    // page. Inject only when the static block is absent — which is exactly the
    // client-side-navigation case, where the document still carries the
    // PREVIOUS film's structured data and genuinely needs replacing.
    const alreadyDescribed = [...document.querySelectorAll('script[type="application/ld+json"]')].some(
      (node) => !node.dataset.famtasticFilmSchema && node.textContent?.includes(`${canonical}#video`),
    );
    if (alreadyDescribed) return undefined;

    const schema = document.createElement('script');
    schema.type = 'application/ld+json';
    schema.dataset.famtasticFilmSchema = 'true';
    schema.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        filmVideoObject(film),
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: 'https://famtasticdesigns.com/' },
            { '@type': 'ListItem', position: 2, name: 'Films', item: 'https://famtasticdesigns.com/watch/' },
            { '@type': 'ListItem', position: 3, name: film.title, item: canonical },
          ],
        },
      ],
    });
    document.head.appendChild(schema);
    return () => schema.remove();
  }, [film]);

  if (!film) {
    return (
      <Section>
        <div className="v1-empty">
          <strong>We could not find that film.</strong>
          <br />
          <Link to="/watch">Browse every film</Link>.
        </div>
      </Section>
    );
  }

  const index = FILMS.findIndex((item) => item.slug === film.slug);
  const previous = index > 0 ? FILMS[index - 1] : null;
  const next = index >= 0 ? FILMS[index + 1] : null;
  const art = filmArt(film);

  return (
    <article>
      <Hero eyebrow={`Film · ${runningTime(film.durationSeconds)}`} title={film.title} lede={film.tagline} />

      <Section>
        <Link to="/watch" className="v1-back-link">
          ← All films
        </Link>

        <figure className="blog-visual watch-stage">
          {/* eslint-disable-next-line jsx-a11y/media-has-caption -- the film's
              spoken and on-screen copy is written out in full below the player,
              which is the accessible text alternative for this page. */}
          <video
            className="watch-stage__video"
            controls
            preload="none"
            playsInline
            poster={film.poster}
            width={film.width}
            height={film.height}
          >
            <source src={film.file} type="video/mp4" />
            Your browser cannot play this file.{' '}
            <a href={film.file}>Download the film</a> — its full on-screen copy is written out below.
          </video>
          <figcaption>
            <img src="/brand/famtastic-mark.svg" alt="" width="32" height="32" />
            <span>
              FAMtastic Designs — {film.title}. {runningTime(film.durationSeconds)}, {film.width}×
              {film.height}. {film.sound}
            </span>
          </figcaption>
        </figure>

        <FadeUp className="v1-panel">
          <div className="v1-prose">
            <h2>What this film argues</h2>
            {film.argument.map((paragraph) => (
              <p key={paragraph.slice(0, 40)}>{paragraph}</p>
            ))}

            <h2>Who it is for</h2>
            <p>{film.audience}</p>

            {/* `artScopeBoundary` ships its own frame and ground, so wrapping it
                in the figure chrome would double the border. It renders bare and
                centred; the SVG blocks take the figure. */}
            {art && art.wrap && (
              <figure className="article-inline-visual article-inline-visual--art">
                <div className="fam-art" dangerouslySetInnerHTML={{ __html: art.html }} />
                {art.caption && (
                  <figcaption>
                    <img src="/brand/famtastic-mark.svg" alt="" width="28" height="28" />
                    FAMtastic Designs — {art.caption}
                  </figcaption>
                )}
              </figure>
            )}
            {art && !art.wrap && (
              <div className="watch-art-bare" dangerouslySetInnerHTML={{ __html: art.html }} />
            )}

            {!art && <div dangerouslySetInnerHTML={{ __html: artDivider({ seed: hashString(film.slug) }) }} />}

            {film.transcript && (
              <>
                <h2>Narration</h2>
                <p className="watch-note">
                  The words spoken in the film, from the approved script it was recorded to.
                </p>
                <blockquote className="watch-transcript">
                  {film.transcript.map((line) => (
                    <p key={line}>{line}</p>
                  ))}
                </blockquote>
              </>
            )}

            <h2>What is on screen</h2>
            <p className="watch-note">
              Every line the film sets in type, beat by beat — so the argument is readable whether or
              not the video plays.
            </p>
            <ol className="watch-beats">
              {film.onScreen.map((beat) => (
                <li key={beat.beat}>
                  <h3>{beat.beat}</h3>
                  <ul>
                    {beat.lines.map((line) => (
                      <li key={line}>{line}</li>
                    ))}
                  </ul>
                </li>
              ))}
            </ol>
          </div>
        </FadeUp>

        <SocialShareButtons title={film.title} summary={film.tagline} url={filmCanonical(film)} />

        <nav className="blog-series-nav" aria-label="Film library navigation">
          <div>
            <span>
              Film {index + 1} of {FILMS.length}
            </span>
            <strong>{film.series}</strong>
          </div>
          <div className="blog-series-nav__links">
            {previous && <Link to={filmPath(previous)}>← {previous.title}</Link>}
            {next && <Link to={filmPath(next)}>{next.title} →</Link>}
          </div>
        </nav>
      </Section>

      <CTABanner
        title="See three real design directions for your own business first"
        primaryCta={{ label: 'Start a free preview', href: '/start' }}
        secondaryCta={{ label: 'Read the published scope', href: '/packages' }}
      />
    </article>
  );
}
