import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { matchBySlug, textValue } from '../utils/content.js';

/**
 * /blog/:slug — single blog_post node: title, date, full body.
 */
export default function BlogPostPage() {
  const { slug } = useParams();
  const [state, setState] = useState({ node: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('blog_post').then(({ data }) => {
      if (!cancelled) setState({ node: matchBySlug(data, slug), loading: false });
    });
    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (state.loading) {
    return <div className="loading" role="status">Loading post…</div>;
  }

  if (!state.node) {
    return (
      <div className="status">
        <p>
          <strong>We could not find that post.</strong>
          <br />
          <Link to="/blog">Browse all posts</Link>.
        </p>
      </div>
    );
  }

  const attrs = state.node.attributes ?? {};
  const created = attrs.created
    ? new Date(attrs.created).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : '';
  const body = textValue(attrs.body);

  return (
    <article className="node-view">
      <Link to="/blog" className="node-view__back">
        ← All posts
      </Link>
      <h1 className="node-view__title">{attrs.title ?? 'Untitled post'}</h1>
      {created && <p className="node-view__meta">{created}</p>}

      {body ? (
        <div
          className="node-view__body"
          dangerouslySetInnerHTML={{ __html: body }}
        />
      ) : (
        <div className="status">
          <p>This post is being published — check back soon.</p>
        </div>
      )}
    </article>
  );
}
