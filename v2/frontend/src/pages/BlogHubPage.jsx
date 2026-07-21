import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodesRaw } from '../api/drupal.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { Hero, Section, Stagger, Item } from '../components/v1/index.js';

/**
 * /blog — hub listing every blog_post, newest first, as v1 cards.
 */
export default function BlogHubPage() {
  const [posts, setPosts] = useState(null); // null = loading

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('blog_post').then(({ data }) => {
      if (!cancelled) {
        setPosts(
          data
            .map((node) => transformBlogNode(node))
            .filter(Boolean)
            .sort((a, b) => new Date(b.created ?? 0) - new Date(a.created ?? 0)),
        );
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <>
      <Hero
        eyebrow="Blog"
        title="Notes from the"
        accent="studio"
        lede="Practical thinking on agentic AI, automation, and engineering systems that sell."
      />

      <Section>
        {posts === null && <div className="v1-loading" role="status">Loading posts…</div>}

        {posts !== null && posts.length === 0 && (
          <div className="v1-empty">
            <strong>The first posts are being drafted.</strong>
            <br />
            New articles are on the way — check back shortly.
          </div>
        )}

        {posts !== null && posts.length > 0 && (
          <Stagger className="v1-grid v1-grid--3">
            {posts.map((post) => (
              <Item key={post.id}>
                <article className="v1-card">
                  <span className="v1-card__kicker">{post.dateLabel || 'Post'}</span>
                  <h3 className="v1-card__title">{post.title}</h3>
                  <p className="v1-card__text">{post.summary || 'Read the full post.'}</p>
                  <Link to={`/blog/${post.slug}`} className="v1-card__cta">
                    Read Post →
                  </Link>
                </article>
              </Item>
            ))}
          </Stagger>
        )}
      </Section>
    </>
  );
}
