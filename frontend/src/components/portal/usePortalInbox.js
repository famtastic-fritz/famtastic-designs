import { useCallback, useEffect, useRef, useState } from 'react';
import {
  createCustomerThread,
  getCustomerMessages,
  getMessageThread,
  readMessageThread,
  replyMessageThread,
} from '../../api/customer.js';
import { messageDeliveryLabel } from '../../lib/portalMessaging.js';

export default function usePortalInbox({ enabled, isViewing, organization }) {
  const [inbox, setInbox] = useState(null);
  const [inboxLoading, setInboxLoading] = useState(false);
  const [activeThread, setActiveThread] = useState(null);
  const [threadLoading, setThreadLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [updatedAt, setUpdatedAt] = useState(null);
  const selected = useRef(null);
  const inboxSequence = useRef(0);
  const threadSequence = useRef(0);
  const readAttempt = useRef('');
  const pendingReply = useRef(new Map());
  const submitting = useRef(false);

  const refreshInbox = useCallback(async () => {
    const sequence = ++inboxSequence.current;
    setInboxLoading(true);
    try {
      const value = await getCustomerMessages();
      if (sequence === inboxSequence.current) {
        setInbox(value);
        setUpdatedAt(Date.now());
      }
      return value;
    } catch (exception) {
      if (sequence === inboxSequence.current) setError(exception.message || 'Messages could not refresh. Try again.');
      return null;
    } finally {
      if (sequence === inboxSequence.current) setInboxLoading(false);
    }
  }, []);

  const loadThread = useCallback(async (id, quiet = false) => {
    const sequence = ++threadSequence.current;
    selected.current = id;
    if (!quiet) {
      setActiveThread(null);
      setThreadLoading(true);
      setError('');
      setNotice('');
      readAttempt.current = '';
    }
    try {
      const value = await getMessageThread(id);
      if (sequence === threadSequence.current && selected.current === id) setActiveThread(value);
      return value;
    } catch (exception) {
      if (sequence === threadSequence.current) setError(exception.message || 'This conversation could not open.');
      return null;
    } finally {
      if (sequence === threadSequence.current) setThreadLoading(false);
    }
  }, []);

  const closeThread = useCallback(() => {
    selected.current = null;
    threadSequence.current += 1;
    setActiveThread(null);
    setThreadLoading(false);
    setError('');
    setNotice('');
  }, []);

  const refresh = useCallback(async () => {
    setError('');
    readAttempt.current = '';
    const work = [refreshInbox()];
    if (selected.current && isViewing) work.push(loadThread(selected.current, true));
    await Promise.all(work);
  }, [isViewing, loadThread, refreshInbox]);

  useEffect(() => {
    if (!enabled) return undefined;
    refresh();
    const update = () => {
      if (document.visibilityState === 'visible' && !submitting.current) refresh();
    };
    const interval = window.setInterval(update, 30000);
    window.addEventListener('focus', update);
    return () => {
      window.clearInterval(interval);
      window.removeEventListener('focus', update);
    };
  }, [enabled, refresh, refreshInbox]);

  useEffect(() => {
    if (!enabled || !isViewing || !activeThread || document.visibilityState !== 'visible') return;
    const thread = activeThread.thread;
    const lastId = thread?.last_message_id || activeThread.messages?.at(-1)?.id;
    const key = `${thread?.public_id}:${lastId}`;
    if (!lastId || !thread?.unread_count || readAttempt.current === key) return;
    readAttempt.current = key;
    readMessageThread(thread.public_id, lastId)
      .then(() => {
        setActiveThread((current) => current?.thread?.public_id === thread.public_id && current.thread.last_message_id === thread.last_message_id
          ? { ...current, thread: { ...current.thread, unread_count: 0 } }
          : current);
        refreshInbox();
      })
      .catch(() => setError('The conversation opened, but its unread status could not be saved. Refresh to try again.'));
  }, [activeThread, enabled, isViewing, refreshInbox]);

  const reply = async (body) => {
    const id = activeThread?.thread?.public_id;
    if (!id || !body.trim() || submitting.current) return false;
    submitting.current = true;
    setBusy(true);
    setError('');
    setNotice('');
    const previous = pendingReply.current.get(id);
    const clientMessageId = previous?.body === body ? previous.id : crypto.randomUUID();
    pendingReply.current.set(id, { body, id: clientMessageId });
    try {
      const receipt = await replyMessageThread(id, body, clientMessageId);
      pendingReply.current.delete(id);
      setNotice(`Reply saved. ${messageDeliveryLabel(receipt.delivery_status)}.`);
      await Promise.all([refreshInbox(), ...(selected.current === id ? [loadThread(id, true)] : [])]);
      return true;
    } catch (exception) {
      setError(exception.message || 'Your reply could not be confirmed. Your text is still here; try again.');
      return false;
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  };

  const create = async (data) => {
    if (!organization || submitting.current) return false;
    submitting.current = true;
    setBusy(true);
    setError('');
    try {
      const receipt = await createCustomerThread({ ...data, organization });
      await refreshInbox();
      if (receipt.thread?.public_id) await loadThread(receipt.thread.public_id);
      setNotice('Your message is saved. FAMtastic has your request.');
      return true;
    } catch (exception) {
      setError(exception.message || 'Your request could not be saved. Your text is still here.');
      return false;
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  };

  return { inbox, inboxLoading, activeThread, threadLoading, busy, error, notice, updatedAt, refresh, loadThread, closeThread, reply, create };
}
