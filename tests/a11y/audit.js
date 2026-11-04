// axe-core WCAG 2.1 A/AA audit of NSP pages through headless Chrome.
// Usage: node audit.js <baseUrl> <adminEmail> <adminPassword>
const puppeteer = require('puppeteer-core');
const axeSource = require('axe-core').source;

const [base, email, password] = process.argv.slice(2);
const publicPages = ['/', '/about', '/news', '/news/smoke-post', '/faqs', '/contact', '/login', '/forgot-password'];
const adminPages = ['/workspace', '/account/security', '/admin', '/admin/pages', '/admin/pages/create', '/admin/posts/create',
  '/admin/media', '/admin/menus', '/admin/content/faqs', '/admin/content/testimonials/create', '/admin/redirects',
  '/admin/messages', '/admin/settings', '/admin/users', '/admin/users/create', '/admin/features', '/admin/audit'];

async function audit(page, path, width) {
  await page.setViewport({ width, height: 900 });
  const response = await page.goto(base + path, { waitUntil: 'networkidle0' });
  await page.addScriptTag({ content: axeSource });
  const result = await page.evaluate(async () => axe.run(document, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
  }));
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  return { path, width, status: response.status(), overflow, violations: result.violations.map(v => ({
    id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 3).map(n => n.target.join(' ')),
  })) };
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const page = await browser.newPage();
  // NSP's CSP (script-src 'self') blocks injected scripts; the audit tool needs to inject axe.
  await page.setBypassCSP(true);
  const results = [];
  for (const path of publicPages) for (const width of [1280, 360]) results.push(await audit(page, path, width));

  await page.goto(base + '/login', { waitUntil: 'networkidle0' });
  await page.type('#field-email', email);
  await page.type('#field-password', password);
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
  for (const path of adminPages) for (const width of [1280, 360]) results.push(await audit(page, path, width));

  // Keyboard: the first Tab must reach the skip link.
  await page.goto(base + '/', { waitUntil: 'networkidle0' });
  await page.keyboard.press('Tab');
  const firstFocus = await page.evaluate(() => document.activeElement.textContent.trim());

  await browser.close();
  let total = 0;
  for (const r of results) {
    total += r.violations.length;
    const flags = [r.status !== 200 ? `HTTP ${r.status}` : '', r.overflow ? 'HORIZONTAL OVERFLOW' : ''].filter(Boolean).join(' ');
    if (r.violations.length || flags) {
      console.log(`${r.path} @${r.width}px ${flags}`);
      for (const v of r.violations) console.log(`   [${v.impact}] ${v.id}: ${v.help} -> ${v.nodes.join(' | ')}`);
    }
  }
  console.log(`\nPages x widths audited: ${results.length}. Violations: ${total}. First Tab focus: "${firstFocus}"`);
})().catch(e => { console.error(e); process.exit(1); });
