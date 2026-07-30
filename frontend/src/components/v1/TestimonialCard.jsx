/**
 * v1 testimonial — oversized lime quote mark, quote text, attribution line
 * (port of the v1 Testimonials block card, simplified to the single-quote
 * shape the Drupal service_page / homepage fields provide).
 */
export default function TestimonialCard({ quote, attribution, className = '' }) {
  if (!quote) return null;
  return (
    <blockquote className={`v1-testimonial ${className}`.trim()}>
      <span className="v1-testimonial__mark" aria-hidden="true">
        &ldquo;
      </span>
      <p className="v1-testimonial__quote">{quote}</p>
      {attribution && <cite className="v1-testimonial__attribution">— {attribution}</cite>}
    </blockquote>
  );
}
