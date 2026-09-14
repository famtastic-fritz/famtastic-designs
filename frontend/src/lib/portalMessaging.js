export function messageDeliveryLabel(status) {
  return {
    received: 'Received',
    queued: 'Email queued',
    sending: 'Email being sent',
    sent: 'Email accepted by mail service',
    delivered: 'Email delivery confirmed',
    failed: 'Email failed · reply saved here',
    portal_only: 'Saved in this conversation',
  }[status] || 'Email status unavailable';
}

export function conversationNextAction(thread, isStaff) {
  if (thread?.status === 'closed' || thread?.status === 'resolved') return 'Conversation closed';
  if (thread?.needs_reply) return isStaff ? 'Needs your reply' : 'Your turn to reply';
  if (thread?.last_author_type === 'customer') return isStaff ? 'Customer message received' : 'Waiting for FAMtastic';
  if (thread?.last_author_type === 'staff') return isStaff ? 'Waiting for customer' : 'FAMtastic replied';
  return 'Open conversation';
}

export function messageDate(stamp) {
  if (!stamp) return 'Time unavailable';
  const value = new Date(Number(stamp) * 1000);
  if (Number.isNaN(value.getTime())) return 'Time unavailable';
  return value.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

// Server context may name a destination only inside the authenticated app.
export function messageContextUrl(context = {}, isStaff = false) {
  if (isStaff) {
    const value = context.admin_request_url;
    return typeof value === 'string' && /^\/web\/admin\/[a-zA-Z0-9/_-]+$/.test(value) ? value : null;
  }
  return context.request_public_id
    ? `/portal?tab=projects&request=${encodeURIComponent(context.request_public_id)}`
    : null;
}
