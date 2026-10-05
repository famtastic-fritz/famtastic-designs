#!/usr/bin/env node
// Fritz-only fictional SMS transport proof. No customer lookup or automatic send.
import { spawnSync } from 'node:child_process';
import { createInterface } from 'node:readline/promises';
import { createTextbeeLabAdapter } from '../tools/customer-messaging/consent-sms-loop/src/index.mjs';

const ACCOUNT = 'fritz-fictional-lab';
const KEY_SERVICE = 'com.famtasticdesigns.textbee.lab.api-key';
const TO_SERVICE = 'com.famtasticdesigns.textbee.lab.test-recipient';
const SECURITY = '/usr/bin/security';
const MESSAGE = 'FAMtastic fictional SMS lab test. Reply YES for the test only.';
const mode = process.argv[2] ?? '--check';
const keychainMode = !process.env.TEXTBEE_API_KEY && !process.env.TEXTBEE_LAB_TO;

function keychain(command, service, extra = [], stdio = ['ignore', 'pipe', 'ignore']) {
  return spawnSync(SECURITY, [command, '-a', ACCOUNT, '-s', service, ...extra], {
    encoding: 'utf8', stdio,
  });
}

function readSecret(service) {
  const result = keychain('find-generic-password', service, ['-w']);
  return result.status === 0 ? result.stdout.replace(/\r?\n$/, '') : null;
}

function storeSecret(service, label) {
  // -w is last: macOS security prompts without putting the value in argv/history.
  const result = keychain('add-generic-password', service, ['-l', label, '-U', '-w'], 'inherit');
  if (result.status !== 0) throw new Error('keychain_write_failed');
}

function removeSecret(service) {
  const result = keychain('delete-generic-password', service);
  return result.status === 0;
}

function requireMacTerminal() {
  if (process.platform !== 'darwin') throw new Error('mac_keychain_required');
  if (!process.stdin.isTTY || !process.stdout.isTTY) throw new Error('interactive_terminal_required');
}

function e164(value) {
  return /^\+[1-9]\d{1,14}$/.test(value ?? '');
}

async function confirm(prompt, expected) {
  const input = createInterface({ input: process.stdin, output: process.stdout });
  try { return (await input.question(prompt)).trim() === expected; }
  finally { input.close(); }
}

async function main() {
  if (!['--setup', '--check', '--send', '--forget'].includes(mode) || process.argv.length > 3) {
    throw new Error('usage: node scripts/textbee-lab.mjs [--setup|--check|--send|--forget]');
  }

  if (mode === '--setup') {
    requireMacTerminal();
    process.stdout.write('Fritz-only fictional Textbee lab. No customer numbers or live reminders.\n');
    process.stdout.write('Paste the Textbee API key at the hidden macOS Keychain prompt, then press Return.\n');
    storeSecret(KEY_SERVICE, 'FAMtastic Textbee fictional lab API key');
    process.stdout.write('Enter your own test phone in E.164 form (+1...), at the next hidden prompt.\n');
    storeSecret(TO_SERVICE, 'FAMtastic Textbee fictional lab test phone');
    if (!readSecret(KEY_SERVICE)?.trim() || !e164(readSecret(TO_SERVICE))) {
      removeSecret(TO_SERVICE);
      throw new Error('keychain_value_invalid; no test recipient saved');
    }
    process.stdout.write('Key and test phone saved in macOS Keychain. No message sent. Run --check.\n');
    return;
  }

  if (mode === '--forget') {
    requireMacTerminal();
    if (!await confirm('Remove this lab key and test phone from macOS Keychain? Type FORGET: ', 'FORGET')) {
      process.stdout.write('Cancelled; nothing removed.\n');
      return;
    }
    removeSecret(KEY_SERVICE);
    removeSecret(TO_SERVICE);
    process.stdout.write('Local lab Keychain items removed.\n');
    return;
  }

  const apiKey = keychainMode && process.platform === 'darwin' ? readSecret(KEY_SERVICE) : process.env.TEXTBEE_API_KEY;
  const to = keychainMode && process.platform === 'darwin' ? readSecret(TO_SERVICE) : process.env.TEXTBEE_LAB_TO;
  if (mode === '--check') {
    process.stdout.write(JSON.stringify({
      mode: 'fritz_only_fictional_lab',
      credential_source: keychainMode ? 'macos_keychain' : 'environment',
      key_present: Boolean(apiKey),
      test_recipient_valid: e164(to),
      device_selected: Boolean(process.env.TEXTBEE_DEVICE_ID),
      ready: Boolean(apiKey) && e164(to) && (keychainMode || process.env.TEXTBEE_LAB_ENABLED === '1'),
      sent: false,
    }) + '\n');
    return;
  }

  if (keychainMode) {
    requireMacTerminal();
    if (!apiKey || !e164(to)) throw new Error('lab_not_configured; run --setup');
    process.stdout.write(`One real SMS to your saved test phone ending ${to.slice(-4)}:\n${MESSAGE}\n`);
    if (!await confirm('Type SEND to make one attempt: ', 'SEND')) {
      process.stdout.write('Cancelled; nothing sent.\n');
      return;
    }
  } else if (process.env.TEXTBEE_LAB_ACK !== 'FRITZ_ONLY_FICTIONAL_TEST') {
    throw new Error('lab_ack_required; nothing sent');
  }

  try {
    const adapter = createTextbeeLabAdapter({
      enabled: keychainMode || process.env.TEXTBEE_LAB_ENABLED === '1',
      apiKey,
      allowedRecipients: [to],
      deviceId: process.env.TEXTBEE_DEVICE_ID || undefined,
    });
    const result = await adapter.send({ to, message: MESSAGE });
    process.stdout.write(JSON.stringify({ ...result, real_sms: true, recipient_redacted: true }) + '\n');
  } catch (error) {
    process.stderr.write(`${error.code ?? 'lab_send_failed'}; outcome may be uncertain; do not retry blindly\n`);
    process.exitCode = 1;
  }
}

main().catch(error => {
  process.stderr.write(`${error.message}; nothing sent\n`);
  process.exitCode = 2;
});
