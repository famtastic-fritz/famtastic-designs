#!/usr/bin/env node
// Read-only capability check. Never exports keys, creates objects, or enables checkout.
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

export class PreflightRefusal extends Error {
  constructor(code) { super(code); this.name = 'PreflightRefusal'; this.code = code; }
}
const refuse = code => { throw new PreflightRefusal(code); };
const PROFILE = /^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/;
const ACCOUNT = /^acct_[A-Za-z0-9]{8,64}$/;

// Only the installed CLI's own configured profile may resolve its credentials.
// No ambient API key, proxy, alternate config, connected-account or Node hook.
export function cliEnvironment(env) {
  if (typeof env.HOME !== 'string' || !env.HOME.startsWith('/') || env.HOME.includes('\0')
    || typeof env.PATH !== 'string' || !env.PATH || env.PATH.includes('\0')) refuse('invalid_cli_environment');
  return { HOME: env.HOME, PATH: env.PATH, LANG: 'C', NO_COLOR: '1' };
}

export function parseCliResult(result) {
  // Do not propagate raw CLI output/errors; they may contain credential material.
  if (result.error || result.status !== 0) refuse('stripe_read_failed');
  try { return JSON.parse(result.stdout); }
  catch { refuse('stripe_read_invalid_json'); }
}

function executeReadOnly(args, env) {
  return parseCliResult(spawnSync('stripe', args, {
    env, encoding: 'utf8', timeout: 20000, maxBuffer: 1024 * 1024,
    stdio: ['ignore', 'pipe', 'pipe'], windowsHide: true,
  }));
}

function validateIdentity(identity, profile, account, now) {
  if (!identity || identity.authenticated !== true) refuse('stripe_not_authenticated');
  if (identity.profile_name !== profile) refuse('stripe_profile_mismatch');
  if (identity.account_id !== account) refuse('stripe_account_mismatch');
  if (identity.test_mode_key?.available !== true) refuse('stripe_test_credential_unavailable');
  const expiry = identity.test_mode_key.expires_at;
  // Known legacy-profile metadata uses a UTC date. Fail closed on absent/unknown
  // formats and on the expiry day rather than guessing a token's usable lifetime.
  if (typeof expiry !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(expiry)
    || !Number.isFinite(Date.parse(`${expiry}T00:00:00Z`))
    || new Date(`${expiry}T00:00:00Z`).toISOString().slice(0, 10) !== expiry
    || Date.parse(`${expiry}T00:00:00Z`) <= now) refuse('stripe_test_credential_expired_or_unknown');
  return expiry;
}

export function inspectTestProfile({ env = process.env, read = executeReadOnly, now = Date.now() } = {}) {
  const profile = env.FAMTASTIC_STRIPE_TEST_PROFILE;
  const account = env.FAMTASTIC_STRIPE_EXPECTED_ACCOUNT;
  if (typeof profile !== 'string' || !PROFILE.test(profile)) refuse('explicit_test_profile_required');
  if (typeof account !== 'string' || !ACCOUNT.test(account)) refuse('explicit_expected_account_required');
  if (!Number.isFinite(now)) refuse('invalid_clock');
  const child = cliEnvironment(env);
  const who = ['whoami', `--project-name=${profile}`, '--format', 'json'];
  const first = read(who, child);
  const expiry = validateIdentity(first, profile, account, now);
  const balance = read(['balance', 'retrieve', `--project-name=${profile}`, '--color=off'], child);
  if (balance?.object !== 'balance' || balance.livemode !== false) refuse('stripe_test_mode_not_proven');
  const last = read(who, child);
  if (validateIdentity(last, profile, account, now) !== expiry) refuse('stripe_profile_changed_during_read');
  return {
    schema: 'famtastic.stripe-read-preflight.v1',
    status: 'read_only_preflight_passed',
    profile, account_id: account, observed_at: new Date(now).toISOString(),
    test_mode_read_verified: true, test_credential_expires_on: expiry,
    live_credential_available: last.live_mode_key?.available === true,
    secret_values_read_or_exported: false,
    provider_mutations: 0, checkout_activated: false, e2e_proven: false,
    limits: [
      'Three read-only CLI invocations, not key isolation or future mutation authority.',
      'No runtime, gateway, signed callback, native payment or fulfillment execution.',
      'Profile may also contain live credentials; never copy its whole configuration.',
      'Recheck exact account/test mode at execution time; this receipt is not an authorization token.',
    ],
  };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    if (process.argv.length !== 2) refuse('unexpected_arguments');
    console.log(JSON.stringify(inspectTestProfile(), null, 2));
  }
  catch (error) {
    console.log(JSON.stringify({ schema: 'famtastic.stripe-read-preflight.v1', status: 'refused',
      reason: error instanceof PreflightRefusal ? error.code : 'unexpected_preflight_failure',
      provider_mutations: 0, checkout_activated: false, e2e_proven: false }));
    process.exitCode = 2;
  }
}
