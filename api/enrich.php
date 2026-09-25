<?php
// tools/enrich.php — auto-fill IP, ISP, ASN, OS untuk data lama
// HAPUS setelah selesai!
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geoip.php';

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);

$limit = (int)($_GET['limit'] ?? 200);
$limit = max(1, min(2000, $limit));

$rows = $pdo->query("
    SELECT id, target_domain, target_url, ip_address, isp, asn, target_os, server_type
    FROM archives
    WHERE (ip_address IS NULL OR ip_address = ''
        OR isp IS NULL OR isp = ''
        OR target_os IS NULL OR target_os = '')
    ORDER BY id ASC
    LIMIT {$limit}
")->fetchAll();

echo "Total rows perlu enrich: " . count($rows) . "\n\n";

$upd = $pdo->prepare("
    UPDATE archives
    SET ip_address = COALESCE(NULLIF(ip_address, ''), :ip),
        country_code = COALESCE(NULLIF(country_code, ''), :cc),
        isp = COALESCE(NULLIF(isp, ''), :isp),
        asn = COALESCE(NULLIF(asn, ''), :asn),
        target_os = COALESCE(NULLIF(target_os, ''), :os),
        server_type = COALESCE(NULLIF(server_type, ''), :sv)
    WHERE id = :id
");

$ok = 0;
$fail = 0;

foreach ($rows as $r) {
    $domain = $r['target_domain'];
    $url    = $r['target_url'];

    $ip = @gethostbyname($domain);
    if ($ip === $domain || !filter_var($ip, FILTER_VALIDATE_IP)) {
        echo "✗ id={$r['id']} {$domain} → DNS gagal\n";
        $fail++;
        flush();
        continue;
    }

    $geo = geoip_full($ip);

    $targetOs = null;
    $serverHeader = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
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

        foreach (explode("\n", $headerStr) as $line) {
            if (stripos($line, 'Server:') === 0) {
                $serverHeader = trim(substr($line, 7));
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

    $upd->execute([
        ':ip'  => $ip,
        ':cc'  => $geo['cc'],
        ':isp' => $geo['isp'],
        ':asn' => $geo['asn'],
        ':os'  => $targetOs,
        ':sv'  => $serverHeader,
        ':id'  => $r['id'],
    ]);

    echo "✓ id={$r['id']} {$domain} → IP={$ip}, CC=" . ($geo['cc'] ?? '?')
       . ", ISP=" . ($geo['isp'] ?? '?')
       . ", OS=" . ($targetOs ?? '?') . "\n";
    $ok++;
    flush();
    usleep(250_000);
}

echo "\n=== DONE ===\n";
echo "OK: {$ok}, Failed: {$fail}\n";
echo "HAPUS file ini sekarang!\n";