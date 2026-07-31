import { useEffect, useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router';
import { getNode, STUB_FLAG } from '../api/drupal.js';

/**
 * Full node view. Fetches a single node by its UUID route param and renders
 * title, meta, and the processed body.
 */
export default function NodeView() {
  const { uuid } = useParams();
  const navigate = useNavigate();
  const [node, setNode] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    getNode(uuid).then((result) => {
      if (!cancelled) {
        setNode(result);
        setLoading(false);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [uuid]);

  if (loading) {
    return <div className="loading">Loading content…</div>;
  }

  if (!node) {
    return (
      <div className="status">
        <strong>Content not found.</strong>
        <p>No published node with UUID {uuid} could be loaded from the backend.</p>
        <p>
          <Link to="/">← Back to home</Link>
        </p>
      </div>
    );
  }

  const created = node.created
    ? new Date(node.created).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : null;

  return (
    <article className="node-view">
      <button type="button" className="node-view__back" onClick={() => navigate(-1)}>
        ← Back
      </button>

      <h1 className="node-view__title">
        {node.title}
        {node[STUB_FLAG] && <span className="stub-badge">stub</span>}
      </h1>

      <div className="node-view__meta">
        {node.type}
        {created ? ` · ${created}` : ''}
      </div>

      {/* Phase 1 trust assumption: body HTML comes from our own Drupal backend
          and is already filtered by Drupal's text formats, so rendering it
          unsanitized is acceptable for the scaffold. Revisit before exposing
          to untrusted input. */}
      <div
        className="node-view__body"
        dangerouslySetInnerHTML={{ __html: node.body }}
      />
    </article>
  );
}
