// npm run dev: PHP API on 127.0.0.1:8001 (server.php) + Vite on :5173 with /api proxied to it.
// PHP: PHP_BIN, else php on PATH, else C:\xampp\php\php.exe.
import { spawn, spawnSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const win = process.platform === 'win32'
const onPath = spawnSync(win ? 'where' : 'which', ['php'], { encoding: 'utf8' }).stdout?.split(/\r?\n/)[0]
const php = process.env.PHP_BIN || onPath || (win && existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php')

const install = spawnSync(php, ['backend/tools/install.php'], { cwd: root, stdio: 'inherit' })
if (install.error) { console.error(`Cannot run PHP (${php}). Set PHP_BIN.`); process.exit(1) }
if (install.status !== 0) { console.error('The database is not ready (see above). Start MySQL, then run npm run dev again.'); process.exit(1) }

const procs = [
  spawn(php, ['-S', '127.0.0.1:8001', 'server.php'], { cwd: root, stdio: 'inherit' }),
  spawn(win ? 'npm.cmd' : 'npm', ['--prefix', 'frontend', 'run', 'dev'], { cwd: root, stdio: 'inherit', shell: win })
]
const stop = () => { for (const p of procs) p.kill(); process.exit() }
for (const p of procs) p.on('exit', stop)
process.on('SIGINT', stop)
process.on('SIGTERM', stop)
