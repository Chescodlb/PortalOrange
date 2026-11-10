// Runs real Moodle events/queue/HTTPS against a local Postman simulation, never Microsoft.
import {spawn, execFileSync} from 'node:child_process';
import {mkdtemp, readFile, writeFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import https from 'node:https';
import http from 'node:http';

const folder = await mkdtemp(join(tmpdir(), 'orange-teams-'));
const publicationOnly = process.argv.includes('--publication');
const saveConsent = process.argv.includes('--save-consent');
const deliveryLogs = process.argv.includes('--delivery-logs');
let simulation, proxy;
const command = (cmd, args) => execFileSync(cmd, args, {encoding: 'utf8', timeout: 180000});
const run = (cmd, args) => new Promise((resolve, reject) => {
  const child = spawn(cmd, args, {stdio: ['ignore', 'pipe', 'pipe']});
  let output = ''; child.stdout.on('data', data => { output += data; });
  child.stderr.on('data', data => { output += data; });
  child.on('error', reject);
  child.on('exit', code => code === 0 ? resolve(output) : reject(new Error(output)));
});
try {
  command('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
    '-keyout', join(folder, 'key.pem'), '-out', join(folder, 'ca.pem'), '-subj', '/CN=host.docker.internal',
    '-addext', 'subjectAltName=DNS:host.docker.internal,DNS:localhost']);
  let logs = '';
  simulation = spawn('postman', ['simulation', 'run', 'postman/simulations/orange-teams.sim.yaml',
    '--port', '4016', '--no-history', '--output', 'ndjson'], {stdio: ['ignore', 'pipe', 'pipe']});
  simulation.stdout.on('data', chunk => { logs += chunk; });
  simulation.stderr.on('data', chunk => { logs += chunk; });
  for (let i = 0; i < 100; i++) {
    try { if ((await fetch('http://127.0.0.1:4016/teams/__stats')).ok) break; } catch {}
    if (simulation.exitCode !== null || i === 99) throw new Error('Simulation startup failed: ' + logs);
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  proxy = https.createServer({key: await readFile(join(folder, 'key.pem')), cert: await readFile(join(folder, 'ca.pem'))},
    (req, res) => {
      const upstream = http.request({host: '127.0.0.1', port: 4016, method: req.method, path: req.url,
        headers: req.headers}, reply => { res.writeHead(reply.statusCode, reply.headers); reply.pipe(res); });
      upstream.on('error', () => { res.writeHead(502); res.end(); });
      req.pipe(upstream);
    });
  await new Promise(resolve => proxy.listen(4017, '0.0.0.0', resolve));
  command('docker', ['compose', 'cp', join(folder, 'ca.pem'), 'web:/tmp/orange-teams-test-ca.pem']);
  const output = await run('docker', ['compose', 'exec', '-T', 'web', 'php',
    '-d', 'curl.cainfo=/tmp/orange-teams-test-ca.pem',
    deliveryLogs ? 'local/orangeportal/cli/check_teams_logs.php' : saveConsent ? 'local/orangeportal/cli/check_publication_save.php' : publicationOnly ? 'local/orangeportal/cli/check_training_publication.php' : 'local/orangeportal/cli/check_teams.php',
    '--endpoint=https://host.docker.internal:4017/teams/invoke']);
  process.stdout.write(output);
  const state = await (await fetch('http://127.0.0.1:4016/teams/__stats')).json();
  if (state.requests.length < (deliveryLogs ? 3 : saveConsent ? 4 : publicationOnly ? 2 : 10) || !logs.includes('request')) throw new Error('Missing evidence of actual HTTP requests');
  console.log(`PASS Postman simulation recorded ${state.requests.length} real JSON POST requests; no Microsoft calls`);
  await writeFile(join(folder, 'simulation.ndjson'), logs);
} finally {
  proxy?.closeAllConnections();
  await new Promise(resolve => proxy ? proxy.close(resolve) : resolve());
  simulation?.kill('SIGINT');
  try { command('docker', ['compose', 'exec', '-T', 'web', 'rm', '-f', '/tmp/orange-teams-test-ca.pem']); } catch {}
  await rm(folder, {recursive: true, force: true});
}
