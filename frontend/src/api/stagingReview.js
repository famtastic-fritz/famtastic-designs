/** Submit exactly the artifact displayed when the account owner chose acceptance. */
export async function acceptDisplayedStagingReview(requestId, receiptHash, { accept, refresh }) {
  if (!/^[a-f0-9]{64}$/.test(receiptHash || '')) throw new Error('Refresh and review the current website before accepting it.');
  try {
    return await accept(requestId, receiptHash);
  } finally {
    // A stale reply refreshes the view; it never automatically accepts the new hash.
    await refresh();
  }
}
