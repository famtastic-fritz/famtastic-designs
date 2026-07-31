import { useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router';
import { getNodeByAlias } from '../api/drupal.js';
import { textValue } from '../utils/content.js';

/**
 * Catch-all route: before bouncing to '/', try to resolve the current path
 * as a `page` node alias (e.g. /about, /contact). If a matching page exists
 * it renders as a simple content page; otherwise redirect home as before.
 */
export default function AliasPage() {
  const { pathname } = useLocation();
  const [state, setState] = useState({ node: null, loading: true });

  useEffect(() => {
    let cancelled = false;
    setState({ node: null, loading: true });
    getNodeByAlias('page', pathname).then(({ node }) => {
      if (!cancelled) setState({ node, loading: false });
    });
    return () => {
      cancelled = true;
    };
  }, [pathname]);

  if (state.loading) {
    return <div className="loading" role="status">Loading page…</div>;
  }

  if (!state.node) {
    return <Navigate to="/" replace />;
  }

  const attrs = state.node.attributes ?? {};
  const body = textValue(attrs.body);

  return (
    <article className="node-view">
      <h1 className="node-view__title">{attrs.title ?? 'Page'}</h1>
      {body ? (
        <div
          className="node-view__body"
          dangerouslySetInnerHTML={{ __html: body }}
        />
      ) : (
        <div className="status">
          <p>This page is being published — check back soon.</p>
        </div>
      )}
    </article>
  );
}
