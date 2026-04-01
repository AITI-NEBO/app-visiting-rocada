const express = require('express');
const path = require('path');
const { createProxyMiddleware } = require('http-proxy-middleware');

const app = express();
const PORT = process.env.PORT || 8080;

// ── Proxy: /b24api → office.rocadatech.ru (наш модуль визитов) ─────────────
app.use(
  '/b24api',
  createProxyMiddleware({
    target: 'https://office.rocadatech.ru',
    changeOrigin: true,
    secure: false,
    pathRewrite: (path) => {
      const [pathname, qs] = path.split('?');
      const route = pathname.replace(/^\/b24api\/?/, '');
      const query = qs ? `route=${route}&${qs}` : `route=${route}`;
      return `/local/modules/rocada.visits/lib/router.php?${query}`;
    },
  })
);

// ── Native fetch: /b24oauth/token → oauth.bitrix.info/oauth/token ──────────
// Используем нативный fetch вместо прокси — http-proxy-middleware v3
// иногда возвращает пустое тело для HTTPS-ответов.
app.get('/b24oauth/token/', async (req, res) => {
  try {
    const params = new URLSearchParams(req.query).toString();
    const url = `https://oauth.bitrix.info/oauth/token/?${params}`;
    console.log('[/b24oauth] →', url);
    const upstream = await fetch(url);
    const text = await upstream.text();
    console.log('[/b24oauth] ←', upstream.status, text.slice(0, 200));
    res.status(upstream.status)
       .set('Content-Type', upstream.headers.get('Content-Type') || 'application/json')
       .send(text);
  } catch (err) {
    console.error('[/b24oauth] fetch error:', err.message);
    res.status(502).json({ error: 'proxy_error', message: err.message });
  }
});

// ── Proxy: /b24rest → office.rocadatech.ru (REST API Битрикс24) ───────────
app.use(
  '/b24rest',
  createProxyMiddleware({
    target: 'https://office.rocadatech.ru',
    changeOrigin: true,
    secure: false,
    pathRewrite: { '^/b24rest': '/rest' },
  })
);

// ── Static files from dist ─────────────────────────────────────────────────
app.use(express.static(path.join(__dirname, 'dist')));

// ── SPA fallback ───────────────────────────────────────────────────────────
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Server running on port ${PORT}`);
});
