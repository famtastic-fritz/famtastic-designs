import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { Section, Stagger, Item } from './v1/index.js';

const SERVICE_SERIES = {
  'ai-chatbot': 'AI Website Agent',
  'site-rebuild': 'Small-Business Website Strategy',
  'custom-website-development': 'Small-Business Website Strategy',
  'landing-page-design': 'Website Lead-Capture',
  'client-portal-systems': 'Customer Portal Experience',
  'e-commerce-solutions': 'Ecommerce and Post-Purchase',
};

const PACKAGE_SLUGS = {
  '199-quick-start': ['what-is-included-199-web-basics-bundle', 'professional-website-55-cents-a-day', 'what-is-a-domain-name', 'what-is-website-hosting'],
  '499-site-upgrade': ['what-is-included-499-business-website-bundle', 'choose-the-right-famtastic-website-package', 'business-website-package-explained'],
  'starter-website': ['starter-website-package-explained', 'choose-the-right-famtastic-website-package', 'one-page-vs-multi-page-website'],
  'business-website': ['business-website-package-explained', 'what-should-a-small-business-website-do', 'how-a-website-captures-a-lead'],
  'premium-website-ai': ['premium-website-ai-package-explained', 'when-an-ai-website-agent-is-useful', 'ai-agent-customer-documentation'],
  'landing-page': ['landing-page-package-explained', 'how-a-website-captures-a-lead', 'mobile-lead-capture-design'],
  'website-care-plan': ['website-care-plan-explained', 'website-numbers-that-matter', 'when-to-change-website-from-analytics'],
};

export default function RelatedEducation({ kind, slug }) {
  const [posts, setPosts] = useState([]);
  useEffect(() => {
    let cancelled = false;
    getNodesRaw('blog_post', { include: 'field_blog_series,field_blog_tags', limit: 100 }).then(({ data, included }) => {
      if (cancelled) return;
      const all = data.map((node) => transformBlogNode(node, included)).filter(Boolean);
      const selected = kind === 'package'
        ? (PACKAGE_SLUGS[slug] || []).map((wanted) => all.find((post) => post.slug === wanted)).filter(Boolean)
        : all.filter((post) => post.series?.includes(SERVICE_SERIES[slug] || '__none__')).slice(0, 4);
      setPosts(selected.slice(0, 4));
    });
    return () => { cancelled = true; };
  }, [kind, slug]);
  if (!posts.length) return null;
  return (
    <Section eyebrow="Learn before you choose" title="Understand what this can do for your business.">
      <Stagger className="v1-grid v1-grid--2">
        {posts.map((post) => <Item key={post.id} className="v1-card"><span className="v1-eyebrow">{post.series || 'FAMtastic guide'}</span><h2 className="v1-card__title"><Link to={`/blog/${post.slug}`}>{post.title}</Link></h2><p className="v1-card__text">{post.summary}</p><Link to={`/blog/${post.slug}`}>Read the guide →</Link></Item>)}
      </Stagger>
    </Section>
  );
}
