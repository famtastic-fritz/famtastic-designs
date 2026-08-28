import { Panel, Empty, title, date } from './PortalShared.jsx';

export default function PortalMessagesView({
  workspace,
  org,
  activeThread,
  setActiveThread,
  viewThread,
  onReplyThread,
  onOpenThread,
  busy,
}) {
  return (
    <section className="portal-grid two">
      <Panel eyebrow="Conversations" title="Your history">
        {workspace.threads?.length ? (
          <ul className="portal-thread-list">
            {workspace.threads.map((thread) => (
              <li key={thread.public_id}>
                <button type="button" onClick={() => viewThread(thread.public_id)}>
                  <strong>{thread.subject}</strong>
                  <small>
                    {title(thread.status)} · {date(thread.changed)}
                  </small>
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <Empty>No conversations yet.</Empty>
        )}
      </Panel>

      {activeThread ? (
        <Panel
          eyebrow={title(activeThread.thread?.kind || 'support')}
          title={activeThread.thread?.subject}
          className="portal-conversation"
        >
          <button
            type="button"
            className="portal-back"
            onClick={() => setActiveThread(null)}
          >
            ← All conversations
          </button>
          <ol>
            {(activeThread.messages || []).map((message, i) => (
              <li key={i} className={`is-${message.author_type}`}>
                <span>{message.author_type === 'customer' ? 'You' : 'FAMtastic Team'}</span>
                <p>{message.body}</p>
                <small>{date(message.created)}</small>
              </li>
            ))}
          </ol>
          <form onSubmit={onReplyThread}>
            <label htmlFor="thread-reply">Reply</label>
            <textarea
              id="thread-reply"
              name="body"
              required
              placeholder="Type your message here…"
            />
            <button disabled={busy}>{busy ? 'Sending…' : 'Send reply'}</button>
          </form>
        </Panel>
      ) : (
        <Panel eyebrow="New conversation" title="Ask FAMtastic">
          <p>Choose the affected area so your request reaches us with the right context.</p>
          <form onSubmit={onOpenThread}>
            <label>
              Area
              <select name="kind">
                <option value="support">Website or service issue</option>
                <option value="project">Project or approval</option>
                <option value="billing">Billing or renewal</option>
              </select>
            </label>
            <label>
              Subject
              <input name="subject" required placeholder="Brief summary of your question" />
            </label>
            <label>
              What happened?
              <textarea
                name="body"
                required
                placeholder="Describe what you need or what needs attention…"
              />
            </label>
            <button disabled={busy}>{busy ? 'Sending…' : 'Send request'}</button>
          </form>
        </Panel>
      )}
    </section>
  );
}
