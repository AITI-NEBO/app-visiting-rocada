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

// ── Proxy: /b24oauth → oauth.bitrix.info (OAuth2 token exchange) ───────────
app.use(
  '/b24oauth',
  createProxyMiddleware({
    target: 'https://oauth.bitrix.info',
    changeOrigin: true,
    secure: false,
    pathRewrite: { '^/b24oauth': '/oauth' },
  })
);

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
