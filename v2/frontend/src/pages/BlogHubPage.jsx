import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { nodeSlug, textValue } from '../utils/content.js';

/**
 * /blog — hub listing every blog_post, newest first.
 */
export default function BlogHubPage() {
  const [posts, setPosts] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('blog_post').then(({ data }) => {
      if (!cancelled) {
        const sorted = [...data].sort(
          (a, b) => new Date(b.attributes?.created ?? 0) - new Date(a.attributes?.created ?? 0),
        );
        setPosts(sorted);
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <section className="hero">
        <span className="hero__eyebrow">Blog</span>
        <h1 className="hero__title">
          Notes from the <span className="accent">studio</span>
        </h1>
        <p className="hero__lede">
          Practical thinking on agentic AI, automation, and engineering systems that sell.
        </p>
      </section>

      {posts === null && <div className="loading" role="status">Loading posts…</div>}

      {posts !== null && posts.length === 0 && (
        <div className="status">
          <p>
            <strong>The first posts are being drafted.</strong>
            <br />
            New articles are on the way — check back shortly.
          </p>
        </div>
      )}

      {posts !== null && posts.length > 0 && (
        <ul className="node-list">
          {posts.map((node) => {
            const attrs = node.attributes ?? {};
            const created = attrs.created
              ? new Date(attrs.created).toLocaleDateString(undefined, {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
                })
              : '';
            const summary =
              textValue(attrs.body?.summary) ||
              textValue(attrs.field_summary) ||
              'Read the full post.';
            return (
              <li key={node.id}>
                <Link to={`/blog/${nodeSlug(node)}`} className="node-card">
                  <span className="node-card__type">{created || 'Post'}</span>
                  <h3 className="node-card__title">{attrs.title ?? 'Untitled post'}</h3>
                  <p className="node-card__summary">{summary}</p>
                  <span className="node-card__cta">Read Post →</span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
