#!/usr/bin/env python3
"""HTTP assertions against the disposable SQLite fixture, never a live host."""
import http.cookiejar
import json
import pathlib
import re
import sys
import urllib.error
import urllib.request

base, state_path, evidence_path = sys.argv[1:]
assert base.startswith('http://127.0.0.1:'), 'Only a local disposable runtime is permitted.'
state = json.loads(pathlib.Path(state_path).read_text())
evidence = pathlib.Path(evidence_path)
results = {}

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def call(browser, path, payload=None, token=None):
    headers = {'Content-Type': 'application/json'}
    if token:
        headers['X-CSRF-Token'] = token
    request = urllib.request.Request(base + path, headers=headers, data=json.dumps(payload).encode() if payload is not None else None)
    try:
        response = browser.open(request)
    except urllib.error.HTTPError as error:
        response = error
    text = response.read().decode()
    try:
        body = json.loads(text)
    except json.JSONDecodeError:
        body = text
    return response.status, body, dict(response.headers)

def check(name, condition):
    results[name] = bool(condition)
    (evidence / 'http-results.json').write_text(json.dumps(results, indent=2))
    assert condition, name

def login(kind):
    browser = client()
    code, payload, headers = call(browser, '/api/customer/login', {'email': state['accounts'][kind]['email'], 'password': 'Fixture-inbox-pass-2026!'})
    if code != 200:
        (evidence / (kind + '-login-error.json')).write_text(json.dumps(payload, indent=2))
    check(kind + '_real_password_login', code == 200)
    return browser, payload

check('anonymous_inbox_denied', call(client(), '/api/customer/messages')[0] == 401)
check('staff_wrong_password_denied', call(client(), '/api/customer/login', {'email': state['accounts']['staff']['email'], 'password': 'Wrong-password!'})[0] == 403)
check('unverified_customer_login_denied', call(client(), '/api/customer/login', {'email': state['accounts']['unverified']['email'], 'password': 'Fixture-inbox-pass-2026!'})[0] == 403)
staff, session = login('staff')
check('staff_without_customer_can_sign_in_by_email', session.get('can_manage_messages') is True and session.get('customer') is None and session.get('organizations') == [] and session.get('staff', {}).get('email') == state['accounts']['staff']['email'])
code, inbox, headers = call(staff, '/api/customer/messages')
check('migrated_contact_visible_and_needs_reply', code == 200 and len(inbox['threads']) == 1 and inbox['needs_reply_count'] == 1 and inbox['unread_count'] == 1)
thread = inbox['threads'][0]['public_id']
check('original_contact_message_preserved', 'original contact form message' in inbox['threads'][0]['last_message_preview'])
check('private_api_no_store', 'no-store' in headers.get('Cache-Control', ''))
check('linked_project_context', inbox['threads'][0]['context']['request_id'] == state['request_id'])
code, html, _ = call(staff, '/admin/famtastic/messages?q=proofs')
(evidence / 'admin-inbox.html').write_text(html if isinstance(html, str) else json.dumps(html))
check('real_drupal_inbox_controls_render', code == 200 and '<input type="search"' in html and '<select name="status">' in html and 'Filter messages' in html)
code, html, _ = call(staff, '/admin/famtastic/messages/' + thread)
(evidence / 'admin-conversation.html').write_text(html if isinstance(html, str) else json.dumps(html))
check('real_drupal_reply_form_and_original_message', code == 200 and 'Send reply' in html and 'original contact form message' in html and 'form_token' in html)
for metric in ['website-requests', 'support', 'notifications', 'customers', 'campaigns', 'open-jobs', 'open-exceptions']:
    code, html, _ = call(staff, '/admin/famtastic/metric/' + metric)
    (evidence / ('metric-' + metric + '.html')).write_text(html if isinstance(html, str) else json.dumps(html))
    check('metric_' + metric + '_renders', code == 200 and 'An unexpected error' not in html and 'Filter' in html)
    if metric == 'website-requests':
        check('website_requests_default_excludes_customer_archived', 'Inbox Fixture Website' in html and 'Customer Archived Duplicate Fixture' not in html)
        check('website_requests_archived_option_without_legacy_status', '<option value="archived">Archived</option>' in html)
        check('website_requests_brief_details_survive_render', '<details class="famtastic-request-brief"' in html and '<summary>View brief</summary>' in html)
        head = re.search(r'<thead>(.*?)</thead>', html, re.S)
        check('website_requests_five_columns', head is not None and len(re.findall(r'<th(?:\s|>)', head.group(1))) == 5)
code, html, _ = call(staff, '/admin/famtastic/metric/website-requests?q=Inbox&status=submitted')
check('website_requests_filter_values_preserved', code == 200 and 'value="Inbox"' in html and 'value="submitted" selected' in html and 'Inbox Fixture Website' in html)
code, html, _ = call(staff, '/admin/famtastic/metric/website-requests?status=submitted')
check('website_requests_normal_status_excludes_customer_archived', code == 200 and 'Inbox Fixture Website' in html and 'Customer Archived Duplicate Fixture' not in html)
code, html, _ = call(staff, '/admin/famtastic/metric/website-requests?status=archived')
(evidence / 'metric-website-requests-archived.html').write_text(html if isinstance(html, str) else json.dumps(html))
check('website_requests_explicit_archive_returns_customer_archived', code == 200 and 'Customer Archived Duplicate Fixture' in html and 'Inbox Fixture Website' not in html and 'value="archived" selected' in html)
code, _, _ = call(staff, '/api/customer/messages/' + thread, {'body': 'Our reply is saved here.', 'client_message_id': 'http-staff-reply-0001'})
check('staff_reply_without_csrf_denied', code == 403)
csrf = call(staff, '/session/token')[1]
reply = {'body': 'Our reply is saved here. Your proof review is being prepared.', 'client_message_id': 'http-staff-reply-0001'}
code, sent, _ = call(staff, '/api/customer/messages/' + thread, reply, csrf)
check('staff_reply_saved_and_email_only_queued', code == 200 and sent['delivery_status'] == 'queued')
code, duplicate, _ = call(staff, '/api/customer/messages/' + thread, reply, csrf)
check('staff_reply_retry_idempotent', code == 200 and duplicate['duplicate'] is True and duplicate['message_id'] == sent['message_id'])
customer, _ = login('customer')
code, detail, _ = call(customer, '/api/customer/messages/' + thread)
check('verified_customer_reads_original_and_staff_reply', code == 200 and len(detail['messages']) == 2 and detail['thread']['unread_count'] == 1 and detail['messages'][1]['delivery_status'] == 'queued')
customer_csrf = call(customer, '/session/token')[1]
check('read_without_csrf_denied', call(customer, '/api/customer/messages/' + thread + '/read', {'last_message_id': sent['message_id']})[0] == 403)
check('customer_read_acknowledgment', call(customer, '/api/customer/messages/' + thread + '/read', {'last_message_id': sent['message_id']}, customer_csrf)[0] == 200)
check('read_clears_unread_without_clearing_needs_reply', call(customer, '/api/customer/messages')[1]['unread_count'] == 0 and call(customer, '/api/customer/messages')[1]['needs_reply_count'] == 1)
check('customer_reply', call(customer, '/api/customer/messages/' + thread, {'body': 'Thank you; the timing matters.', 'client_message_id': 'http-customer-reply-0001'}, customer_csrf)[0] == 200)
foreign, _ = login('foreign')
foreign_csrf = call(foreign, '/session/token')[1]
check('foreign_account_detail_denied', call(foreign, '/api/customer/messages/' + thread)[0] == 404)
check('foreign_account_reply_denied', call(foreign, '/api/customer/messages/' + thread, {'body': 'Unauthorized reply', 'client_message_id': 'http-foreign-reply-0001'}, foreign_csrf)[0] == 404)
check('foreign_account_read_denied', call(foreign, '/api/customer/messages/' + thread + '/read', {'last_message_id': sent['message_id']}, foreign_csrf)[0] == 404)
check('foreign_account_admin_denied', call(foreign, '/admin/famtastic/messages')[0] == 403)
code, final, _ = call(staff, '/api/customer/messages/' + thread)
check('staff_sees_customer_reply_and_needs_reply', code == 200 and len(final['messages']) == 3 and final['thread']['needs_reply'] is True and final['thread']['unread_count'] == 1)
(evidence / 'final-conversation.json').write_text(json.dumps(final, indent=2))
print('PASS:', len(results), 'real HTTP assertions')
