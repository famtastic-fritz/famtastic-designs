import { chromium } from 'playwright';

const email = 'famtastic@famtasticdesigns.com';
const passwords = ['Kajahk1', 'oh@UQC{x4]D@daP'];

async function tryLogin(password) {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    viewport: { width: 1280, height: 800 },
  });
  const page = await context.newPage();
  try {
    console.log(`\n--- Trying password: ${password} ---`);
    await page.goto('https://sso.godaddy.com/?realm=idp&path=%2Fproducts', { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForSelector('input[name="username"], input[type="email"], input#username, input#email', { timeout: 10000 });
    const userInput = await page.$('input[name="username"], input[type="email"], input#username, input#email');
    await userInput.fill(email);
    // Look for password field
    await page.waitForSelector('input[type="password"]', { timeout: 10000 });
    await page.fill('input[type="password"]', password);
    // Click submit
    const submit = await page.$('button[type="submit"], button:has-text("Sign In"), button#submitBtn');
    await submit.click();
    // Wait for navigation or 2FA
    await page.waitForTimeout(8000);
    const url = page.url();
    const title = await page.title();
    console.log('URL after submit:', url);
    console.log('Title:', title);
    // Check for 2FA prompt
    const body = await page.content();
    if (body.includes('verification') || body.includes('two-step') || body.includes('2-Step') || body.includes('security code')) {
      console.log('RESULT: 2FA / verification required');
    } else if (url.includes('dashboard') || url.includes('products') || url.includes('account')) {
      console.log('RESULT: Login appears successful');
    } else if (body.includes('incorrect') || body.includes('Invalid') || body.includes('password')) {
      console.log('RESULT: Invalid password');
    } else {
      console.log('RESULT: Unknown outcome; see screenshot');
    }
    await page.screenshot({ path: `/tmp/godaddy-${password.replace(/[^a-z0-9]/gi, '_')}.png`, fullPage: true });
  } catch (e) {
    console.error('Error:', e.message);
    await page.screenshot({ path: `/tmp/godaddy-err-${password.replace(/[^a-z0-9]/gi, '_')}.png`, fullPage: true });
  } finally {
    await browser.close();
  }
}

(async () => {
  for (const pw of passwords) {
    await tryLogin(pw);
  }
})();
