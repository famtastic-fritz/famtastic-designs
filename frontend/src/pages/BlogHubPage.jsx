import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { getNodesRaw } from '../api/drupal.js';
import { transformBlogNode } from '../lib/drupalAdapter.js';
import { Hero, Section, Stagger, Item } from '../components/v1/index.js';

/**
 * /blog — hub listing every blog_post, newest first, as v1 cards.
 */
export default function BlogHubPage() {
  const [posts, setPosts] = useState(null); // null = loading
  const [category, setCategory] = useState('All');

  useEffect(() => {
    let cancelled = false;
    getNodesRaw('blog_post', {
      include: 'field_blog_category,field_blog_tags,field_blog_series',
    }).then(({ data, included }) => {
      if (!cancelled) {
        setPosts(
          data
            .map((node) => transformBlogNode(node, included))
            .filter(Boolean)
            .sort((a, b) => new Date(b.created ?? 0) - new Date(a.created ?? 0)),
        );
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  const categories = ['All', ...new Set((posts ?? []).map((post) => post.category).filter(Boolean))];
  const visiblePosts = category === 'All' ? posts : posts?.filter((post) => post.category === category);

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
          <>
            <div className="blog-filters" aria-label="Filter articles by category">
              {categories.map((item) => (
                <button
                  key={item}
                  type="button"
                  className={`blog-filter${category === item ? ' blog-filter--active' : ''}`}
                  onClick={() => setCategory(item)}
                  aria-pressed={category === item}
                >
                  {item}
                </button>
              ))}
            </div>
            <Stagger className="v1-grid v1-grid--3">
            {visiblePosts.map((post) => (
              <Item key={post.id}>
                <article className="v1-card">
                  <span className="v1-card__kicker">{post.category || post.dateLabel || 'Post'}</span>
                  <h3 className="v1-card__title">{post.title}</h3>
                  <p className="v1-card__text">{post.summary || 'Read the full post.'}</p>
                  {post.series && <p className="blog-series-label">Series: {post.series}</p>}
                  {post.tags.length > 0 && (
                    <div className="blog-tags" aria-label="Topics">
                      {post.tags.map((tag) => <span key={tag}>{tag}</span>)}
                    </div>
                  )}
                  <Link to={`/blog/${post.slug}`} className="v1-card__cta">
                    Read Post →
                  </Link>
                </article>
              </Item>
            ))}
            </Stagger>
          </>
        )}
      </Section>
    </>
  );
}
