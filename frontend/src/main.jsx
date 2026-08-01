import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import App from './App.jsx';
import { captureAttribution } from './lib/attribution.js';
import './index.css';

// Record the inbound campaign (?utm_* or ?src=) before React mounts. Routing
// must not run first: a redirect route such as /deal replaces the URL in its
// own effect, which fires before any parent effect could read the original
// query string.
captureAttribution();

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>,
);
