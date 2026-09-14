import { useState } from 'react';
import { Panel, Empty } from './PortalShared.jsx';
import { conversationNextAction, messageContextUrl, messageDate, messageDeliveryLabel } from '../../lib/portalMessaging.js';

const proofLabels = {
  not_started: 'Proofs have not started',
  queued: 'Proofs queued',
  generating: 'Proofs in progress',
  owner_review: 'Proofs in FAMtastic review',
  customer_ready: 'Proofs ready for customer review',
  notified: 'Proofs ready for customer review',
  selected: 'Direction selected',
  revision_requested: 'Changes requested',
  failed: 'Proofs need attention',
};

export default function PortalMessagesView({ workspace, messages }) {
  const [filter, setFilter] = useState('needs_reply');
  const [search, setSearch] = useState('');
  const [composing, setComposing] = useState(false);
  const [drafts, setDrafts] = useState({});
  const { inbox, inboxLoading, activeThread, threadLoading, busy, error, notice, updatedAt, refresh, loadThread, closeThread, reply, create } = messages;
  const isStaff = inbox?.is_staff === true;
  const threads = inbox?.threads || [];
  const waiting = threads.filter((thread) => !thread.needs_reply && !['closed', 'resolved'].includes(thread.status));
  const filters = [
    ['needs_reply', 'Needs reply', inbox?.needs_reply_count],
    ['unread', 'Unread', inbox?.unread_count],
    ['waiting', 'Waiting', waiting.length],
    ['all', 'All', threads.length],
  ];
  const visibleThreads = threads.filter((thread) => {
    if (filter === 'needs_reply' && !thread.needs_reply) return false;
    if (filter === 'unread' && !thread.unread_count) return false;
    if (filter === 'waiting' && !waiting.includes(thread)) return false;
    const haystack = `${thread.subject} ${thread.customer_name || ''} ${thread.customer_email || ''} ${thread.context?.project_name || ''} ${thread.last_message_preview || ''}`.toLowerCase();
    return haystack.includes(search.toLowerCase());
  });
  const thread = activeThread?.thread;
  const context = thread?.context || {};
  const contextUrl = messageContextUrl(context, isStaff);
  const draft = drafts[thread?.public_id] || '';

  const open = (id) => {
    setComposing(false);
    loadThread(id);
  };
  const sendReply = async (event) => {
    event.preventDefault();
    const id = thread.public_id;
    if (await reply(draft)) setDrafts((current) => ({ ...current, [id]: '' }));
  };

  return (
    <section className={`portal-inbox ${thread || threadLoading || composing ? 'has-conversation' : ''}`} aria-label="Message inbox">
      <div className="portal-inbox-intro">
        <div>
          <p>{isStaff ? 'All client conversations. Know who needs your reply.' : 'Your conversations with FAMtastic, together in one place.'}</p>
          <small>{updatedAt ? `Updated ${new Date(updatedAt).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })} · refreshes every 30 seconds` : 'Connecting to your inbox…'}</small>
        </div>
        <div className="portal-inbox-actions">
          <button type="button" className="secondary" disabled={inboxLoading} onClick={refresh}>{inboxLoading ? 'Refreshing…' : 'Refresh'}</button>
          {!isStaff && workspace?.organization && <button type="button" className="secondary" onClick={() => { closeThread(); setComposing(true); }}>New message</button>}
          {isStaff && inbox.admin_inbox_url === '/web/admin/famtastic/messages' && <a href={inbox.admin_inbox_url}>Open in admin ↗</a>}
        </div>
      </div>

      {error && <div className="portal-inbox-feedback is-error" role="alert">{error}</div>}
      {notice && <div className="portal-inbox-feedback" role="status">{notice}</div>}

      <div className="portal-inbox-layout">
        <Panel eyebrow={isStaff ? 'FAMtastic Concierge' : 'Your inbox'} title="Conversations" className="portal-inbox-list">
          <div className="portal-inbox-filters" role="group" aria-label="Filter conversations">
            {filters.map(([id, label, count]) => (
              <button key={id} type="button" aria-pressed={filter === id} onClick={() => setFilter(id)}>
                {label}{inbox && <b>{count ?? 0}</b>}
              </button>
            ))}
          </div>
          <label className="portal-inbox-search">
            <span className="portal-sr-only">Search conversations</span>
            <input type="search" placeholder="Search name, email or project" value={search} onChange={(event) => setSearch(event.target.value)} />
          </label>
          {inboxLoading && !inbox ? <p role="status">Loading conversations…</p> : !inbox ? (
            <Empty>Messages are unavailable. Refresh to try again.</Empty>
          ) : visibleThreads.length ? (
            <ul className="portal-thread-list">
              {visibleThreads.map((item) => (
                <li key={item.public_id}>
                  <button type="button" aria-current={thread?.public_id === item.public_id ? 'true' : undefined} onClick={() => open(item.public_id)}>
                    <span className="portal-inbox-thread-title">
                      <strong>{isStaff ? item.customer_name || item.customer_email || item.subject : item.subject}</strong>
                      {item.unread_count > 0 && <span className="portal-inbox-unread" aria-label={`${item.unread_count} unread messages`}>{item.unread_count}</span>}
                    </span>
                    {isStaff && <span className="portal-inbox-subject">{item.subject}</span>}
                    <span className="portal-inbox-preview">{item.last_message_preview || 'Open to read this conversation.'}</span>
                    <span className="portal-inbox-thread-meta"><small>{item.source === 'contact_form' ? 'Contact form' : 'Portal'} · {messageDate(item.last_message_at)}</small></span>
                    <span className={`portal-inbox-turn ${item.needs_reply ? 'needs-reply' : ''}`}>{conversationNextAction(item, isStaff)}</span>
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <div className="portal-inbox-empty">
              <span aria-hidden="true">✉</span>
              <p>{search ? 'No conversations match your search.' : filter === 'needs_reply' ? 'No conversations need your reply.' : filter === 'unread' ? 'You have no unread messages.' : filter === 'waiting' ? 'No conversations are waiting.' : 'No conversations yet.'}</p>
              {filter !== 'all' && threads.length > 0 && <button type="button" className="secondary" onClick={() => setFilter('all')}>View all conversations</button>}
            </div>
          )}
        </Panel>

        <Panel className="portal-conversation portal-inbox-detail">
          {(thread || composing || threadLoading) && <button type="button" className="portal-back" onClick={() => { closeThread(); setComposing(false); }}>← All conversations</button>}
          {threadLoading ? <p role="status">Opening conversation…</p> : thread ? (
            <>
              <header className="portal-inbox-conversation-head">
                <span>{thread.source === 'contact_form' ? 'Contact form' : 'Portal conversation'}{thread.case_number ? ` · ${thread.case_number}` : ''}</span>
                <h2>{isStaff ? thread.customer_name || thread.customer_email || thread.subject : thread.subject}</h2>
                {isStaff && <p>{thread.subject}<small>{thread.customer_email}</small></p>}
                <div className={`portal-inbox-next ${thread.needs_reply ? 'needs-reply' : ''}`}>
                  <strong>{conversationNextAction(thread, isStaff)}</strong>
                  {thread.needs_reply && <span>{isStaff ? 'The customer is waiting.' : 'FAMtastic has replied.'}</span>}
                </div>
              </header>

              {(context.project_name || context.request_public_id || context.order_id) ? (
                <aside className="portal-inbox-context" aria-label="Linked project">
                  <div><strong>{context.project_name || 'Linked project'}</strong><small>{proofLabels[context.proof_status] || 'Proof status not recorded'}{context.order_id ? ` · Order ${context.order_id}` : ''}</small></div>
                  {contextUrl && <a href={contextUrl}>{isStaff ? 'Open project / proofs' : 'Open project'} ↗</a>}
                </aside>
              ) : <p className="portal-inbox-no-context">No project is linked to this conversation yet.{isStaff && /^\/web\/admin\/famtastic\/intake\/\d+\/edit$/.test(context.admin_intake_url || '') && <a href={context.admin_intake_url}>Open original contact request ↗</a>}</p>}

              <ol aria-label="Conversation history">
                {(activeThread.messages || []).map((message) => (
                  <li key={message.id} className={`is-${message.author_type}`}>
                    <span>{message.author_type === 'customer' ? isStaff ? thread.customer_name || 'Customer' : 'You' : 'FAMtastic Concierge'}</span>
                    <p>{message.body}</p>
                    <small>{messageDate(message.created)} · {messageDeliveryLabel(message.delivery_status)}</small>
                  </li>
                ))}
              </ol>

              {inbox?.can_reply ? (
                <form onSubmit={sendReply} className="portal-inbox-reply">
                  <label htmlFor="thread-reply">{isStaff ? 'Reply to client' : 'Reply to FAMtastic'}</label>
                  <textarea id="thread-reply" name="body" required maxLength={10000} value={draft} onChange={(event) => setDrafts((current) => ({ ...current, [thread.public_id]: event.target.value }))} placeholder="Write a reply…" />
                  <div><small>{isStaff ? 'Your reply is saved here. Email status is tracked separately.' : 'Your reply stays connected to this conversation.'}</small><button disabled={busy || !draft.trim()}>{busy ? 'Saving reply…' : 'Send reply'}</button></div>
                </form>
              ) : <p>Replying is unavailable for this account.</p>}
            </>
          ) : composing && workspace?.organization ? (
            <>
              <span className="portal-eyebrow">New conversation</span>
              <h2>Message FAMtastic</h2>
              <form onSubmit={async (event) => { event.preventDefault(); const form = event.currentTarget; if (await create(Object.fromEntries(new FormData(form)))) { form.reset(); setComposing(false); } }}>
                <label>Area<select name="kind"><option value="support">Website or service issue</option><option value="project">Project or approval</option><option value="billing">Billing or renewal</option></select></label>
                <label>Subject<input name="subject" required maxLength={255} placeholder="What do you need help with?" /></label>
                <label>Message<textarea name="body" required maxLength={10000} placeholder="Tell us what needs attention…" /></label>
                <button disabled={busy}>{busy ? 'Saving message…' : 'Send message'}</button>
              </form>
            </>
          ) : (
            <div className="portal-inbox-empty portal-inbox-welcome">
              <span aria-hidden="true">✉</span>
              <h2>Every conversation, with context.</h2>
              <p>Open a conversation to read the message, see whose turn it is, and reply.</p>
              {isStaff && inbox?.admin_orders_url === '/web/admin/commerce/orders' && <a href={inbox.admin_orders_url}>Open client orders ↗</a>}
            </div>
          )}
        </Panel>
      </div>
    </section>
  );
}
