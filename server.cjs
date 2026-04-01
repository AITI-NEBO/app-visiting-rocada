const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 8080;

// ── Универсальная вспомогательная функция ────────────────────────────────────
async function proxyFetch(targetUrl, req, res) {
  try {
    const upstream = await fetch(targetUrl, {
      method: req.method,
      headers: {
        'Accept': req.headers['accept'] || '*/*',
        'Content-Type': req.headers['content-type'] || 'application/json',
      },
    });
    const text = await upstream.text();
    res
      .status(upstream.status)
      .set('Content-Type', upstream.headers.get('Content-Type') || 'application/json')
      .send(text);
  } catch (err) {
    console.error(`[proxy] fetch error for ${targetUrl}:`, err.message);
    res.status(502).json({ error: 'proxy_error', message: err.message });
  }
}

// ── /b24oauth/token → oauth.bitrix.info/oauth/token ─────────────────────────
app.get('/b24oauth/token/', async (req, res) => {
  const params = new URLSearchParams(req.query).toString();
  const url = `https://oauth.bitrix.info/oauth/token/?${params}`;
  console.log('[/b24oauth]', url);
  await proxyFetch(url, req, res);
});

// ── /b24rest/* → office.rocadatech.ru/rest/* ────────────────────────────────
app.use('/b24rest', async (req, res) => {
  const rest = req.path; // например /user.current.json
  const qs = new URLSearchParams(req.query).toString();
  const url = `https://office.rocadatech.ru/rest${rest}${qs ? '?' + qs : ''}`;
  console.log('[/b24rest]', url);
  await proxyFetch(url, req, res);
});

// ── /b24api/* → office.rocadatech.ru/local/modules/rocada.visits/... ─────────
app.use('/b24api', async (req, res) => {
  // Путь после /b24api/ становится значением route=
  const route = req.path.replace(/^\//, ''); // убираем ведущий /
  const extra = new URLSearchParams(req.query).toString();
  const qs = extra ? `route=${route}&${extra}` : `route=${route}`;
  const url = `https://office.rocadatech.ru/local/modules/rocada.visits/lib/router.php?${qs}`;
  console.log('[/b24api]', url);
  await proxyFetch(url, req, res);
});

// ── Static files from dist ────────────────────────────────────────────────────
app.use(express.static(path.join(__dirname, 'dist')));

// ── SPA fallback ──────────────────────────────────────────────────────────────
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Server running on port ${PORT}`);
});
