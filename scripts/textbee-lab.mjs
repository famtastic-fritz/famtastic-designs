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

function normalizedPhone(value) {
  const compact = (value ?? '').trim().replace(/[\s().-]/g, '');
  const candidate = /^\d{10}$/.test(compact) ? `+1${compact}`
    : /^1\d{10}$/.test(compact) ? `+${compact}` : compact;
  return /^\+[1-9]\d{1,14}$/.test(candidate) ? candidate : null;
}

async function confirm(prompt, expected) {
  const input = createInterface({ input: process.stdin, output: process.stdout });
  try { return (await input.question(prompt)).trim() === expected; }
  finally { input.close(); }
}

async function main() {
  if (!['--setup', '--set-phone', '--check', '--send', '--forget'].includes(mode) || process.argv.length > 3) {
    throw new Error('usage: node scripts/textbee-lab.mjs [--setup|--set-phone|--check|--send|--forget]');
  }

  if (mode === '--setup' || mode === '--set-phone') {
    requireMacTerminal();
    process.stdout.write('Fritz-only fictional Textbee lab. No customer numbers or live reminders.\n');
    if (mode === '--setup') {
      process.stdout.write('Paste the Textbee API key at the hidden macOS Keychain prompt, then press Return.\n');
      storeSecret(KEY_SERVICE, 'FAMtastic Textbee fictional lab API key');
    }
    process.stdout.write('Enter your own US test phone as 10 digits or +1 followed by 10 digits at the hidden prompt.\n');
    storeSecret(TO_SERVICE, 'FAMtastic Textbee fictional lab test phone');
    const storedPhone = readSecret(TO_SERVICE);
    if (storedPhone === null) {
      process.stdout.write('Keychain accepted the phone, but this process cannot read it to validate. Nothing sent. Run --check from standard Mac Terminal.\n');
      process.exitCode = 1;
      return;
    }
    if (!normalizedPhone(storedPhone)) {
      removeSecret(TO_SERVICE);
      throw new Error('test_phone_invalid; use 10 US digits or +1 followed by 10 digits; test phone not saved');
    }
    process.stdout.write('Test phone saved in macOS Keychain. No message sent. Run --check.\n');
    return;
  }

  if (mode === '--forget') {
    requireMacTerminal();
    if (!await confirm('Remove this lab key and test phone from macOS Keychain? Type FORGET: ', 'FORGET')) {
      process.stdout.write('Cancelled; nothing removed.\n');
      return;
    }
    const keyRemoved = removeSecret(KEY_SERVICE);
    const recipientRemoved = removeSecret(TO_SERVICE);
    if (!keyRemoved || !recipientRemoved) throw new Error('keychain_removal_incomplete; inspect Keychain Access');
    process.stdout.write('Local lab Keychain items removed.\n');
    return;
  }

  if (mode === '--send' && keychainMode) requireMacTerminal();
  const apiKey = keychainMode && process.platform === 'darwin' ? readSecret(KEY_SERVICE) : process.env.TEXTBEE_API_KEY;
  const to = normalizedPhone(keychainMode && process.platform === 'darwin' ? readSecret(TO_SERVICE) : process.env.TEXTBEE_LAB_TO);
  if (mode === '--check') {
    process.stdout.write(JSON.stringify({
      mode: 'fritz_only_fictional_lab',
      credential_source: keychainMode ? 'macos_keychain' : 'environment',
      key_present: Boolean(apiKey),
      test_recipient_valid: Boolean(to),
      device_selected: Boolean(process.env.TEXTBEE_DEVICE_ID),
      ready: Boolean(apiKey) && Boolean(to) && (keychainMode || process.env.TEXTBEE_LAB_ENABLED === '1'),
      sent: false,
    }) + '\n');
    return;
  }

  if (keychainMode) {
    if (!apiKey || !to) throw new Error('lab_not_configured; run --setup or --set-phone');
    process.stdout.write(`One real SMS to your saved test phone ${to}:\n${MESSAGE}\n`);
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
