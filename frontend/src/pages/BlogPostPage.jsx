import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { applySeo } from '../components/SEO.jsx';
import { blogSeo } from '../seo.js';
import { Hero, Section, CTABanner, FadeUp, FAQAccordion } from '../components/v1/index.js';

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
      author: { '@type': 'Organization', name: 'FAMtastic Designs' },
      publisher: { '@type': 'Organization', name: 'FAMtastic Designs' },
      mainEntityOfPage: canonical,
    }];
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

  return (
    <article>
      <Hero eyebrow={post.dateLabel || 'Blog'} title={post.title} lede={post.summary} />

      <Section>
        <Link to="/blog" className="v1-back-link">
          ← All posts
        </Link>
        {post.bodyHtml ? (
          <FadeUp className="v1-panel">
            <div className="v1-prose" dangerouslySetInnerHTML={{ __html: post.bodyHtml }} />
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
