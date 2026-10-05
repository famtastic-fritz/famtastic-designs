#!/usr/bin/env node
// Fritz-only fictional SMS transport proof. No customer lookup or automatic send.
import { spawnSync } from 'node:child_process';
import { createInterface } from 'node:readline/promises';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { createTextbeeLabAdapter } from '../tools/customer-messaging/consent-sms-loop/src/index.mjs';
import { createPrivatePhoneStore, normalizeTestPhone } from './textbee-lab-phone.mjs';

const ACCOUNT = 'fritz-fictional-lab';
const KEY_SERVICE = 'com.famtasticdesigns.textbee.lab.api-key';
const TO_SERVICE = 'com.famtasticdesigns.textbee.lab.test-recipient';
const SECURITY = '/usr/bin/security';
const MESSAGE = 'FAMtastic fictional SMS lab test. Reply YES for the test only.';
const phoneStore = createPrivatePhoneStore(join(homedir(), 'Library', 'Application Support', 'FAMtastic', 'TextbeeLab'));
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

async function confirm(prompt, expected) {
  const input = createInterface({ input: process.stdin, output: process.stdout });
  try { return (await input.question(prompt)).trim() === expected; }
  finally { input.close(); }
}

async function configurePhone() {
  const input = createInterface({ input: process.stdin, output: process.stdout });
  try {
    for (let attempt = 0; attempt < 3; attempt++) {
      const raw = await input.question('Your own test phone (10 US digits or +1..., visible only here): ');
      const phone = normalizeTestPhone(raw);
      if (!phone) {
        process.stdout.write('That is not a valid phone number. Try ten digits without an extension.\n');
        continue;
      }
      if ((await input.question(`Save ${phone} as the only test recipient? Type SAVE: `)).trim() !== 'SAVE') {
        process.stdout.write('Not saved. Try again or press Control-C to cancel.\n');
        continue;
      }
      phoneStore.save(phone);
      process.stdout.write('Private test recipient saved. No message sent. Run --check.\n');
      return;
    }
    throw new Error('test_phone_not_saved');
  } finally {
    input.close();
  }
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
    await configurePhone();
    return;
  }

  if (mode === '--forget') {
    requireMacTerminal();
    if (!await confirm('Remove this lab key and private test phone? Type FORGET: ', 'FORGET')) {
      process.stdout.write('Cancelled; nothing removed.\n');
      return;
    }
    const keyRemoved = removeSecret(KEY_SERVICE);
    const recipientRemoved = phoneStore.remove();
    removeSecret(TO_SERVICE); // best-effort cleanup for earlier Keychain phone setup
    if (!keyRemoved || !recipientRemoved) throw new Error('lab_removal_incomplete; inspect Keychain and private test phone');
    process.stdout.write('Local lab key and test recipient removed.\n');
    return;
  }

  if (mode === '--send' && keychainMode) requireMacTerminal();
  const apiKey = keychainMode && process.platform === 'darwin' ? readSecret(KEY_SERVICE) : process.env.TEXTBEE_API_KEY;
  const to = keychainMode ? phoneStore.read() : normalizeTestPhone(process.env.TEXTBEE_LAB_TO);
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
