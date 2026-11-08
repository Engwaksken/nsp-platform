// axe-core WCAG 2.1 A/AA audit of NSP pages through headless Chrome (A11Y-706).
//
// Full-site sweep (local machine):
//   php artisan nsp:audit-fixture --password=<pw>     # sample data in every module + tests/a11y/pages.json
//   NSP_MFA_ENFORCE=false php artisan serve
//   node audit.js http://127.0.0.1:8000 admin@a11y.nsp.test <pw>
//   php artisan nsp:audit-fixture --clean
//
// Without pages.json it audits the core public and admin pages only.
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');
const axeSource = require('axe-core').source;

const [base, email, password] = process.argv.slice(2);
const listFile = path.join(__dirname, 'pages.json');
const list = fs.existsSync(listFile) ? JSON.parse(fs.readFileSync(listFile, 'utf8')) : {
  public: ['/', '/news', '/faqs', '/contact', '/login', '/forgot-password'],
  signedIn: ['/workspace', '/account/security', '/admin', '/admin/pages', '/admin/users', '/admin/settings', '/admin/features'],
};

async function audit(page, url, width) {
  await page.setViewport({ width, height: 900 });
  const response = await page.goto(base + url, { waitUntil: 'networkidle0' });
  await page.addScriptTag({ content: axeSource });
  const result = await page.evaluate(async () => axe.run(document, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
  }));
  const checks = await page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    h1: document.querySelectorAll('h1').length,
    lang: document.documentElement.getAttribute('lang') || '',
    title: document.title.trim(),
  }));
  const finalPath = new URL(page.url()).pathname + new URL(page.url()).search;
  return { url, width, status: response.status(), redirectedTo: finalPath !== url ? finalPath : null, ...checks, violations: result.violations.map(v => ({
    id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 3).map(n => n.target.join(' ')),
  })) };
}

// Keyboard: Tab through the first 15 stops; each must show a visible focus indicator.
async function keyboard(page, url) {
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(base + url, { waitUntil: 'networkidle0' });
  const stops = [];
  for (let i = 0; i < 15; i++) {
    await page.keyboard.press('Tab');
    stops.push(await page.evaluate(() => {
      const el = document.activeElement;
      if (!el || el === document.body) return null;
      const s = getComputedStyle(el);
      const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
      return { label: (el.innerText || el.getAttribute('aria-label') || el.name || el.tagName).trim().slice(0, 40), visible };
    }));
  }
  return stops.filter(Boolean);
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const page = await browser.newPage();
  // NSP's CSP (script-src 'self') blocks injected scripts; the audit tool needs to inject axe.
  await page.setBypassCSP(true);
  const results = [];
  for (const url of list.public) for (const width of [1280, 360]) results.push(await audit(page, url, width));
  const keys = { '/': await keyboard(page, '/') };
  results.push(await audit(page, '/this-page-does-not-exist', 1280));

  await page.goto(base + '/login', { waitUntil: 'networkidle0' });
  await page.type('#field-email', email);
  await page.type('#field-password', password);
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/login"] button[type=submit]')]);
  for (const url of list.signedIn) for (const width of [1280, 360]) results.push(await audit(page, url, width));
  keys['/admin'] = await keyboard(page, '/admin');
  await browser.close();

  let total = 0;
  let problems = 0;
  for (const r of results) {
    total += r.violations.length;
    const expected = r.url === '/this-page-does-not-exist' ? 404 : 200;
    const flags = [r.status !== expected ? `HTTP ${r.status}` : '', r.redirectedTo ? `redirected to ${r.redirectedTo}` : '', r.overflow ? 'HORIZONTAL OVERFLOW' : '',
      r.h1 !== 1 ? `${r.h1} h1 elements` : '', r.lang ? '' : 'no lang', r.title ? '' : 'no title'].filter(Boolean).join(', ');
    if (r.violations.length || flags) {
      problems++;
      console.log(`${r.url} @${r.width}px ${flags}`);
      for (const v of r.violations) console.log(`   [${v.impact}] ${v.id}: ${v.help} -> ${v.nodes.join(' | ')}`);
    }
  }
  for (const [url, stops] of Object.entries(keys)) {
    const hidden = stops.filter(s => !s.visible);
    console.log(`Keyboard ${url}: first stop "${stops[0]?.label}", ${stops.length} stops, ${hidden.length} without visible focus${hidden.length ? ': ' + hidden.map(s => s.label).join(', ') : ''}`);
  }
  console.log(`\nPages x widths audited: ${results.length}. Pages with problems: ${problems}. axe violations: ${total}.`);
  process.exit(problems ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
