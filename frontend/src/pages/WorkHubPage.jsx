import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformCaseStudyNode } from '../lib/drupalAdapter.js';
import './WorkHubPage.css';

const WORLDS = [
  { id: 'rattler-lifers', index: '01', eyebrow: 'Live campaign world', title: 'And If It Is?', statement: 'A rally cry became an identity system people could enter, answer, and share.', detail: 'Strategy · Character design · Social system · Interactive web', image: '/portfolio/rattler-lifers.webp', alt: 'The And If It Is Rattler Lifers campaign website in deep green, orange, and cream', href: '/and-if-it-is/', action: 'Enter the experience', palette: ['#ff681d', '#f4e7c0', '#003f2d'], className: 'is-rattler' },
  { id: 'reunion-cinema', index: '02', eyebrow: 'Live digital event', title: 'The Tide Is Rising', statement: 'A 30-year reunion rebuilt as a cinematic destination, ticket engine, and living memory.', detail: 'Experience design · Commerce · Storytelling · Operations', image: '/portfolio/mbsh-reunion.webp', alt: 'Miami Beach Senior High reunion cinematic artwork with an illuminated school tower', action: 'Live launch · FAMtastic case file', palette: ['#e6b95f', '#102f5f', '#6e1018'], className: 'is-reunion' },
  { id: 'bossy-lab', index: '03', eyebrow: 'Fictional concept lab', title: 'The Bossy Lab', statement: 'A nail appointment became a neon formulation ritual—part beauty studio, part future laboratory.', detail: 'Art direction · Generated imagery · Conversion prototype', image: '/portfolio/bossy-lab.webp', alt: 'Fictional Bossy Nails concept with a Black nail artist in a futuristic neon laboratory', action: 'Built to test the edge', palette: ['#baff15', '#5530a6', '#070a0f'], className: 'is-bossy' },
  { id: 'candy-lady', index: '04', eyebrow: 'Fictional concept lab', title: 'Porch to Playoffs', statement: 'Neighborhood memory became a warm, mobile-first shop built for game-day movement.', detail: 'Cultural research · Commerce concept · Community storytelling', image: '/portfolio/candy-lady.webp', alt: 'Fictional Candy Lady Shop concept with a multiracial group of children at a game-day candy table', action: 'A business wrapped in memory', palette: ['#e4342d', '#f5dfb4', '#194b7d'], className: 'is-candy' },
  { id: 'crown-coast', index: '05', eyebrow: 'Fictional concept lab', title: 'Crown & Coast', statement: 'A first church visit became a luminous pilgrimage from uncertainty to belonging.', detail: 'Journey design · Local research · Editorial web system', image: '/portfolio/crown-coast.webp', alt: 'Fictional Crown and Coast church website with a sunrise waterfront procession', action: 'Hospitality, made visible', palette: ['#ff675c', '#ffd15d', '#082a50'], className: 'is-crown' },
  { id: 'famu-corner', index: '06', eyebrow: 'Fictional concept lab', title: 'The Signal Is Alive', statement: 'Scores, campus movement, culture, and alumni energy reorganized into one living Rattler signal.', detail: 'Information architecture · Fan experience · Visual system', image: '/portfolio/famu-corner.webp', alt: 'Fictional FAMU Corner fan portal concept in orange, green, and black', action: 'A portal with a pulse', palette: ['#ff691d', '#f0ead8', '#06482f'], className: 'is-corner' },
];

function World({ world, onVisible }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!ref.current || !('IntersectionObserver' in window)) return undefined;
    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting && entry.intersectionRatio > 0.38) onVisible(world);
    }, { threshold: [0.38, 0.62] });
    observer.observe(ref.current);
    return () => observer.disconnect();
  }, [onVisible, world]);

  const content = <><span>{world.action}</span>{world.href && <b aria-hidden="true">↗</b>}</>;
  return (
    <article ref={ref} className={`portfolio-world ${world.className}`} id={world.id}>
      <div className="portfolio-world__noise" aria-hidden="true" />
      <div className="portfolio-world__copy">
        <p className="portfolio-world__eyebrow"><span>{world.index}</span>{world.eyebrow}</p>
        <h2>{world.title}</h2>
        <p className="portfolio-world__statement">{world.statement}</p>
        <p className="portfolio-world__detail">{world.detail}</p>
        {world.href ? (world.external
          ? <a className="portfolio-world__action" href={world.href} target="_blank" rel="noreferrer">{content}</a>
          : <a className="portfolio-world__action" href={world.href}>{content}</a>)
          : <span className="portfolio-world__action is-label">{content}</span>}
      </div>
      <div className="portfolio-world__stage" aria-hidden="true">
        <div className="portfolio-world__orbit" />
        <div className="portfolio-world__browser">
          <div className="portfolio-world__chrome"><i /><i /><i /><span>famtastic / experience</span></div>
          <img src={world.image} alt="" loading={world.index === '01' ? 'eager' : 'lazy'} />
        </div>
      </div>
      <p className="sr-only">{world.alt}</p>
    </article>
  );
}

export default function WorkHubPage() {
  const [studies, setStudies] = useState([]);
  const [active, setActive] = useState(WORLDS[0]);
  useEffect(() => {
    let cancelled = false;
    getNodesRaw('case_study').then(({ data }) => {
      if (!cancelled) setStudies(data.map(transformCaseStudyNode).filter(Boolean));
    });
    return () => { cancelled = true; };
  }, []);

  const paletteStyle = { '--portfolio-hot': active.palette[0], '--portfolio-light': active.palette[1], '--portfolio-deep': active.palette[2] };
  return (
    <div className="portfolio" style={paletteStyle}>
      <header className="portfolio-intro">
        <div className="portfolio-intro__signal" aria-hidden="true"><i /><i /><i /><i /><i /></div>
        <p className="portfolio-intro__kicker">FAMtastic Designs · Selected worlds</p>
        <h1>We do not make<br /><em>“another website.”</em></h1>
        <p className="portfolio-intro__lede">We build places a brand can live—systems with a pulse, a point of view, and a job to do.</p>
        <a href="#rattler-lifers" className="portfolio-intro__enter"><span>Enter the work</span><i aria-hidden="true">↓</i></a>
        <div className="portfolio-intro__marquee" aria-hidden="true"><span>Strategy · Story · Systems · Motion · Culture · Conversion · Strategy · Story · Systems · Motion · Culture · Conversion · </span></div>
      </header>

      <nav className="portfolio-index" aria-label="Selected work">
        <span>Now entering</span><strong>{active.index} / {active.title}</strong>
        <div>{WORLDS.map((world) => <a key={world.id} className={world.id === active.id ? 'active' : ''} href={`#${world.id}`} aria-label={`Jump to ${world.title}`} />)}</div>
      </nav>

      <main className="portfolio-worlds">{WORLDS.map((world) => <World key={world.id} world={world} onVisible={setActive} />)}</main>

      {studies.length > 0 && <section className="portfolio-casefiles" aria-labelledby="casefiles-title">
        <p>Published case files</p><h2 id="casefiles-title">The receipts keep coming.</h2>
        <div>{studies.map((study) => <Link key={study.id} to={`/work/${study.slug}`}><span>{study.projectType || 'Case study'}</span><strong>{study.title}</strong><i>Read the story →</i></Link>)}</div>
      </section>}

      <section className="portfolio-outro">
        <div aria-hidden="true" className="portfolio-outro__rings"><i /><i /><i /></div>
        <p>You saw what happens when we get the room to think.</p>
        <h2>What should we make<br />impossible to ignore?</h2>
        <Link to="/start">Start your world <span>↗</span></Link>
        <small>Concept-lab work is labeled. This portfolio experience stays inside FAMtastic Designs.</small>
      </section>
    </div>
  );
}
