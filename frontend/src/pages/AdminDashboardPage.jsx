import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useUser } from '../auth/UserContext.jsx';
import { getMyProjects, createProject, deleteProject } from '../api/drupal.js';

const STATUS_OPTIONS = ['discovery', 'active', 'review', 'complete'];

const EMPTY_FORM = { title: '', client: '', status: 'discovery', budget: '', dueDate: '' };

/** Human-friendly money cell (budgets are stored as plain numbers). */
function formatBudget(value) {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';
  return `$${Number(value).toLocaleString('en-US')}`;
}

/** Human-friendly date cell (stored as ISO YYYY-MM-DD). */
function formatDate(value) {
  if (!value) return '—';
  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US');
}

/**
 * Admin dashboard: list Client Projects, create new ones inline, delete
 * existing ones. Requires an authenticated session — ProtectedRoute guards
 * the route, but this page also renders a graceful login-required state.
 */
export default function AdminDashboardPage() {
  const { user, token, isAuthenticated, loading: authLoading } = useUser();

  const [projects, setProjects] = useState([]);
  const [listState, setListState] = useState('idle'); // idle | loading | error
  const [listError, setListError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [deletingId, setDeletingId] = useState(null);

  const loadProjects = useCallback(async () => {
    if (!token) return;
    setListState('loading');
    setListError('');
    try {
      setProjects(await getMyProjects(token));
      setListState('idle');
    } catch (err) {
      setListError(err.message);
      setListState('error');
    }
  }, [token]);

  useEffect(() => {
    if (isAuthenticated) loadProjects();
  }, [isAuthenticated, loadProjects]);

  function updateField(field) {
    return (event) => setForm((prev) => ({ ...prev, [field]: event.target.value }));
  }

  async function handleCreate(event) {
    event.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      await createProject(form, token);
      setForm(EMPTY_FORM);
      setShowForm(false);
      await loadProjects();
    } catch (err) {
      setFormError(err.message || 'Could not create the project.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(project) {
    if (!window.confirm(`Delete project “${project.title}”? This cannot be undone.`)) return;
    setDeletingId(project.id);
    try {
      await deleteProject(project.id, token);
      setProjects((prev) => prev.filter((p) => p.id !== project.id));
    } catch (err) {
      setListError(err.message || 'Could not delete the project.');
    } finally {
      setDeletingId(null);
    }
  }

  /* Graceful unauthenticated state (ProtectedRoute normally redirects first). */
  if (!authLoading && !isAuthenticated) {
    return (
      <div className="status">
        <strong>Login required.</strong>
        <p>
          You need an active session to view the admin dashboard.{' '}
          <Link to="/login?redirect=%2Fadmin">Sign in</Link> to continue.
        </p>
      </div>
    );
  }

  return (
    <section aria-labelledby="admin-heading">
      <div className="section-heading">
        <h2 id="admin-heading">Client Projects</h2>
        <span className="hint">
          {user?.email ? `Signed in as ${user.email} · ` : ''}JSON:API · node/client_project
        </span>
      </div>

      <div className="admin-toolbar">
        <button
          type="button"
          className="btn btn--lime"
          onClick={() => {
            setShowForm((v) => !v);
            setFormError('');
          }}
        >
          {showForm ? 'Cancel' : 'New project'}
        </button>
        <button
          type="button"
          className="btn btn--ghost"
          onClick={loadProjects}
          disabled={listState === 'loading'}
        >
          Refresh
        </button>
      </div>

      {showForm && (
        <form className="form admin-create" onSubmit={handleCreate}>
          <div className="form__row">
            <div className="form__field">
              <label className="form__label" htmlFor="project-title">
                Title
              </label>
              <input
                id="project-title"
                className="form__input"
                type="text"
                required
                value={form.title}
                onChange={updateField('title')}
                placeholder="Website redesign"
              />
            </div>
            <div className="form__field">
              <label className="form__label" htmlFor="project-client">
                Client
              </label>
              <input
                id="project-client"
                className="form__input"
                type="text"
                required
                value={form.client}
                onChange={updateField('client')}
                placeholder="Acme Corp"
              />
            </div>
          </div>

          <div className="form__row">
            <div className="form__field">
              <label className="form__label" htmlFor="project-status">
                Status
              </label>
              <select
                id="project-status"
                className="form__input"
                value={form.status}
                onChange={updateField('status')}
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option} value={option}>
                    {option.replace('_', ' ')}
                  </option>
                ))}
              </select>
            </div>
            <div className="form__field">
              <label className="form__label" htmlFor="project-budget">
                Budget (USD)
              </label>
              <input
                id="project-budget"
                className="form__input"
                type="number"
                min="0"
                step="0.01"
                value={form.budget}
                onChange={updateField('budget')}
                placeholder="15000"
              />
            </div>
            <div className="form__field">
              <label className="form__label" htmlFor="project-due">
                Due date
              </label>
              <input
                id="project-due"
                className="form__input"
                type="date"
                value={form.dueDate}
                onChange={updateField('dueDate')}
              />
            </div>
          </div>

          {formError && (
            <div className="alert alert--error" role="alert">
              {formError}
            </div>
          )}

          <button className="btn btn--lime" type="submit" disabled={submitting}>
            {submitting ? 'Creating…' : 'Create project'}
          </button>
        </form>
      )}

      {listState === 'error' && (
        <div className="alert alert--error" role="alert">
          {listError || 'Could not load projects.'}{' '}
          <button type="button" className="alert__retry" onClick={loadProjects}>
            Try again
          </button>
        </div>
      )}

      {listState === 'loading' ? (
        <div className="loading" role="status" aria-live="polite">
          Loading projects…
        </div>
      ) : projects.length === 0 && listState !== 'error' ? (
        <div className="status">
          <strong>No projects yet.</strong>
          <p>Use “New project” to add your first client project.</p>
        </div>
      ) : (
        <div className="admin-table__wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th scope="col">Title</th>
                <th scope="col">Client</th>
                <th scope="col">Status</th>
                <th scope="col">Budget</th>
                <th scope="col">Due date</th>
                <th scope="col">
                  <span className="visually-hidden">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {projects.map((project) => (
                <tr key={project.id}>
                  <td>{project.title}</td>
                  <td>{project.client || '—'}</td>
                  <td>
                    <span className={`status-pill status-pill--${project.status}`}>
                      {String(project.status).replace('_', ' ')}
                    </span>
                  </td>
                  <td>{formatBudget(project.budget)}</td>
                  <td>{formatDate(project.dueDate)}</td>
                  <td className="admin-table__actions">
                    <button
                      type="button"
                      className="btn btn--danger btn--sm"
                      onClick={() => handleDelete(project)}
                      disabled={deletingId === project.id}
                    >
                      {deletingId === project.id ? 'Deleting…' : 'Delete'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
