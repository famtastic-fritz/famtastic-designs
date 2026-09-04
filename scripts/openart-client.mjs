#!/usr/bin/env node
/**
 * OpenArt MCP & API Client with automatic token refresh.
 *
 * Credentials never live in this file. A prior version hardcoded a live
 * client_id and refresh_token here, committed to git in ac212d4 and pushed to
 * the shared repo -- an incident, not a style issue. That token must be
 * rotated at OpenArt regardless of this fix; removing it from the file does
 * not remove it from git history.
 *
 * Set OPENART_CLIENT_ID and OPENART_REFRESH_TOKEN in the untracked local
 * environment (see marketing/.env.example), never in source.
 */

import https from 'https';

const CLIENT_ID = process.env.OPENART_CLIENT_ID;
const REFRESH_TOKEN = process.env.OPENART_REFRESH_TOKEN;

if (!CLIENT_ID || !REFRESH_TOKEN) {
  console.error('FAIL: set OPENART_CLIENT_ID and OPENART_REFRESH_TOKEN in the environment.');
  console.error('Never hardcode these values in source -- see marketing/.env.example.');
  process.exit(1);
}

async function postJson(urlStr, data, headers = {}) {
  const url = new URL(urlStr);
  const payload = JSON.stringify(data);
  return new Promise((resolve, reject) => {
    const req = https.request({
      hostname: url.hostname,
      port: 443,
      path: url.pathname + url.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload),
        ...headers
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, data: JSON.parse(body) });
        } catch {
          resolve({ status: res.statusCode, raw: body });
        }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

async function refreshToken() {
  console.log("Attempting OpenArt OAuth token refresh...");
  const endpoints = [
    "https://mcp.openart.ai/oauth/token",
    "https://openart.ai/api/oauth/token",
    "https://api.openart.ai/oauth/token"
  ];

  for (const ep of endpoints) {
    try {
      console.log(`Trying endpoint: ${ep}`);
      const res = await postJson(ep, {
        grant_type: "refresh_token",
        client_id: CLIENT_ID,
        refresh_token: REFRESH_TOKEN
      });
      console.log(`Status: ${res.status}`, res.data || res.raw);
      if (res.status === 200 && res.data?.access_token) {
        return res.data.access_token;
      }
    } catch (e) {
      console.log(`Failed on ${ep}:`, e.message);
    }
  }
}

async function main() {
  const token = await refreshToken();
  if (token) {
    console.log("✔ Token refreshed successfully:", token);
  } else {
    console.log("⚠ Token refresh required via interactive browser login.");
  }
}

main();
