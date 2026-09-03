#!/usr/bin/env node
/**
 * OpenArt MCP & API Client with automatic token refresh
 */

import https from 'https';

const CLIENT_ID = "4GXDAxR4ew54HIMszUvB";
const REFRESH_TOKEN = "oa_ort_live_jEE6uKeftxluKngD_4W87RXZuRQhhD-eIxgDqK8NrwE";

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
