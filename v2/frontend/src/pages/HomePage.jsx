import NodeList from '../components/NodeList.jsx';

/**
 * Landing page: brand hero + the latest articles from the backend.
 */
export default function HomePage() {
  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">Headless Drupal 11 · React 18</span>
        <h1 className="hero__title">
          Design that <span className="accent">glows</span> in the dark.
        </h1>
        <p className="hero__lede">
          FAMtastic Designs pairs a decoupled Drupal 11 content backend with a
          fast React SPA — premium dark UI, lime accent, zero compromise.
        </p>
      </section>

      <section aria-labelledby="latest-heading">
        <div className="section-heading">
          <h2 id="latest-heading">Latest articles</h2>
          <span className="hint">Served live over JSON:API</span>
        </div>
        <NodeList type="article" />
      </section>
    </>
  );
}
