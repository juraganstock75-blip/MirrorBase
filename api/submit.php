<?php
// api/submit.php — endpoint JSON mass submit
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/geoip.php';

send_json_security_headers();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$keysFile = __DIR__ . '/keys.json';
$activeKeys = [];
if (is_file($keysFile)) {
    $keysData = json_decode((string)@file_get_contents($keysFile), true);
    if (is_array($keysData)) {
        foreach ($keysData as $info) {
            if (!empty($info['active']) && !empty($info['key'])) {
                $activeKeys[] = (string)$info['key'];
            }
        }
    }
}
if (empty($activeKeys)) {
    json_response(['ok' => false, 'error' => 'Server misconfigured'], 500);
}

$provided = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
$valid = false;
foreach ($activeKeys as $k) {
    if (is_string($provided) && hash_equals($k, $provided)) {
        $valid = true;
        break;
    }
}
if (!$valid) {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'POST only'], 405);
}

$maxBody = 100 * 1024;
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBody) {
    json_response(['ok' => false, 'error' => 'Request too large'], 413);
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > $maxBody) {
        json_response(['ok' => false, 'error' => 'Request too large'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
    }
} else {
    $data = $_POST;
}

$def_defacer = clean_input($data['defacer'] ?? '', 128);
$def_team    = clean_input($data['team'] ?? '', 128);
$def_poc     = isset($data['poc']) && $data['poc'] !== ''
    ? max(1, min(31, (int)$data['poc'])) : null;
$def_reason  = isset($data['reason']) && $data['reason'] !== ''
    ? max(1, min(7, (int)$data['reason'])) : null;
$def_server  = clean_input($data['server_type'] ?? '', 128);
$def_os      = clean_input($data['target_os'] ?? '', 64);
$def_date    = (string)($data['archive_date'] ?? date('Y-m-d'));
$urls        = $data['urls'] ?? [];

if ($def_defacer === '') {
    json_response(['ok' => false, 'error' => 'defacer wajib'], 400);
}
if (!is_array($urls) || empty($urls)) {
    json_response(['ok' => false, 'error' => 'urls harus array'], 400);
}
if (count($urls) > 500) {
    json_response(['ok' => false, 'error' => 'maks 500 url'], 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $def_date)) {
    json_response(['ok' => false, 'error' => 'archive_date Y-m-d'], 400);
}

$teamId = null;
if ($def_team !== '') {
    $stmt = $pdo->prepare('SELECT id FROM teams WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => $def_team]);
    $teamId = (int)$stmt->fetchColumn() ?: null;
    if (!$teamId) {
        try {
            $ins = $pdo->prepare('INSERT INTO teams (name) VALUES (:n)');
            $ins->execute([':n' => $def_team]);
            $teamId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {}
    }
}

$insert = $pdo->prepare("
    INSERT INTO archives
      (uid, target_url, target_domain, category, flag_home, flag_mass, flag_redef,
       country_code, isp, asn, is_special, defacer, team_id, ip_address, ip_mass,
       server_type, target_os, poc, reason, archive_date)
    VALUES
      (:uid, :u, :d, :cat, :h, :m, :r, :cc, :isp, :asn, :sp, :df, :t, :ip, :ipm,
       :sv, :os, :poc, :reason, :dt)
");

$results = [];
$ok = 0;
$fail = 0;

foreach ($urls as $i => $item) {
    if (is_string($item)) {
        $url     = clean_url(normalize_url($item));
        $defacer = $def_defacer;
        $poc     = $def_poc;
        $reason  = $def_reason;
        $ipAddr  = null;
        $isHome  = null;
        $isMass  = 0;
        $isRedef = 0;
        $cc      = null;
        $isp     = null;
        $asn     = null;
        $server  = $def_server;
        $targetOs = $def_os;
    } elseif (is_array($item)) {
        $url     = clean_url(normalize_url($item['url'] ?? ''));
        $defacer = clean_input($item['defacer'] ?? $def_defacer, 128);
        $poc     = isset($item['poc']) && $item['poc'] !== ''
            ? max(1, min(31, (int)$item['poc'])) : $def_poc;
        $reason  = isset($item['reason']) && $item['reason'] !== ''
            ? max(1, min(7, (int)$item['reason'])) : $def_reason;
        $ipAddr  = clean_ip($item['ip'] ?? null);
        $isHome  = isset($item['flag_home']) ? (int)$item['flag_home'] : null;
        $isMass  = !empty($item['flag_mass']) ? 1 : 0;
        $isRedef = !empty($item['flag_redef']) ? 1 : 0;
        $cc      = null;
        $isp     = null;
        $asn     = null;
        $server  = clean_input($item['server_type'] ?? $def_server, 128);
        $targetOs = clean_input($item['target_os'] ?? $def_os, 64);
    } else {
        $results[] = ['index' => $i, 'ok' => false, 'error' => 'item tidak valid'];
        $fail++;
        continue;
    }

    if ($url === null) {
        $results[] = ['index' => $i, 'ok' => false, 'error' => 'URL tidak valid'];
        $fail++;
        continue;
    }
    if ($defacer === '') $defacer = $def_defacer;

    $domain = domain_from_url($url);

    // resolve IP kalau nggak ada
    if (!$ipAddr) {
        $resolved = @gethostbyname($domain);
        if ($resolved !== $domain && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ipAddr = $resolved;
        }
    }

    // geoip per item
    if ($ipAddr) {
        $geo = geoip_full($ipAddr);
        $cc  = $geo['cc'];
        $isp = $geo['isp'];
        $asn = $geo['asn'];
    }

    $category  = classify_domain($domain);
    $isSpecial = ($category === 'special') ? 1 : 0;
    if ($isHome === null) $isHome = is_homepage($url) ? 1 : 0;

    $uid = generate_uid($pdo);

    try {
        $insert->execute([
            ':uid'    => $uid,
            ':u'      => $url,
            ':d'      => $domain,
            ':cat'    => $category,
            ':h'      => $isHome ? 1 : 0,
            ':m'      => $isMass,
            ':r'      => $isRedef,
            ':cc'     => $cc,
            ':isp'    => $isp,
            ':asn'    => $asn,
            ':sp'     => $isSpecial,
            ':df'     => $defacer,
            ':t'      => $teamId,
            ':ip'     => $ipAddr,
            ':ipm'    => null,
            ':sv'     => $server ?: null,
            ':os'     => $targetOs ?: null,
            ':poc'    => $poc,
            ':reason' => $reason,
            ':dt'     => $def_date,
        ]);
        $id = (int)$pdo->lastInsertId();
        $results[] = ['index' => $i, 'id' => $id, 'uid' => $uid, 'url' => $url, 'ok' => true];
        $ok++;
    } catch (PDOException $e) {
        $results[] = ['index' => $i, 'url' => $url, 'ok' => false, 'error' => 'db error'];
        $fail++;
    }
}

json_response([
    'ok'      => true,
    'summary' => ['success' => $ok, 'failed' => $fail, 'total' => count($urls)],
    'results' => $results,
]);