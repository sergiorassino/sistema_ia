<?php
/**
 * Disparador web de restaurar.sh (VPS de emergencia, uso desde el celular).
 *
 * No pasa por Laravel: tiene que funcionar aunque vendor/ o la BD estén rotos.
 * Clave y hosts: plantilla scripts/emergencia/config.example.env (se copian
 * a config.env al crear el lab). config.env pisa la plantilla si define las
 * mismas claves. En Hostinger queda 404 (host + HABILITAR_RESPALDO=1).
 *
 * docs/10-backup-y-vps-emergencia.md §4.1
 */
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

const TOKEN_MIN = 16;
const SESSION_NAME = 'se_emergencia_restaurar';
const SESSION_TTL = 28800; // 8 h
const RATE_FAIL_MAX = 8;
const RATE_FAIL_VENTANA = 900;

$appDir = dirname(__DIR__);
$confPath = $appDir.'/scripts/emergencia/config.env';
$examplePath = $appDir.'/scripts/emergencia/config.example.env';
$scriptSh = $appDir.'/scripts/emergencia/restaurar.sh';
$tenant = basename($appDir);
$logFile = $appDir.'/storage/logs/restaurar-emergencia.log';
$lockFile = '/tmp/se-'.$tenant.'-restaurar.lock';
$rateFile = '/tmp/se-'.$tenant.'-restaurar-web-rate.json';

$conf = array_merge(parse_env_file($examplePath), parse_env_file($confPath));
conf_404_si_apagado($confPath, $appDir, $conf);

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_TTL,
    'path' => '/',
    'secure' => https_on(),
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.gc_maxlifetime', (string) SESSION_TTL);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$accion = (string) ($_POST['accion'] ?? $_GET['accion'] ?? '');

if ($accion === 'estado') {
    exigir_sesion_json();
    json_out(estado_restauracion($lockFile, $logFile));
}

if ($accion === 'salir' && es_post()) {
    exigir_csrf();
    $_SESSION = [];
    session_destroy();
    redirigir_aqui();
}

$flash = '';
$flashTipo = 'ok';

if ($accion === 'entrar' && es_post()) {
    exigir_csrf();
    if (rate_bloqueado($rateFile)) {
        $flash = 'Demasiados intentos. Esperá 15 minutos.';
        $flashTipo = 'error';
    } elseif (!hash_equals($conf['RESTAURAR_WEB_TOKEN'], (string) ($_POST['token'] ?? ''))) {
        rate_fallo($rateFile);
        usleep(400000);
        $flash = 'Clave incorrecta.';
        $flashTipo = 'error';
    } else {
        rate_reset($rateFile);
        session_regenerate_id(true);
        $_SESSION['ok'] = 1;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        redirigir_aqui();
    }
}

if ($accion === 'lanzar' && es_post()) {
    exigir_csrf();
    if (empty($_SESSION['ok'])) {
        $flash = 'Ingresá la clave primero.';
        $flashTipo = 'error';
    } else {
        $dry = (($_POST['modo'] ?? '') === 'dry-run');
        if (! $dry && empty($_POST['confirmo'])) {
            $flash = 'Marcá la casilla de confirmación para restaurar de verdad.';
            $flashTipo = 'error';
        } else {
            $r = lanzar_restaurar($appDir, $scriptSh, $logFile, $lockFile, $conf, $dry);
            $flash = $r['mensaje'];
            $flashTipo = $r['ok'] ? 'ok' : 'error';
        }
    }
}

$logueado = ! empty($_SESSION['ok']);
$estado = $logueado ? estado_restauracion($lockFile, $logFile) : null;
html_pagina($tenant, $logueado, $_SESSION['csrf'], $flash, $flashTipo, $estado);

// ---------------------------------------------------------------------------

function conf_404_si_apagado(string $confPath, string $appDir, array $conf): void
{
    if (! is_readable($confPath)) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
    if (($conf['HABILITAR_RESTAURAR_WEB'] ?? '') !== '1') {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
    $hosts = array_filter(array_map('trim', explode(',', (string) ($conf['RESTAURAR_WEB_HOST'] ?? ''))));
    if ($hosts === []) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
    $actual = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $okHost = false;
    foreach ($hosts as $h) {
        if (strtolower($h) === $actual) {
            $okHost = true;
            break;
        }
    }
    if (! $okHost) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
    $token = (string) ($conf['RESTAURAR_WEB_TOKEN'] ?? '');
    if (strlen($token) < TOKEN_MIN) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Falta RESTAURAR_WEB_TOKEN (mínimo ".TOKEN_MIN." caracteres) en config.example.env o config.env.\n";
        exit;
    }
    $env = parse_env_file($appDir.'/.env');
    if (($env['HABILITAR_RESPALDO'] ?? '0') === '1') {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }
}

function parse_env_file(string $path): array
{
    $out = [];
    if (! is_readable($path)) {
        return $out;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $out;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2) {
            $q = $v[0];
            if (($q === '"' || $q === "'") && str_ends_with($v, $q)) {
                $v = substr($v, 1, -1);
            }
        }
        $out[$k] = $v;
    }

    return $out;
}

function https_on(): bool
{
    if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function exigir_csrf(): void
{
    $ok = hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
    if (! $ok) {
        http_response_code(400);
        echo 'CSRF';
        exit;
    }
}

function exigir_sesion_json(): void
{
    if (empty($_SESSION['ok'])) {
        json_out(['ok' => false, 'error' => 'no autorizado'], 401);
    }
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirigir_aqui(): void
{
    $aqui = (string) ($_SERVER['SCRIPT_NAME'] ?? '/emergencia-restaurar.php');
    header('Location: '.$aqui, true, 303);
    exit;
}

function ip_cliente(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
}

function rate_datos(string $file): array
{
    $raw = is_readable($file) ? file_get_contents($file) : false;
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if (! is_array($j)) {
        $j = [];
    }
    $ip = ip_cliente();
    $row = $j[$ip] ?? ['n' => 0, 't' => 0];
    if (time() - (int) $row['t'] > RATE_FAIL_VENTANA) {
        $row = ['n' => 0, 't' => time()];
    }

    return [$j, $ip, $row];
}

function rate_bloqueado(string $file): bool
{
    [, , $row] = rate_datos($file);

    return ((int) $row['n']) >= RATE_FAIL_MAX;
}

function rate_fallo(string $file): void
{
    [$j, $ip, $row] = rate_datos($file);
    $row['n'] = (int) $row['n'] + 1;
    $row['t'] = time();
    $j[$ip] = $row;
    @file_put_contents($file, json_encode($j), LOCK_EX);
}

function rate_reset(string $file): void
{
    [$j, $ip] = rate_datos($file);
    unset($j[$ip]);
    @file_put_contents($file, json_encode($j), LOCK_EX);
}

function restore_en_curso(string $lockFile): bool
{
    $fp = @fopen($lockFile, 'c');
    if ($fp === false) {
        return false;
    }
    $libre = flock($fp, LOCK_EX | LOCK_NB);
    if ($libre) {
        flock($fp, LOCK_UN);
        fclose($fp);

        return false;
    }
    fclose($fp);

    return true;
}

function leer_log(string $logFile): string
{
    if (! is_readable($logFile)) {
        return '';
    }
    $size = filesize($logFile);
    if ($size === false || $size === 0) {
        return '';
    }
    $max = 120000;
    if ($size <= $max) {
        return (string) file_get_contents($logFile);
    }
    $fp = fopen($logFile, 'rb');
    if ($fp === false) {
        return '';
    }
    fseek($fp, -$max, SEEK_END);
    $txt = (string) stream_get_contents($fp);
    fclose($fp);

    return $txt;
}

function php_fn_ok(string $name): bool
{
    if (! function_exists($name)) {
        return false;
    }
    $dis = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));

    return ! in_array(strtolower($name), $dis, true);
}

function pedido_path(string $logFile): string
{
    return dirname($logFile).'/restaurar-web.pedido';
}

function estado_restauracion(string $lockFile, string $logFile): array
{
    $log = leer_log($logFile);
    $running = restore_en_curso($lockFile);
    $enCola = is_file(pedido_path($logFile));
    $exit = null;
    if (preg_match('/^EXIT:(\d+)\s*$/m', $log, $m)) {
        $exit = (int) $m[1];
    }
    $lista = str_contains($log, 'Restauración lista.');
    $error = (bool) preg_match('/^ERROR:/m', $log);

    return [
        'ok' => true,
        'running' => $running || $enCola,
        'en_cola' => $enCola,
        'exit' => $exit,
        'lista' => $lista && $exit === 0 && ! $enCola,
        'error' => $error || ($exit !== null && $exit !== 0),
        'log' => $log,
    ];
}

function home_dir(): string
{
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $pw = posix_getpwuid(posix_geteuid());
        if (is_array($pw) && ! empty($pw['dir'])) {
            return (string) $pw['dir'];
        }
    }
    $h = getenv('HOME');

    return ($h !== false && $h !== '') ? $h : '/home/admin';
}

function lanzar_en_fondo(string $lanzador): ?int
{
    if (php_fn_ok('proc_open')) {
        $des = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        $pipes = [];
        $proc = @proc_open($lanzador, $des, $pipes);
        if (is_resource($proc)) {
            $out = '';
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                $out = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
            }
            proc_close($proc);
            $pid = (int) trim($out);

            return $pid > 0 ? $pid : 1;
        }
    }
    if (php_fn_ok('exec')) {
        $lineas = [];
        exec($lanzador, $lineas);

        return isset($lineas[0]) ? (int) $lineas[0] : 1;
    }
    if (php_fn_ok('shell_exec')) {
        $out = (string) shell_exec($lanzador);
        $pid = (int) trim($out);

        return $pid > 0 ? $pid : 1;
    }
    if (php_fn_ok('popen')) {
        $h = @popen($lanzador, 'r');
        if (is_resource($h)) {
            $out = (string) stream_get_contents($h);
            pclose($h);
            $pid = (int) trim($out);

            return $pid > 0 ? $pid : 1;
        }
    }

    return null;
}

function salida_comando(string $cmd): ?string
{
    if (php_fn_ok('exec')) {
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        if ($code !== 0) {
            return null;
        }

        return trim(implode("\n", $out));
    }
    if (php_fn_ok('shell_exec')) {
        $out = shell_exec($cmd.' 2>/dev/null');
        if (! is_string($out)) {
            return null;
        }

        return trim($out);
    }
    if (php_fn_ok('proc_open')) {
        $des = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $proc = @proc_open($cmd, $des, $pipes);
        if (! is_resource($proc)) {
            return null;
        }
        $out = isset($pipes[1]) && is_resource($pipes[1]) ? (string) stream_get_contents($pipes[1]) : '';
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (proc_close($proc) !== 0) {
            return null;
        }

        return trim($out);
    }

    return null;
}

/**
 * DirectAdmin deja /bin y /usr/bin fuera de open_basedir: is_executable()
 * dice que bash no existe aunque exec() sí pueda lanzarlo.
 */
function resolver_bash(): string
{
    foreach (['/bin/bash', '/usr/bin/bash'] as $bin) {
        if (@is_executable($bin)) {
            return $bin;
        }
    }
    $puede = php_fn_ok('exec') || php_fn_ok('shell_exec') || php_fn_ok('proc_open') || php_fn_ok('popen');
    if (! $puede) {
        return '/bin/bash';
    }
    foreach (['/usr/bin/bash', '/bin/bash'] as $bin) {
        $cmd = escapeshellarg($bin).' -c '.escapeshellarg('echo ok');
        if (salida_comando($cmd) === 'ok') {
            return $bin;
        }
    }

    return '';
}

function resolver_setsid(string $bash): string
{
    foreach (['/usr/bin/setsid', '/bin/setsid'] as $bin) {
        if (@is_executable($bin)) {
            return $bin;
        }
    }
    if ($bash === '') {
        return '';
    }
    $cual = salida_comando(escapeshellarg($bash).' -c '.escapeshellarg('command -v setsid'));
    if (is_string($cual) && $cual !== '' && $cual[0] === '/') {
        return $cual;
    }

    return '';
}

function lanzar_restaurar(
    string $appDir,
    string $scriptSh,
    string $logFile,
    string $lockFile,
    array $conf,
    bool $dry
): array {
    $bash = resolver_bash();
    if ($bash === '') {
        return ['ok' => false, 'mensaje' => 'No está bash en este servidor (/bin/bash ni /usr/bin/bash).'];
    }
    if (! is_readable($scriptSh)) {
        return ['ok' => false, 'mensaje' => 'No se encuentra restaurar.sh.'];
    }
    if (restore_en_curso($lockFile)) {
        return ['ok' => false, 'mensaje' => 'Ya hay una restauración en curso.'];
    }
    $pedido = pedido_path($logFile);
    if (is_file($pedido)) {
        return ['ok' => false, 'mensaje' => 'Ya hay un pedido en cola. Esperá al cron web-cola.sh o borrá storage/logs/restaurar-web.pedido.'];
    }

    $logDir = dirname($logFile);
    if (! is_dir($logDir) && ! @mkdir($logDir, 0750, true) && ! is_dir($logDir)) {
        return ['ok' => false, 'mensaje' => 'No se pudo crear '.$logDir];
    }

    $ip = ip_cliente();
    $modo = $dry ? '--dry-run --yes' : '--yes';
    $cabecera = '['.date('Y-m-d H:i:s')."] Disparado desde la web (IP {$ip}".($dry ? ', dry-run' : '').").\n";
    file_put_contents($logFile, $cabecera);

    $home = home_dir();
    $phpBin = $conf['PHP_BIN'] ?? '/usr/local/php83/bin/php';
    $phpDir = dirname((string) $phpBin);
    $path = $phpDir.':'.$home.'/bin:'.$home.'/.local/bin:/usr/local/bin:/usr/bin:/bin';
    $setsid = resolver_setsid($bash);

    $inner = 'export HOME='.escapeshellarg($home)
        .' USER='.escapeshellarg(basename($home))
        .' LOGNAME='.escapeshellarg(basename($home))
        .' PATH='.escapeshellarg($path)
        .'; cd '.escapeshellarg($appDir)
        .'; '.escapeshellarg($bash).' '.escapeshellarg($scriptSh).' '.$modo
        .' >> '.escapeshellarg($logFile).' 2>&1'
        .'; echo EXIT:$? >> '.escapeshellarg($logFile);

    $lanzador = ($setsid !== '' ? escapeshellarg($setsid).' ' : '')
        .'nohup '.escapeshellarg($bash).' -c '.escapeshellarg($inner)
        .' </dev/null >/dev/null 2>&1 & echo $!';

    ignore_user_abort(true);
    $pidN = lanzar_en_fondo($lanzador);
    if ($pidN !== null) {
        if ($pidN > 1) {
            file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] PID {$pidN}\n", FILE_APPEND);
        }

        return [
            'ok' => true,
            'mensaje' => $dry
                ? 'Simulación lanzada. El log se actualiza abajo (no pisa la base).'
                : 'Restauración lanzada. Dejá esta pantalla abierta; tarda varios minutos (sobre todo el import MySQL).',
        ];
    }

    $linea = $dry ? "dry-run\n" : "yes\n";
    file_put_contents($pedido, $linea);
    file_put_contents(
        $logFile,
        '['.date('Y-m-d H:i:s')."] PHP no puede lanzar procesos (exec/proc_open deshabilitados). Pedido en cola para web-cola.sh.\n",
        FILE_APPEND
    );

    return [
        'ok' => true,
        'mensaje' => 'PHP-FPM no puede ejecutar bash (exec/proc_open están cortados). Dejé el pedido en cola. En el VPS, una sola vez: DirectAdmin → Cron Jobs, cada minuto: /bin/bash '.$appDir.'/scripts/emergencia/web-cola.sh — o habilitá exec en PHP Settings (docs/10 Paso H).',
    ];
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function html_pagina(
    string $tenant,
    bool $logueado,
    string $csrf,
    string $flash,
    string $flashTipo,
    ?array $estado
): void {
    $titulo = 'Sistemas Escolares — Restaurar emergencia';
    $running = $estado['running'] ?? false;
    $log = $estado['log'] ?? '';
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= h($titulo) ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            background: #333333; color: #333333; min-height: 100dvh;
        }
        .wrap { max-width: 42rem; margin: 0 auto; padding: 1rem 1rem 2.5rem; }
        header {
            color: #e8eef6; padding: 1.1rem 0 0.8rem;
        }
        header h1 { font-size: 1.15rem; margin: 0 0 .25rem; font-weight: 700; color: #C1D7DA; }
        header p { margin: 0; opacity: .8; font-size: .9rem; }
        .card {
            background: #fff; border-radius: 14px; padding: 1.1rem 1rem 1.2rem;
            box-shadow: 0 8px 28px rgb(0 0 0 / .18);
        }
        label { display: block; font-weight: 600; font-size: .9rem; margin: .4rem 0 .35rem; }
        input[type=password] {
            width: 100%; font-size: 1.05rem; padding: .7rem .75rem;
            border: 1px solid #c5d0dc; border-radius: 8px;
        }
        .check { display: flex; gap: .6rem; align-items: flex-start; margin: 1rem 0; font-size: .95rem; }
        .check input { margin-top: .2rem; width: 1.15rem; height: 1.15rem; }
        .btns { display: flex; flex-direction: column; gap: .55rem; margin-top: .6rem; }
        button, .btn {
            appearance: none; border: 0; border-radius: 10px; padding: .85rem 1rem;
            font-size: 1.02rem; font-weight: 700; cursor: pointer; text-align: center;
            text-decoration: none; display: block; width: 100%;
        }
        .enter { background: #40848D; color: #fff; }
        .go { background: #c0392b; color: #fff; }
        .go:disabled { background: #b0bec5; cursor: not-allowed; }
        .dry { background: #e8eef4; color: #1a334d; }
        .out { background: transparent; color: #5a7188; font-weight: 600; font-size: .9rem; }
        .flash { padding: .7rem .8rem; border-radius: 8px; margin-bottom: .9rem; font-size: .95rem; }
        .flash.ok { background: #e7f6ea; color: #1b5e20; }
        .flash.error { background: #fdecea; color: #8a1f11; }
        .badge {
            display: inline-block; font-size: .75rem; font-weight: 700;
            padding: .2rem .5rem; border-radius: 999px; margin-bottom: .6rem;
        }
        .badge.run { background: #fff3cd; color: #7a5b00; }
        .badge.ok { background: #e7f6ea; color: #1b5e20; }
        .badge.err { background: #fdecea; color: #8a1f11; }
        .badge.idle { background: #eef2f6; color: #445566; }
        pre {
            background: #0d1b2a; color: #d6e2f0; border-radius: 10px;
            padding: .8rem; font-size: .72rem; line-height: 1.4;
            overflow: auto; max-height: 55dvh; white-space: pre-wrap; word-break: break-word;
            min-height: 8rem;
        }
        .hint { font-size: .82rem; color: #5a7188; margin: .4rem 0 0; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Restaurar colegio</h1>
        <p>VPS de emergencia · <strong><?= h($tenant) ?></strong></p>
    </header>
    <div class="card">
        <?php if ($flash !== ''): ?>
            <div class="flash <?= h($flashTipo) ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <?php if (! $logueado): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="accion" value="entrar">
                <label for="token">Clave de restauración</label>
                <input id="token" name="token" type="password" required minlength="<?= TOKEN_MIN ?>"
                       inputmode="text" autocapitalize="off" autocorrect="off" spellcheck="false"
                       placeholder="La que está en config.env">
                <div class="btns">
                    <button class="enter" type="submit">Entrar</button>
                </div>
                <p class="hint">La misma clave que configuraste en el VPS. No es la contraseña del panel ni de MySQL.</p>
            </form>
        <?php else: ?>
            <?php
            $cls = 'idle';
            $txt = 'En espera';
            if (! empty($estado['en_cola'])) {
                $cls = 'run';
                $txt = 'En cola…';
            } elseif (! empty($estado['running'])) {
                $cls = 'run';
                $txt = 'En curso…';
            } elseif (! empty($estado['lista'])) {
                $cls = 'ok';
                $txt = 'Lista';
            } elseif (! empty($estado['error'])) {
                $cls = 'err';
                $txt = 'Falló';
            }
            ?>
            <span class="badge <?= $cls ?>" id="badge"><?= h($txt) ?></span>
            <form method="post" id="form-go">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="accion" value="lanzar">
                <input type="hidden" name="modo" value="yes">
                <label class="check">
                    <input type="checkbox" name="confirmo" value="1" required <?= $running ? 'disabled' : '' ?>>
                    <span>Entiendo que esto pisa la base y los archivos del colegio en <strong>este</strong> VPS con el último dump (producción no se toca).</span>
                </label>
                <div class="btns">
                    <button class="go" type="submit" <?= $running ? 'disabled' : '' ?>>Restaurar ahora</button>
                </div>
            </form>
            <form method="post" style="margin-top:.5rem">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="accion" value="lanzar">
                <input type="hidden" name="modo" value="dry-run">
                <button class="dry" type="submit" <?= $running ? 'disabled' : '' ?>>Simular (no cambia nada)</button>
            </form>
            <h2 style="font-size:.9rem;margin:1.1rem 0 .4rem">Log</h2>
            <pre id="log"><?= h($log !== '' ? $log : '(todavía no hay log)') ?></pre>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="accion" value="salir">
                <button class="out" type="submit">Cerrar sesión</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($logueado): ?>
<script>
(function () {
    const logEl = document.getElementById('log');
    const badge = document.getElementById('badge');
    const btns = document.querySelectorAll('button.go, button.dry');
    const box = document.querySelector('input[name=confirmo]');
    function pintar(s) {
        logEl.textContent = s.log || '(todavía no hay log)';
        logEl.scrollTop = logEl.scrollHeight;
        let cls = 'idle', txt = 'En espera';
        if (s.en_cola) { cls = 'run'; txt = 'En cola…'; }
        else if (s.running) { cls = 'run'; txt = 'En curso…'; }
        else if (s.lista) { cls = 'ok'; txt = 'Lista'; }
        else if (s.error) { cls = 'err'; txt = 'Falló'; }
        badge.className = 'badge ' + cls;
        badge.textContent = txt;
        btns.forEach(b => { b.disabled = !!s.running; });
        if (box) box.disabled = !!s.running;
    }
    async function poll() {
        try {
            const r = await fetch('?accion=estado', { credentials: 'same-origin', cache: 'no-store' });
            if (r.ok) pintar(await r.json());
        } catch (e) {}
        setTimeout(poll, 2500);
    }
    logEl.scrollTop = logEl.scrollHeight;
    poll();
})();
</script>
<?php endif; ?>
</body>
</html>
    <?php
    exit;
}
