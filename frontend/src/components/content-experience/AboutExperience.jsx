import { plainTextValue, textValue } from '../../utils/content.js';
import { FAMPage, FAMPageHero, FAMSection, FAMStatement, FAMFinale } from './index.jsx';

const MEANINGS = [
  ['F', 'Fearless Deviation', 'fearless'],
  ['A', 'Applying Mastery', 'mastery'],
  ['M', 'Manifesting Extraordinary', 'extraordinary'],
];

export default function AboutExperience({ attrs }) {
  const body = textValue(attrs.body);
  return <FAMPage id="about" recipe="about">
    <FAMPageHero id="story" eyebrow={attrs.title || 'About'}
      title={plainTextValue(attrs.field_hero_headline) || attrs.title || 'About FAMtastic'}
      lede={plainTextValue(attrs.field_hero_subheadline)} />
    <FAMSection id="fam-definition" eyebrow="Our name. Our philosophy." title="What makes it FAMtastic." signature="FAMtastic.">
      <div className="fam-ce-meaning">{MEANINGS.map(([letter, meaning, tone]) => <div className={`fam-ce-meaning__row fam-ce-meaning__row--${tone}`} key={letter}>
        <span className="fam-ce-meaning__letter" aria-hidden="true">{letter}</span><h3>{meaning}</h3>
      </div>)}</div>
    </FAMSection>
    <FAMStatement id="philosophy" eyebrow="The FAM in FAMtastic" title="That's how we make something FAMtastic." signature="FAMtastic." />
    <FAMSection id="existing-story" eyebrow="Inside the studio" title="The thinking behind the work." signature="the work.">
      {/* Preserve the existing trusted, processed Drupal editorial body. */}
      {body ? <div className="fam-ce-story-body" dangerouslySetInnerHTML={{ __html: body }} /> : <p>This page is being published — check back soon.</p>}
    </FAMSection>
    <FAMFinale id="finale" title="More than a website… A future." signature="A future."
      primaryCta={{ label: 'Start Your Project', href: '/contact' }} secondaryCta={{ label: 'Explore our work', href: '/work' }} />
  </FAMPage>;
}
