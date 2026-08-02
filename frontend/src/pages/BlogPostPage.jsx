import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug } from '../utils/content.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { Hero, Section, CTABanner, FadeUp } from '../components/v1/index.js';

/**
 * /blog/:slug — single blog_post node: hero, date, full body.
 */
export default function BlogPostPage() {
  const { slug } = useParams();
  const [state, setState] = useState({ post: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    setState({ post: null, loading: true });
    getNodesRaw('blog_post').then(({ data }) => {
      if (!cancelled) {
        setState({ post: transformBlogNode(matchBySlug(data, slug)), loading: false });
      }
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

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
      </Section>

      <CTABanner
        title="Want a system like this for your business?"
        primaryCta={{ label: 'Explore Your Options', href: '/contact#project-fit' }}
      />
    </article>
  );
}
