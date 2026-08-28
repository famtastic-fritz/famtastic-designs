import { useState } from 'react';
import { Panel } from './PortalShared.jsx';

export default function PortalShayAssistant({ workspace, go }) {
  const [selectedTopic, setSelectedTopic] = useState('packages');

  const topics = {
    packages: {
      title: 'Compare Web Bundles & Scope',
      content:
        'Web Basics ($199) gives you a single high-converting landing page with 1 year of hosting. Business Website ($499) delivers up to 5 custom pages, lead capture, foundational SEO, and analytics connection.',
      action: () => go('services'),
      actionLabel: 'Explore Catalog →',
    },
    proofs: {
      title: 'How Visual Proof Reviews Work',
      content:
        'FAMtastic generates 3 distinct, interactive design concepts in private sandboxes. You can review them on desktop/mobile, select your preferred direction, request specific color/layout adjustments, or share an unlisted link with your team.',
      action: () => go('projects'),
      actionLabel: 'Open Proof Room →',
    },
    changes: {
      title: 'Request Content or Layout Changes',
      content:
        'Need to update your phone number, business hours, menu, or add a seasonal banner? Start a message thread under "Website or service issue" and our team will implement it.',
      action: () => go('messages'),
      actionLabel: 'Message Support →',
    },
    ai_agent: {
      title: 'AI Website Concierge & Chatbot',
      content:
        'Our AI Website Agent is trained exclusively on your verified business facts and FAQs. It handles customer inquiries 24/7 without making up prices or unapproved promises.',
      action: () => go('services'),
      actionLabel: 'View AI Agent Module →',
    },
  };

  const active = topics[selectedTopic];

  return (
    <section className="portal-grid two">
      <Panel
        eyebrow="Governed AI Assistant"
        title="Shay — Solutions Advisor"
        className="lime"
      >
        <p>
          Shay assists you in understanding package capabilities, organizing project briefs, and drafting
          inquiries.
        </p>

        <div
          style={{
            margin: '1rem 0',
            padding: '0.85rem',
            borderRadius: '10px',
            border: '1px solid rgba(124,252,0,0.35)',
            background: 'rgba(0,0,0,0.5)',
          }}
        >
          <span
            style={{
              display: 'block',
              color: '#7cfc00',
              fontSize: '0.72rem',
              fontWeight: '800',
              textTransform: 'uppercase',
              letterSpacing: '0.08em',
            }}
          >
            🛡️ AI Governance &amp; Operating Rule
          </span>
          <p style={{ margin: '0.3rem 0 0', fontSize: '0.82rem', color: '#c2ccc2', lineHeight: '1.45' }}>
            FAMtastic AI models summarize, explain, and draft. They never change billing, alter accounts,
            send messages autonomously, or deploy code without explicit human confirmation.
          </p>
        </div>

        <div style={{ display: 'grid', gap: '0.5rem', marginTop: '1.25rem' }}>
          <span style={{ fontSize: '0.75rem', color: '#8e998e', textTransform: 'uppercase' }}>
            Choose a topic to explore
          </span>
          {Object.entries(topics).map(([key, item]) => (
            <button
              key={key}
              type="button"
              className={selectedTopic === key ? '' : 'quiet'}
              style={{ textAlign: 'left', width: '100%', padding: '0.65rem 0.85rem' }}
              onClick={() => setSelectedTopic(key)}
            >
              {item.title}
            </button>
          ))}
        </div>
      </Panel>

      <Panel eyebrow="Advisor Knowledge" title={active.title}>
        <p style={{ fontSize: '0.95rem', lineHeight: '1.6', color: '#e0e6e0' }}>{active.content}</p>
        <div style={{ marginTop: '1.5rem' }}>
          <button type="button" onClick={active.action}>
            {active.actionLabel}
          </button>
        </div>
      </Panel>
    </section>
  );
}
