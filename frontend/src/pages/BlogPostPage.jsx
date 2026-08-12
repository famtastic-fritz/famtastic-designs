import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { applySeo } from '../components/SEO.jsx';
import { blogSeo } from '../seo.js';
import { Hero, Section, CTABanner, FadeUp, FAQAccordion } from '../components/v1/index.js';

const CAMPAIGN_ARTICLES = [
  'professional-website-55-cents-a-day', 'what-is-a-domain-name', 'what-is-website-hosting',
  'parts-of-a-one-page-business-website', '199-website-inclusions-and-boundaries',
  'after-buying-199-website', 'who-is-199-website-for', 'grow-beyond-first-website',
];
const CAMPAIGN_VISUALS = [
  ['55-cent-website-hero.webp', 'A small-business owner stepping from an idea toward a professional website', 'The first step is a credible place for customers to find and understand the business.'],
  ['domain-explained.webp', 'A business connected to its website through a clear digital address', 'The domain is the customer-owned address that points people to the website.'],
  ['hosting-explained.webp', 'Protected managed infrastructure keeping a business website available online', 'Hosting is the managed infrastructure that keeps the website available.'],
  ['one-page-anatomy.webp', 'The connected sections and systems of a useful one-page business website', 'A focused page still connects identity, trust, action, measurement, and response.'],
];

function campaignBodyHtml(post) {
  if (!post?.bodyHtml || !post.series?.includes('55 Cents a Day')) return post?.bodyHtml || '';
  const articleIndex = Math.max(0, CAMPAIGN_ARTICLES.indexOf(post.slug));
  const selected = [CAMPAIGN_VISUALS[articleIndex % 4], CAMPAIGN_VISUALS[(articleIndex + 2) % 4]];
  const figures = selected.map(([file, alt, caption]) => `<figure class="article-inline-visual"><img src="/blog-images/${file}" alt="${alt}" width="1600" height="900" loading="lazy"><figcaption><img src="/brand/famtastic-mark.svg" alt="" width="28" height="28">FAMtastic Designs — ${caption}</figcaption></figure>`);
  let paragraph = 0;
  return post.bodyHtml.replace(/<\/p>/g, (closing) => {
    paragraph += 1;
    if (paragraph === 3) return closing + figures[0];
    if (paragraph === 9) return closing + figures[1];
    return closing;
  });
}

/**
 * /blog/:slug — single blog_post node: hero, date, full body.
 */
export default function BlogPostPage() {
  const { slug } = useParams();
  const [state, setState] = useState({ post: null, seriesPosts: [], loading: true });

  useEffect(() => {
    let cancelled = false;
    setState({ post: null, seriesPosts: [], loading: true });
    getNodesRaw('blog_post', {
      include: 'field_blog_category,field_blog_tags,field_blog_series,field_related_faqs',
      limit: 100,
    }).then(({ data, included }) => {
      if (!cancelled) {
        const posts = data.map((node) => transformBlogNode(node, included)).filter(Boolean);
        const post = transformBlogNode(matchBySlug(data, slug), included);
        const seriesPosts = post?.series
          ? posts.filter((item) => item.series === post.series).sort((a, b) => a.seriesOrder - b.seriesOrder)
          : [];
        setState({ post, seriesPosts, loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  useEffect(() => {
    if (!state.post) return undefined;
    applySeo(blogSeo(state.post));
    const schema = document.createElement('script');
    schema.type = 'application/ld+json';
    schema.dataset.famtasticBlogSchema = 'true';
    const canonical = `https://famtasticdesigns.com/blog/${state.post.slug}/`;
    const graph = [{
      '@type': 'Article',
      headline: state.post.title,
      description: state.post.metaDescription || state.post.summary,
      datePublished: state.post.created,
      dateModified: state.post.changed,
      author: { '@type': 'Organization', name: 'FAMtastic Designs' },
      publisher: { '@type': 'Organization', name: 'FAMtastic Designs', url: 'https://famtasticdesigns.com/' },
      mainEntityOfPage: canonical,
      ...(state.post.visual?.src ? { image: `https://famtasticdesigns.com${state.post.visual.src}` } : {}),
      keywords: [state.post.seoBrief?.primary_keyword, ...(state.post.seoBrief?.secondary_keywords ?? [])].filter(Boolean),
    }];
    graph.push({
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Blogs', item: 'https://famtasticdesigns.com/blog/' },
        ...(state.post.series ? [{ '@type': 'ListItem', position: 2, name: state.post.series, item: canonical }] : []),
        { '@type': 'ListItem', position: state.post.series ? 3 : 2, name: state.post.title, item: canonical },
      ],
    });
    if (state.post.seriesOrder === 1 && state.seriesPosts.length > 1) {
      graph.push({
        '@type': 'ItemList',
        name: state.post.series,
        itemListElement: state.seriesPosts.map((item, index) => ({
          '@type': 'ListItem',
          position: index + 1,
          name: item.title,
          url: `https://famtasticdesigns.com/blog/${item.slug}/`,
        })),
      });
    }
    if (state.post.faqs.length) {
      graph.push({
        '@type': 'FAQPage',
        url: canonical,
        mainEntity: state.post.faqs.map((faq) => ({
          '@type': 'Question',
          name: faq.question,
          acceptedAnswer: { '@type': 'Answer', text: faq.answer.replace(/<[^>]+>/g, ' ') },
        })),
      });
    }
    schema.textContent = JSON.stringify({ '@context': 'https://schema.org', '@graph': graph });
    document.head.appendChild(schema);
    return () => schema.remove();
  }, [state.post]);

  if (state.loading) {
    return <div className="v1-loading" role="status">Loading post…</div>;
  }

  const post = state.post;

  if (!post) {
    return (
      <Section>
        <div className="v1-empty">
          <strong>We could not find that post.</strong>
          <br />
          <Link to="/blog">Browse all posts</Link>.
        </div>
      </Section>
    );
  }

  const seriesIndex = state.seriesPosts.findIndex((item) => item.id === post.id);
  const previous = seriesIndex > 0 ? state.seriesPosts[seriesIndex - 1] : null;
  const next = seriesIndex >= 0 ? state.seriesPosts[seriesIndex + 1] : null;
  const renderedBodyHtml = campaignBodyHtml(post);

  return (
    <article>
      <Hero eyebrow={post.dateLabel || 'Blogs'} title={post.title} lede={post.summary} />

      <Section>
        <Link to="/blog" className="v1-back-link">
          ← All posts
        </Link>
        {post.visual?.src && (
          <figure className="blog-visual">
            <img src={post.visual.src} alt={post.visual.alt} width="1600" height="900" loading="eager" />
            <figcaption>
              <img src={post.visual.brand_mark || '/brand/famtastic-mark.svg'} alt="" width="32" height="32" />
              <span>{post.visual.caption}</span>
            </figcaption>
          </figure>
        )}
        {renderedBodyHtml ? (
          <FadeUp className="v1-panel">
            <div className="v1-prose" dangerouslySetInnerHTML={{ __html: renderedBodyHtml }} />
          </FadeUp>
        ) : (
          <div className="v1-empty">This post is being published — check back soon.</div>
        )}
        {post.tags.length > 0 && (
          <div className="blog-tags blog-tags--article" aria-label="Article topics">
            {post.tags.map((tag) => <span key={tag}>{tag}</span>)}
          </div>
        )}
        {post.series && (
          <nav className="blog-series-nav" aria-label={`${post.series} series navigation`}>
            <div>
              <span>Article {seriesIndex + 1} of {state.seriesPosts.length}</span>
              <strong>{post.series}</strong>
            </div>
            <div className="blog-series-nav__links">
              {previous && <Link to={`/blog/${previous.slug}`}>← {previous.title}</Link>}
              {next && <Link to={`/blog/${next.slug}`}>{next.title} →</Link>}
            </div>
          </nav>
        )}
      </Section>

      {post.faqs.length > 0 && (
        <Section eyebrow="FAQ" title="Questions related to this topic">
          <FAQAccordion items={post.faqs} />
        </Section>
      )}

      <CTABanner
        title="Turn the idea into a useful next step"
        primaryCta={{ label: post.ctaText, href: post.ctaHref }}
      />
    </article>
  );
}
