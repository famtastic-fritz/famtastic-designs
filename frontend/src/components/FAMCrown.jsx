/** Source-derived authorship mark. Success callers must have real success state. */
export default function FAMCrown({intent='signature',intensity='normal',label}) {
  const safeIntent=['signature','featured','success','identity'].includes(intent)?intent:'signature';
  const safeIntensity=['subtle','normal','hero'].includes(intensity)?intensity:'normal';
  return <img className={`fam-crown fam-crown--${safeIntensity}`} data-intent={safeIntent}
    src="/brand/famtastic-crown-flat.png" width="251" height="254" alt={label||''}
    aria-hidden={label?undefined:true} decoding="async" />;
}
