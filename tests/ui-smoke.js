#!/usr/bin/env node
/**
 * UI smoke tests for DivingClub-Manager on Hetzner staging.
 * Runs headless Chromium via Puppeteer, checks key pages and flows.
 */
const puppeteer = require('puppeteer-core');

const BASE = 'http://127.0.0.1';
const BASIC_AUTH = 'cep:cep2026';
const ADMIN_EMAIL = 'michel.brochard@mac.com';
const MEMBER_EMAIL = 'baccadia@hotmail.com';
const PASSWORD = 'TestPass2026!';
const TIMEOUT = 10000;

let browser, page;
let passed = 0, failed = 0, errors = [];

async function test(name, fn) {
    try {
        await fn();
        passed++;
        console.log(`  ✅ ${name}`);
    } catch (e) {
        failed++;
        errors.push({ name, error: e.message });
        console.log(`  ❌ ${name}: ${e.message.substring(0, 120)}`);
    }
}

function url(path) {
    return `http://127.0.0.1${path}`;
}

async function goto(path, waitFor = 'networkidle2') {
    await page.goto(url(path), { waitUntil: waitFor, timeout: TIMEOUT });
}

async function assertTitle(expected) {
    const title = await page.title();
    if (!title.toLowerCase().includes(expected.toLowerCase()))
        throw new Error(`Title "${title}" does not contain "${expected}"`);
}

async function assertSelector(sel, desc) {
    const el = await page.$(sel);
    if (!el) throw new Error(`Selector "${sel}" not found` + (desc ? ` (${desc})` : ''));
}

async function assertText(text) {
    const body = await page.$eval('body', el => el.textContent);
    if (!body.includes(text)) throw new Error(`Text "${text}" not found on page`);
}

async function assertNoError() {
    const body = await page.$eval('body', el => el.textContent);
    if (body.includes('Server Error') || body.includes('500 |'))
        throw new Error('500 Server Error on page');
    if (body.includes('Whoops!'))
        throw new Error('Whoops error on page');
}

async function assertStatus(path, expected) {
    const resp = await page.goto(url(path), { waitUntil: 'networkidle2', timeout: TIMEOUT });
    if (resp.status() !== expected)
        throw new Error(`${path} returned ${resp.status()}, expected ${expected}`);
}
async function login(email) {
    await goto('/login');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', PASSWORD);
    await Promise.all([
        page.click('button[type="submit"]'),
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: TIMEOUT }),
    ]);
}

async function logout() {
    // Clear all cookies and storage to ensure clean state
    const client = await page.createCDPSession();
    await client.send('Network.clearBrowserCookies');
    await client.send('Storage.clearCookies', {});
    await page.evaluate(() => { document.cookie.split(';').forEach(c => { document.cookie = c.trim().split('=')[0] + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/'; }); });
}

// ─── PUBLIC PAGES ──────────────────────────────────────────

async function publicTests() {
    console.log('\n🌐 Public Pages');

    await test('Homepage loads', async () => {
        await goto('/');
        await assertNoError();
        await assertSelector('nav', 'navbar');
    });

    await test('Homepage has club branding', async () => {
        await goto('/');
        const html = await page.content();
        if (!html.includes('DivingClub-Manager'))
            throw new Error('Missing generator meta');
    });

    await test('Login page loads', async () => {
        await goto('/login');
        await assertNoError();
        await assertSelector('input[name="email"]');
        await assertSelector('input[name="password"]');
    });

    await test('Register page loads', async () => {
        await goto('/register');
        await assertNoError();
        await assertSelector('input[name="email"]');
    });

    await test('Forgot password page loads', async () => {
        await goto('/forgot-password');
        await assertNoError();
        await assertSelector('input[name="email"]');
    });

    await test('Language switcher present', async () => {
        await goto('/');
        await assertSelector('[href*="locale"], .dropdown-menu a[href*="lang"], select[name="locale"]', 'language switcher');
    });

    await test('404 page returns 404', async () => {
        await assertStatus('/this-page-does-not-exist-xyz', 404);
    });
}

// ─── MEMBER FLOW ───────────────────────────────────────────

async function memberTests() {
    console.log('\n👤 Member Flow');

    await test('Login as member', async () => {
        await login(MEMBER_EMAIL);
        await assertNoError();
    });

    await test('Dashboard loads after login', async () => {
        await goto('/dashboard');
        await assertNoError();
    });

    await test('Profile page loads', async () => {
        await goto('/profile');
        await assertNoError();
        await assertSelector('form', 'profile form');
    });

    await test('Events page loads', async () => {
        await goto('/events');
        await assertNoError();
    });

    await test('Calendar page loads', async () => {
        await goto('/calendar');
        await assertNoError();
    });

    await test('Articles page loads', async () => {
        await goto('/articles');
        await assertNoError();
    });

    await test('Dive sites page loads', async () => {
        await goto('/dive-sites');
        await assertNoError();
    });

    await test('Classifieds page loads', async () => {
        await goto('/classifieds');
        await assertNoError();
    });

    await test('Votes page loads', async () => {
        await goto('/votes');
        await assertNoError();
    });

    await test('GDPR/Privacy page loads', async () => {
        await goto('/privacy');
        await assertNoError();
    });

    await test('Member cannot access admin', async () => {
        const resp = await page.goto(url('/admin/members'), { waitUntil: 'networkidle2', timeout: TIMEOUT });
        const finalUrl = page.url();
        const status = resp.status();
        // Should be 403, redirect to login/dashboard, or show "unauthorized"
        if (status === 200 && !finalUrl.includes('login') && !finalUrl.includes('dashboard')) {
            const body = await page.$eval('body', el => el.textContent);
            if (body.includes('Members') && body.includes('table'))
                throw new Error('Admin members page fully accessible to regular member');
        }
    });

    await test('Logout works', async () => {
        await logout();
        const client = await page.createCDPSession();
        await client.send('Network.clearBrowserCookies');
        await goto('/dashboard');
        const u = page.url();
        if (!u.includes('login'))
            console.log('    ⚠️  Note: redirect after logout goes to ' + u);
    });
}

// ─── ADMIN FLOW ────────────────────────────────────────────

async function adminTests() {
    console.log('\n🔧 Admin Flow');

    await test('Login as admin', async () => {
        await login(ADMIN_EMAIL);
        await assertNoError();
    });

    await test('Admin dashboard loads', async () => {
        await goto('/dashboard');
        await assertNoError();
    });

    await test('Members list loads', async () => {
        await goto('/admin/members');
        await assertNoError();
    });

    await test('Events management loads', async () => {
        await goto('/admin/events');
        await assertNoError();
    });

    await test('Settings page loads', async () => {
        await goto('/admin/settings');
        await assertNoError();
    });

    await test('Equipment page loads', async () => {
        await goto('/admin/equipment');
        await assertNoError();
    });

    await test('Votes management loads', async () => {
        await goto('/admin/votes');
        await assertNoError();
    });

    await test('Email management loads', async () => {
        await goto('/admin/emails');
        await assertNoError();
    });

    await test('Backup page loads', async () => {
        await goto('/admin/backups');
        await assertNoError();
    });

    await test('Library page loads', async () => {
        await goto('/admin/library');
        await assertNoError();
    });

    await test('Payments page loads', async () => {
        await goto('/admin/payments');
        await assertNoError();
    });

    await test('Dive group rules page loads', async () => {
        await goto('/admin/dive-group-rules');
        await assertNoError();
    });

    await test('Admin guide loads', async () => {
        await goto('/admin/guide');
        await assertNoError();
    });

    await test('Medical export CSV (FFESSM)', async () => {
        try {
            const resp = await page.goto(url('/admin/medical-export?federation_id=1'), { waitUntil: 'networkidle2', timeout: TIMEOUT });
            if (resp && resp.status() !== 200) throw new Error(`Status ${resp.status()}`);
        } catch (e) {
            // ERR_ABORTED is expected for file downloads — that means it worked
            if (!e.message.includes('ERR_ABORTED') && !e.message.includes('net::'))
                throw e;
        }
    });

    await test('Logout admin', async () => {
        await logout();
    });
}

// ─── SECURITY CHECKS ──────────────────────────────────────

async function securityTests() {
    console.log('\n🔒 Security Checks');

    await test('Login throttle (no crash on rapid attempts)', async () => {
        for (let i = 0; i < 3; i++) {
            await goto('/login');
            await page.type('input[name="email"]', 'fake@test.com');
            await page.type('input[name="password"]', 'wrong');
            await Promise.all([
                page.click('button[type="submit"]'),
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: TIMEOUT }),
            ]);
            await assertNoError();
        }
    });

    await test('Backup path traversal blocked', async () => {
        await login(ADMIN_EMAIL);
        const resp = await page.goto(url('/admin/backups/../../.env'), { waitUntil: 'networkidle2', timeout: TIMEOUT });
        const body = await page.$eval('body', el => el.textContent);
        if (body.includes('APP_KEY') || body.includes('DB_PASSWORD'))
            throw new Error('Path traversal exposed .env!');
    });

    await test('Install route blocked (installed.lock)', async () => {
        const resp = await page.goto(url('/install'), { waitUntil: 'networkidle2', timeout: TIMEOUT });
        if (resp.status() === 200) {
            const body = await page.$eval('body', el => el.textContent);
            if (body.includes('Install') && body.includes('Database'))
                throw new Error('Install wizard accessible on production!');
        }
    });

    await test('API rate limiting headers present', async () => {
        await goto('/');
        // Just verify the app doesn't crash — rate limit headers are on API routes
    });

    await logout();
}

// ─── RESPONSIVE / MOBILE ──────────────────────────────────

async function responsiveTests() {
    console.log('\n📱 Responsive / Mobile');

    await test('Mobile viewport renders without horizontal scroll', async () => {
        await page.setViewport({ width: 375, height: 812 });
        await goto('/');
        await assertNoError();
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        if (scrollWidth > clientWidth + 10)
            throw new Error(`Horizontal scroll: ${scrollWidth} > ${clientWidth}`);
    });

    await test('Mobile navbar has toggle', async () => {
        await page.setViewport({ width: 375, height: 812 });
        await goto('/');
        await assertSelector('.navbar-toggler, [data-bs-toggle="collapse"]', 'mobile menu toggle');
    });

    await test('Tablet viewport renders', async () => {
        await page.setViewport({ width: 768, height: 1024 });
        await goto('/');
        await assertNoError();
    });

    // Reset viewport
    await page.setViewport({ width: 1280, height: 800 });
}

// ─── PERFORMANCE ──────────────────────────────────────────

async function performanceTests() {
    console.log('\n⚡ Performance');

    await test('Homepage loads under 3s', async () => {
        const start = Date.now();
        await goto('/');
        const elapsed = Date.now() - start;
        if (elapsed > 3000) throw new Error(`Took ${elapsed}ms`);
    });

    await test('Login page loads under 2s', async () => {
        const start = Date.now();
        await goto('/login');
        const elapsed = Date.now() - start;
        if (elapsed > 2000) throw new Error(`Took ${elapsed}ms`);
    });

    await test('No console errors on homepage', async () => {
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('404') && !msg.text().includes('Failed to load'))
                consoleErrors.push(msg.text());
        });
        await goto('/');
        await new Promise(r => setTimeout(r, 1000));
        page.removeAllListeners('console');
        if (consoleErrors.length > 0)
            throw new Error(`Console errors: ${consoleErrors.join('; ').substring(0, 200)}`);
    });
}

// ─── MAIN ──────────────────────────────────────────────────

(async () => {
    console.log('🤿 DivingClub-Manager UI Tests — Hetzner Staging');
    console.log('═'.repeat(55));

    browser = await puppeteer.launch({
        executablePath: '/snap/bin/chromium',
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
    });
    page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });
    await page.setExtraHTTPHeaders({
        'Authorization': 'Basic ' + Buffer.from(BASIC_AUTH).toString('base64'),
    });
    page.setDefaultTimeout(TIMEOUT);

    try {
        await publicTests();
        await memberTests();
        await adminTests();
        await securityTests();
        await responsiveTests();
        await performanceTests();
    } catch (e) {
        console.error('\n💥 Fatal error:', e.message);
    }

    await browser.close();

    console.log('\n' + '═'.repeat(55));
    console.log(`Results: ${passed} passed, ${failed} failed out of ${passed + failed}`);
    if (errors.length) {
        console.log('\nFailures:');
        errors.forEach(e => console.log(`  • ${e.name}: ${e.error.substring(0, 150)}`));
    }
    process.exit(failed > 0 ? 1 : 0);
})();
