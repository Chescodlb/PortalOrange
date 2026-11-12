// Local stand-in for the client-supplied POST contract. Never calls Microsoft.
const http = require('http');
const state = {requests: [], code: 202, body: '{}'};
http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  res.setHeader('Content-Type', 'application/json');
  if (url.pathname === '/__stats') return res.end(JSON.stringify(state));
  if (url.pathname === '/__control') {
    state.code = Number(url.searchParams.get('code') || 202);
    state.body = url.searchParams.has('body') ? url.searchParams.get('body') : '{}';
    return res.end('{}');
  }
  if (req.method !== 'POST' || url.pathname !== '/invoke') {
    res.writeHead(501); return res.end('{}');
  }
  const body = req.body;
  if (!body || Object.keys(body).sort().join(',') !== 'contenido,fechaPublicacion,servicioAfectado,titulo,url' ||
      Object.values(body).some(value => typeof value !== 'string') || !/^https?:\/\//.test(body.url) ||
      !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(body.fechaPublicacion)) {
    res.writeHead(400); return res.end('{"error":"Invalid contract"}');
  }
  state.requests.push({body, eventid: req.headers['x-orange-event-id'], type: req.headers['content-type']});
  if (state.code === 302) res.setHeader('Location', '/invoke?redirected=1');
  res.writeHead(state.code); res.end(state.body);
}).listen(process.env.PORT || 4016);
