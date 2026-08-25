import { useState } from 'react';
import { collectUtmParams, postContactRequest } from '../../api/pipeline.js';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

const INITIAL = { name: '', email: '', phone: '', business: '', message: '' };

/**
 * v1 contact form — real fields (name, email, phone, business, message) in
 * the dark card style. The headless backend exposes no form endpoint yet, so
 * a valid submission opens the visitor's mail client with a fully composed
 * message to the studio inbox and confirms inline.
 */
export default function ContactForm({ title = 'Tell us about your project', compact = false }) {
  const [values, setValues] = useState(INITIAL);
  const [errors, setErrors] = useState({});
  const [sent, setSent] = useState(false);
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [fallbackMailto, setFallbackMailto] = useState('');

  function update(field) {
    return (event) => {
      setValues((prev) => ({ ...prev, [field]: event.target.value }));
      setErrors((prev) => ({ ...prev, [field]: undefined }));
    };
  }

  function validate() {
    const next = {};
    if (!values.name.trim()) next.name = 'Your name is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) next.email = 'A valid email is required.';
    if (values.message.trim().length < 10) next.message = 'Tell us a little more (10+ characters).';
    return next;
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const next = validate();
    if (Object.keys(next).length > 0) {
      setErrors(next);
      return;
    }
    if (submitting || sent) return;

    const subject = `Project inquiry — ${values.name.trim()}${values.business.trim() ? ` (${values.business.trim()})` : ''}`;
    const lines = [
      `Name: ${values.name.trim()}`,
      `Email: ${values.email.trim()}`,
      values.phone.trim() && `Phone: ${values.phone.trim()}`,
      values.business.trim() && `Business: ${values.business.trim()}`,
      '',
      values.message.trim(),
    ].filter((line) => line !== false && line !== undefined);
    const mailto = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`;
    setFallbackMailto(mailto);
    setSubmitting(true);
    try {
      const res = await postContactRequest({
        source: 'contact-form',
        name: values.name.trim(),
        email: values.email.trim(),
        phone: values.phone.trim(),
        business_name: values.business.trim(),
        message: values.message.trim(),
        utm: collectUtmParams(),
        path: window.location.pathname,
        referrer: document.referrer || null,
      });
      setMessage(res?.message || 'We received your request. Our team has been notified.');
      setSent(true);
    } catch {
      setMessage('We could not reach the server. Your email client should open with your message ready to send.');
      window.location.href = mailto;
      setSent(true);
    } finally {
      setSubmitting(false);
    }
  }

  if (sent) {
    return (
      <div className="v1-card v1-form-card" role="status">
        <h2 className="v1-form-card__title">Thanks, {values.name.split(' ')[0]}.</h2>
        <p className="v1-card__text">
          {message || 'We received your request. Our team has been notified.'}
          {' '}If you need to send anything else, write to us directly at{' '}
          <a href={fallbackMailto || `mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
        </p>
        <button
          type="button"
          className="v1-btn v1-btn--ghost"
          onClick={() => {
              setSent(false);
              setMessage('');
              setFallbackMailto('');
              setValues(INITIAL);
            }}
        >
          Send another message
        </button>
      </div>
    );
  }

  return (
    <form className={`v1-card v1-form-card${compact ? ' v1-form-card--compact' : ''}`} onSubmit={handleSubmit} noValidate>
      <h2 className="v1-form-card__title">{title}</h2>

      <div className="v1-form__grid">
        <label className="v1-field">
          <span className="v1-field__label">Name *</span>
          <input
            className="v1-field__input"
            type="text"
            name="name"
            autoComplete="name"
            value={values.name}
            onChange={update('name')}
            aria-invalid={Boolean(errors.name)}
          />
          {errors.name && <span className="v1-field__error">{errors.name}</span>}
        </label>

        <label className="v1-field">
          <span className="v1-field__label">Email *</span>
          <input
            className="v1-field__input"
            type="email"
            name="email"
            autoComplete="email"
            value={values.email}
            onChange={update('email')}
            aria-invalid={Boolean(errors.email)}
          />
          {errors.email && <span className="v1-field__error">{errors.email}</span>}
        </label>

        <label className="v1-field">
          <span className="v1-field__label">Phone</span>
          <input
            className="v1-field__input"
            type="tel"
            name="phone"
            autoComplete="tel"
            value={values.phone}
            onChange={update('phone')}
          />
        </label>

        <label className="v1-field">
          <span className="v1-field__label">Business</span>
          <input
            className="v1-field__input"
            type="text"
            name="business"
            autoComplete="organization"
            value={values.business}
            onChange={update('business')}
          />
        </label>
      </div>

      <label className="v1-field">
        <span className="v1-field__label">What are you trying to build? *</span>
        <textarea
          className="v1-field__input v1-field__textarea"
          name="message"
          rows={compact ? 4 : 6}
          value={values.message}
          onChange={update('message')}
          aria-invalid={Boolean(errors.message)}
        />
        {errors.message && <span className="v1-field__error">{errors.message}</span>}
      </label>

      <button type="submit" className="v1-btn v1-btn--primary v1-form__submit" disabled={submitting || sent}>
        {submitting ? 'Sending...' : 'Send Message'}
      </button>
      <p className="v1-form__note">
        No accounts, no spam lists. If the server is unavailable, we will fall back to email.
      </p>
    </form>
  );
}
