import { useRef } from 'react';
import { FAMPage, FAMPageHero } from '../components/content-experience/index.jsx';
import './video-review-page.css';

const FILMS = [
  {
    id: 'walking',
    title: 'The walking continuation',
    length: '0:36',
    format: 'portrait',
    stem: 'walking-continuation-20260919',
    description: 'Your original performance, a new voiceover bridge, and the belief behind FAMtastic.',
    note: 'Uses the recorded performance from the reference film. No new acting or lip-sync was generated.',
  },
  {
    id: 'faster-ad',
    title: 'What’s the catch? — Version 2',
    length: '1:13',
    format: 'landscape',
    stem: 'whats-the-catch-v2-20260919',
    description: 'The complete story with a faster voice, tighter pacing, and the revised fam-TAS-tik pronunciation.',
    note: 'The full script, now 30% shorter than the first version.',
  },
  {
    id: 'voice-test',
    title: 'The local voice experiment',
    length: '0:33',
    format: 'square',
    stem: 'local-voice-proof-20260919',
    description: 'Hear the original narrator, a reference voice, and the converted result side by side.',
    note: 'An experimental comparison using synthetic voices. This is not a clone of Fritz’s voice.',
  },
];

export default function VideoReviewPage() {
  const players = useRef(new Map());

  function playOne(event) {
    for (const player of players.current.values()) {
      if (player !== event.currentTarget) player.pause();
    }
  }

  return (
    <FAMPage id="video-review" recipe="video-review">
      <FAMPageHero
        id="video-review-intro"
        eyebrow="FAMtastic Designs · September 19, 2026"
        title="Your latest films."
        signature="latest films."
        lede="Three videos. One place to watch. Tap a player to begin, with sound."
        note="Captions are included in the videos. Use the player controls for full screen."
      />
      <nav className="video-review-nav fam-ce-container" aria-label="Choose a video">
        <a href="#walking">Walking continuation</a>
        <a href="#faster-ad">Faster ad · V2</a>
        <a href="#voice-test">Voice experiment</a>
      </nav>
      <div className="fam-ce-container">
        {FILMS.map((film, index) => {
          const base = `/media/films/${film.stem}`;
          return (
            <section key={film.id} id={film.id} className="video-review-film" aria-labelledby={`${film.id}-title`}>
              <div className="video-review-copy">
                <p className="fam-ce-eyebrow">0{index + 1} / {film.length}{film.id === 'voice-test' ? ' · Experimental' : ''}</p>
                <h2 id={`${film.id}-title`}>{film.title}</h2>
                <p>{film.description}</p>
                <p id={`${film.id}-note`} className="video-review-note">{film.note}</p>
                <a className="video-review-direct" href={`${base}.mp4`} target="_blank" rel="noopener">Open video directly<span className="fam-ce-sr-only">: {film.title}</span> ↗</a>
              </div>
              <figure className={`video-review-player video-review-player--${film.format}`}>
                <video
                  ref={node => { if (node) players.current.set(film.id, node); else players.current.delete(film.id); }}
                  controls playsInline preload="none"
                  poster={`${base}.jpg`}
                  aria-label={film.title}
                  aria-describedby={`${film.id}-note`}
                  onPlay={playOne}
                >
                  <source src={`${base}.mp4`} type="video/mp4" />
                  <track kind="captions" src={`${base}.vtt`} srcLang="en-US" label="English captions" />
                  <p><a href={`${base}.mp4`}>Open {film.title}</a></p>
                </video>
              </figure>
            </section>
          );
        })}
      </div>
    </FAMPage>
  );
}
