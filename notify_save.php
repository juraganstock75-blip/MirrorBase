<?php
// notify_save.php — handler submission
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/geoip.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function back(array $qs): void {
    header('Location: /notify.php?' . http_build_query($qs));
    exit;
}
function backErr(string $err, array $old = []): void {
    $qs = ['err' => $err];
    if (!empty($old['attacker'])) $qs['a'] = $old['attacker'];
    if (!empty($old['team']))     $qs['t'] = $old['team'];
    if (!empty($old['poc']))      $qs['p'] = $old['poc'];
    if (!empty($old['reason']))   $qs['r'] = $old['reason'];
    if (!empty($old['urls']))     $qs['u'] = $old['urls'];
    back($qs);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back(['err' => 'Method tidak valid.']);
}

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['notify_csrf']) || !hash_equals($_SESSION['notify_csrf'], $csrf)) {
    backErr('Sesi tidak valid, coba lagi.');
}
unset($_SESSION['notify_csrf']);

if (!empty($_POST['website'])) {
    backErr('Ditolak.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$mkey = 'rlm_' . md5($ip);
$now = time();
$mbucket = $_SESSION[$mkey] ?? ['count' => 0, 'start' => $now];
if ($now - $mbucket['start'] > 3600) {
    $mbucket = ['count' => 0, 'start' => $now];
}
if ($mbucket['count'] >= 10) {
    backErr('Batas submit tercapai (10 per jam).');
}

$attacker = clean_input($_POST['attacker'] ?? '', 128);
$teamName = clean_input($_POST['team'] ?? '', 128);
$poc      = ($_POST['poc'] ?? '') !== '' ? max(1, min(31, (int)$_POST['poc'])) : null;
$reason   = ($_POST['reason'] ?? '') !== '' ? max(1, min(7, (int)$_POST['reason'])) : null;
$urlsRaw  = (string)($_POST['urls'] ?? '');

$oldMap = [
    'attacker' => $attacker,
    'team'     => $teamName,
    'poc'      => (string)$poc,
    'reason'   => (string)$reason,
    'urls'     => $urlsRaw,
];

if ($attacker === '') {
    backErr('Attacker wajib diisi.', $oldMap);
}

$lines = preg_split('/\r\n|\r|\n/', $urlsRaw);
$lines = array_map('trim', $lines);
$lines = array_values(array_filter($lines, fn($l) => $l !== ''));

if (empty($lines)) {
    backErr('Daftar URL kosong.', $oldMap);
}
if (count($lines) > 500) {
    backErr('Maks 500 URL per submit.', $oldMap);
}

// team
$teamId = null;
if ($teamName !== '') {
    $stmt = $pdo->prepare('SELECT id FROM teams WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => $teamName]);
    $teamId = (int)$stmt->fetchColumn() ?: null;
    if (!$teamId) {
        try {
            $ins = $pdo->prepare('INSERT INTO teams (name) VALUES (:n)');
            $ins->execute([':n' => $teamName]);
            $teamId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {}
    }
}

$insert = $pdo->prepare("
    INSERT INTO archives
      (uid, target_url, target_domain, category, flag_home, flag_mass, flag_redef,
       country_code, isp, asn, is_special, defacer, team_id, ip_address, ip_mass,
       server_type, target_os, poc, reason, mirror_url, screenshot, archive_date)
    VALUES
      (:uid, :u, :d, :cat, :h, :m, :r, :cc, :isp, :asn, :sp, :df, :t, :ip, :ipm,
       :sv, :os, :poc, :reason, :mi, :sc, :dt)
");

$today = date('Y-m-d');
$ok = 0;
$fail = 0;
$errs = [];
$seen = [];

foreach ($lines as $idx => $line) {
    $lineNo = $idx + 1;

    $parts = array_map('trim', explode('|', $line));
    $rawUrl = $parts[0] ?? '';

    $url = clean_url(normalize_url($rawUrl));
    if ($url === null) {
        $errs[] = "#{$lineNo}: URL tidak valid";
        $fail++;
        continue;
    }

    $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
    if (preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $path)) {
        $errs[] = "#{$lineNo}: file gambar ditolak";
        $fail++;
        continue;
    }
    if (str_contains($url, '/~')) {
        $errs[] = "#{$lineNo}: /~ tidak diizinkan";
        $fail++;
        continue;
    }
    if (isset($seen[$url])) {
        $errs[] = "#{$lineNo}: duplikat";
        $fail++;
        continue;
    }
    $seen[$url] = true;

    $domain    = domain_from_url($url);
    $category  = classify_domain($domain);
    $isHome    = is_homepage($url) ? 1 : 0;
    $isSpecial = ($category === 'special') ? 1 : 0;

    $lineDefacer = clean_input($parts[1] ?? '', 128);
    $linePoc     = isset($parts[2]) && $parts[2] !== ''
        ? max(1, min(31, (int)$parts[2])) : $poc;
    $lineReason  = isset($parts[3]) && $parts[3] !== ''
        ? max(1, min(7, (int)$parts[3])) : $reason;
    $lineMirror  = clean_url($parts[4] ?? null);

    if ($lineDefacer === '') $lineDefacer = $attacker;

    // ---- DNS resolve IP target ----
    $ipAddr = null;
    $resolved = @gethostbyname($domain);
    if ($resolved !== $domain && filter_var($resolved, FILTER_VALIDATE_IP)) {
        $ipAddr = $resolved;
    }

    // ---- geoip ----
    $countryCode = null;
    $isp = null;
    $asn = null;
    if ($ipAddr) {
        $geo = geoip_full($ipAddr);
        $countryCode = $geo['cc'];
        $isp = $geo['isp'];
        $asn = $geo['asn'];
    }

    // ---- detect OS + server header ----
    $targetOs = null;
    $serverHeader = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_HEADER         => true,
        ]);
        $resp = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr  = substr((string)$resp, 0, $headerSize);
        curl_close($ch);

        foreach (explode("\n", $headerStr) as $hdrLine) {
            if (stripos($hdrLine, 'Server:') === 0) {
                $serverHeader = trim(substr($hdrLine, 7));
                break;
            }
        }
        if ($serverHeader) {
            $s = strtolower($serverHeader);
            if (str_contains($s, 'ubuntu'))    $targetOs = 'Ubuntu';
            elseif (str_contains($s, 'debian')) $targetOs = 'Debian';
            elseif (str_contains($s, 'centos')) $targetOs = 'CentOS';
            elseif (str_contains($s, 'red hat') || str_contains($s, 'redhat')) $targetOs = 'Red Hat';
            elseif (str_contains($s, 'fedora')) $targetOs = 'Fedora';
            elseif (str_contains($s, 'almalinux')) $targetOs = 'AlmaLinux';
            elseif (str_contains($s, 'rocky')) $targetOs = 'Rocky Linux';
            elseif (str_contains($s, 'alpine')) $targetOs = 'Alpine';
            elseif (str_contains($s, 'freebsd')) $targetOs = 'FreeBSD';
            elseif (str_contains($s, 'openbsd')) $targetOs = 'OpenBSD';
            elseif (str_contains($s, 'win32') || str_contains($s, 'win64')
                || str_contains($s, 'microsoft') || str_contains($s, 'iis')) $targetOs = 'Windows';
            elseif (str_contains($s, 'apache') || str_contains($s, 'nginx')
                || str_contains($s, 'litespeed') || str_contains($s, 'openresty')) $targetOs = 'Linux/Unix';
        }
    }

    $uid = generate_uid($pdo);

    try {
        $insert->execute([
            ':uid'    => $uid,
            ':u'      => $url,
            ':d'      => $domain,
            ':cat'    => $category,
            ':h'      => $isHome,
            ':m'      => 0,
            ':r'      => 0,
            ':cc'     => $countryCode,
            ':isp'    => $isp,
            ':asn'    => $asn,
            ':sp'     => $isSpecial,
            ':df'     => $lineDefacer,
            ':t'      => $teamId,
            ':ip'     => $ipAddr,
            ':ipm'    => null,
            ':sv'     => $serverHeader,
            ':os'     => $targetOs,
            ':poc'    => $linePoc,
            ':reason' => $lineReason,
            ':mi'     => $lineMirror,
            ':sc'     => null,
            ':dt'     => $today,
        ]);
        $ok++;
    } catch (PDOException $e) {
        $errs[] = "#{$lineNo}: gagal simpan";
        $fail++;
    }
}

$mbucket['count']++;
$_SESSION[$mkey] = $mbucket;

$qs = ['ok' => $ok, 'fail' => $fail, 'total' => count($lines)];
if ($errs) $qs['errs'] = implode('||', array_slice($errs, 0, 50));
back($qs);