#!/usr/bin/env node
// Fritz-only fictional SMS transport proof. No customer lookup or automatic send.
import { createTextbeeLabAdapter } from '../tools/customer-messaging/consent-sms-loop/src/index.mjs';

const mode = process.argv[2] ?? '--check';
if (!['--check', '--send'].includes(mode) || process.argv.length > 3) {
  process.stderr.write('Usage: node scripts/textbee-lab.mjs [--check|--send]\n');
  process.exitCode = 2;
} else if (mode === '--check') {
  process.stdout.write(JSON.stringify({
    mode: 'fritz_only_fictional_lab',
    enabled: process.env.TEXTBEE_LAB_ENABLED === '1',
    key_present: Boolean(process.env.TEXTBEE_API_KEY),
    test_recipient_present: Boolean(process.env.TEXTBEE_LAB_TO),
    device_selected: Boolean(process.env.TEXTBEE_DEVICE_ID),
    sent: false,
  }) + '\n');
} else {
  if (process.env.TEXTBEE_LAB_ACK !== 'FRITZ_ONLY_FICTIONAL_TEST') {
    process.stderr.write('lab_ack_required; nothing sent\n');
    process.exitCode = 2;
  } else {
    try {
      const adapter = createTextbeeLabAdapter({
        enabled: process.env.TEXTBEE_LAB_ENABLED === '1',
        apiKey: process.env.TEXTBEE_API_KEY,
        allowedRecipients: [process.env.TEXTBEE_LAB_TO],
        deviceId: process.env.TEXTBEE_DEVICE_ID || undefined,
      });
      const result = await adapter.send({
        to: process.env.TEXTBEE_LAB_TO,
        message: 'FAMtastic fictional SMS lab test. Reply YES for the test only.',
      });
      process.stdout.write(JSON.stringify({ ...result, real_sms: true, recipient_redacted: true }) + '\n');
    } catch (error) {
      process.stderr.write(`${error.code ?? 'lab_send_failed'}; outcome may be uncertain; do not retry blindly\n`);
      process.exitCode = 1;
    }
  }
}
