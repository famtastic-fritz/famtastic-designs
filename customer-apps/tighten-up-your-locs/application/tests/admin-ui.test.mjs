import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { TIMEZONE, dayKey, localTimeValue, zonedTimestamp, timeInterval, monthDates, isOnDay, errorMessage, actionNeedsTime } from '../public/admin-assets/admin.js';

test('business timezone is explicit and independent of device timezone', () => {
    assert.equal(TIMEZONE, 'America/New_York');
    assert.equal(zonedTimestamp('2026-09-14T09:00'), Date.parse('2026-09-14T13:00:00Z') / 1000);
    assert.equal(zonedTimestamp('2026-01-14T09:00'), Date.parse('2026-01-14T14:00:00Z') / 1000);
    assert.equal(localTimeValue(Date.parse('2026-09-14T13:00:00Z') / 1000), '2026-09-14T09:00');
    assert.equal(dayKey(Date.parse('2026-09-15T02:00:00Z') / 1000), '2026-09-14');
});
test('spring-forward missing time and fall-back ambiguity are rejected', () => {
    assert.throws(() => zonedTimestamp('2026-03-08T02:30'), /does not exist/);
    assert.throws(() => zonedTimestamp('2026-11-01T01:30'), /happens twice/);
    assert.equal(zonedTimestamp('2026-03-08T03:30'), Date.parse('2026-03-08T07:30:00Z') / 1000);
    assert.equal(zonedTimestamp('2026-11-01T02:30'), Date.parse('2026-11-01T07:30:00Z') / 1000);
});
test('invalid dates and reversed intervals fail before a request', () => {
    for (const value of ['', '2026-02-30T09:00', '2026-13-01T09:00', '2026-01-01T24:00', '1999-12-31T23:00', '2026-01-01T09:90']) assert.throws(() => zonedTimestamp(value));
    assert.throws(() => timeInterval('2026-09-14T10:00', '2026-09-14T09:00'), /end time/);
    assert.throws(() => timeInterval('2026-09-14T10:00', '2026-09-14T10:00'), /end time/);
});
test('interval duration accounts for DST, not just wall-clock difference', () => {
    const time = timeInterval('2026-03-08T01:00', '2026-03-08T04:00');
    assert.equal(time.ends_at - time.starts_at, 2 * 3600);
});
test('calendar month covers real leap/non-leap month dates', () => {
    assert.equal(monthDates('2026-02').length, 28);
    assert.equal(monthDates('2028-02').length, 29);
    assert.equal(monthDates('2026-09').at(-1), '2026-09-30');
    assert.equal(monthDates('2026-12').at(-1), '2026-12-31');
});
test('calendar includes overnight appointments, but not exclusive end midnight', () => {
    const overnight = timeInterval('2026-09-14T23:00', '2026-09-15T01:00');
    assert.equal(isOnDay(overnight, '2026-09-14'), true);
    assert.equal(isOnDay(overnight, '2026-09-15'), true);
    assert.equal(isOnDay(overnight, '2026-09-16'), false);
    assert.equal(isOnDay(timeInterval('2026-09-14T23:00', '2026-09-15T00:00'), '2026-09-15'), false);
});
test('a reschedule remains visible on both original and proposed days', () => {
    const appointment = { ...timeInterval('2026-09-14T09:00', '2026-09-14T11:00'), pending_starts_at: zonedTimestamp('2026-09-16T10:00'), pending_ends_at: zonedTimestamp('2026-09-16T12:00') };
    assert.equal(isOnDay(appointment, '2026-09-14'), true);
    assert.equal(isOnDay(appointment, '2026-09-16'), true);
    assert.equal(isOnDay(appointment, '2026-09-15'), false);
});
test('DST transition day boundaries are correct for appointment membership', () => {
    const appointment = timeInterval('2026-03-08T00:00', '2026-03-09T00:00');
    assert.equal(appointment.ends_at - appointment.starts_at, 23 * 3600);
    assert.equal(isOnDay(appointment, '2026-03-08'), true);
    assert.equal(isOnDay(appointment, '2026-03-09'), false);
});
test('errors distinguish stale state, authentication, and uncertain persistence', () => {
    assert.match(errorMessage({ status: 409 }), /refresh/);
    assert.match(errorMessage({ status: 409, code: 'appointment_slot_conflict' }), /overlaps/);
    assert.match(errorMessage({ status: 419 }), /sign-in has expired/);
    assert.match(errorMessage({}), /Do not assume the change failed/);
    assert.equal(errorMessage({ status: 422, validation: { starts_at: ['Choose a future time.'] } }), 'Choose a future time.');
});
test('confirming an existing proposal preserves its offered times', () => {
    assert.equal(actionNeedsTime('confirm', 'appointment'), false);
    assert.equal(actionNeedsTime('confirm', 'request'), true);
    assert.equal(actionNeedsTime('reschedule', 'appointment'), true);
    assert.equal(actionNeedsTime('cancel', 'appointment'), false);
    assert.equal(actionNeedsTime('complete', 'appointment'), false);
    assert.equal(actionNeedsTime('create', 'opening'), true);
    assert.equal(actionNeedsTime('update', 'opening'), true);
    assert.equal(actionNeedsTime('delete', 'opening'), false);
});
test('private admin has only same-origin runtime resources and no agency endpoint', () => {
    const source = fs.readFileSync(new URL('../public/admin-assets/admin.js', import.meta.url), 'utf8');
    const view = fs.readFileSync(new URL('../resources/views/admin.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(source + view, /famtasticdesigns\.com|googletagmanager|google-analytics|localStorage|sessionStorage|indexedDB/);
    assert.match(view, /name="csrf-token" content="\{\{ csrf_token\(\) \}\}"/);
    assert.match(view, /method="POST" action="\/admin\/logout">@csrf/);
    assert.match(source, /credentials: 'same-origin'/);
    assert.match(source, /'X-CSRF-TOKEN': csrf/);
    assert.match(source, /state\.keys\.get\(signature\)/);
    assert.match(source, /result\.ok !== true/);
    assert.doesNotMatch(source, /innerHTML|insertAdjacentHTML/);
});
