/** Opt-in editorial phrase only. Preserve CMS text and native heading semantics. */
export default function SignatureHeading({ text, phrases = [] }) {
  if (typeof text !== 'string') return text;
  const phrase = phrases.find(value => typeof value === 'string' && value.trim()
    && value.trim().split(/\s+/).length <= 6 && text.includes(value));
  if (!phrase) return text;
  const start = text.indexOf(phrase);
  return <>{text.slice(0, start)}<span className="fam-heading-script">{phrase}</span>{text.slice(start + phrase.length)}</>;
}
