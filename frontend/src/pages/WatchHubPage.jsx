import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { Hero, Section, Stagger, Item, CTABanner, FadeUp } from '../components/v1/index.js';
import { FILMS, FILM_SERIES, filmPath, runningTime, watchItemList } from '../lib/filmLibrary.js';
import { artDivider } from '../lib/blogArt.js';

/**
 * /watch — the film index.
 *
 * Laid out as an editorial index rather than a video wall. Every film here is
 * 9:16, and a grid of full-height vertical posters at three columns runs each
 * card past 700px tall, which turns the page into a scroll with no overview.
 * So a card is horizontal: a narrow poster beside the argument, the way a film
 * index reads, and the poster is the only cropped element on the page.
 *
 * The series filter reuses the blog hub's `.blog-filter` buttons verbatim —
 * this library is part of the blog, not a separate microsite, and shared
 * chrome is the cheapest way to say so.
 */
export default function WatchHubPage() {
  const [series, setSeries] = useState('All');

  useEffect(() => {
    // Same de-duplication rule as the film page: the build already writes this
    // ItemList into /watch/index.html, so only inject it when arriving through
    // client-side navigation, where the head still describes another page.
    const alreadyDescribed = [...document.querySelectorAll('script[type="application/ld+json"]')].some(
      (node) => !node.dataset.famtasticWatchSchema && node.textContent?.includes('/watch/#films'),
    );
    if (alreadyDescribed) return undefined;

    const schema = document.createElement('script');
    schema.type = 'application/ld+json';
    schema.dataset.famtasticWatchSchema = 'true';
    schema.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        watchItemList(),
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: 'https://famtasticdesigns.com/' },
            { '@type': 'ListItem', position: 2, name: 'Films', item: 'https://famtasticdesigns.com/watch/' },
          ],
        },
      ],
    });
    document.head.appendChild(schema);
    return () => schema.remove();
  }, []);

  const options = ['All', ...FILM_SERIES];
  const visible = FILMS.filter((film) => series === 'All' || film.series === series);

  return (
    <>
      <Hero
        eyebrow="Films"
        title="Short films, one"
        accent="argument each"
        lede="Every campaign film we have finished, with its on-screen copy written out and — where a film is narrated from an approved script — its transcript. Nothing here needs a platform to be watchable."
      />

      <Section>
        <div className="blog-filters watch-filters" aria-label="Filter films by series">
          {options.map((item) => (
            <button
              key={item}
              type="button"
              className={`blog-filter${series === item ? ' blog-filter--active' : ''}`}
              onClick={() => setSeries(item)}
              aria-pressed={series === item}
            >
              {item}
            </button>
          ))}
        </div>
        <p className="watch-count">
          {visible.length} film{visible.length === 1 ? '' : 's'}
        </p>

        <Stagger className="watch-list">
          {visible.map((film) => (
            <Item key={film.slug}>
              {/*
                One link per card, stretched over the whole card by
                `.watch-card__link::after`. The poster is decorative here — the
                title carries the destination — so a second link on the image
                would only give a screen reader the same target twice, and the
                stretched pseudo-element is what makes the entire card a touch
                target rather than a 24px line of text.
              */}
              <article className="watch-card">
                <div className="watch-card__poster">
                  <img src={film.poster} alt="" width="540" height="960" loading="lazy" />
                  <span className="watch-card__time">{runningTime(film.durationSeconds)}</span>
                </div>
                <div className="watch-card__body">
                  <span className="v1-card__kicker">{film.eyebrow}</span>
                  <h2 className="watch-card__title">
                    <Link to={filmPath(film)} className="watch-card__link">
                      {film.title}
                    </Link>
                  </h2>
                  <p className="watch-card__text">{film.tagline}</p>
                  <p className="watch-card__meta">
                    {runningTime(film.durationSeconds)} · {film.sound}
                    {film.transcript ? ' · Transcript' : ''}
                  </p>
                </div>
              </article>
            </Item>
          ))}
        </Stagger>

        <FadeUp>
          <div className="fam-art-divider-host" dangerouslySetInnerHTML={{ __html: artDivider({ seed: 7 }) }} />
        </FadeUp>

        {/*
          One standing scope statement for the whole library. Several of these
          films quote a price on screen, and a viewer who watches three of them
          in a row should not have to reconstruct the boundary from fragments.
          Every line traces to backend/config/famtastic-products.json.
        */}
        <FadeUp className="v1-panel">
          <div className="v1-prose watch-scope">
            <h2>What the $199 in these films is</h2>
            <p>
              The Web Basics Bundle is <strong>$199 one time</strong> — about 55 cents a day across the
              first year — and it is exactly three things:
            </p>
            <ul>
              <li>One focused landing-page website</li>
              <li>One year of managed hosting</li>
              <li>
                The first-year domain registration, or the connection of a domain you already own
              </li>
            </ul>
            <p>
              After the first year, hosting renews at $9.99 a month and the domain renews separately.
              Business email setup ($99, one time) and website maintenance are their own products and
              are <strong>not</strong> included in the $199. Two of the films above exist specifically to
              say that out loud.
            </p>
            <p>
              <Link to="/packages">Read the full published scope</Link> before you buy anything.
            </p>
          </div>
        </FadeUp>
      </Section>

      <CTABanner
        title="See what one of these would look like for your business"
        primaryCta={{ label: 'Start with a free preview', href: '/start' }}
      />
    </>
  );
}
