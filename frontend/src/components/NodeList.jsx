import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getNodes, STUB_FLAG } from '../api/drupal.js';

/**
 * Fetches and renders nodes of a given bundle as teaser cards.
 * Each card links to the full node view at /node/:uuid. When the backend is
 * unreachable the API layer supplies clearly-marked stub nodes so the grid
 * still renders.
 */
export default function NodeList({ type = 'article' }) {
  const [nodes, setNodes] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    getNodes(type).then((items) => {
      if (!cancelled) {
        setNodes(items);
        setLoading(false);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [type]);

  if (loading) {
    return <div className="loading">Loading {type} content…</div>;
  }

  if (!nodes.length) {
    return (
      <div className="status">
        <strong>No {type} content yet.</strong>
        <p>Create your first {type} node in Drupal and it will appear here via JSON:API.</p>
      </div>
    );
  }

  return (
    <ul className="node-list">
      {nodes.map((node) => (
        <li key={node.id}>
          <Link to={`/node/${node.id}`} className="node-card">
            <span className="node-card__type">{node.type}</span>
            <h3 className="node-card__title">
              {node.title}
              {node[STUB_FLAG] && <span className="stub-badge">stub</span>}
            </h3>
            {node.summary && <p className="node-card__summary">{node.summary}</p>}
            <span className="node-card__cta">Read more →</span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
