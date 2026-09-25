<?php
// includes/geoip.php — geoip lookup country, ISP, ASN
declare(strict_types=1);

function geoip_country(?string $ip): ?string {
    $full = geoip_full($ip);
    return $full['cc'];
}

function geoip_full(?string $ip): array {
    $empty = ['cc' => null, 'isp' => null, 'asn' => null];

    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return $empty;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $empty;
    }

    // 1. Cloudflare header
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $cf = strtoupper((string)$_SERVER['HTTP_CF_IPCOUNTRY']);
        if (preg_match('/^[A-Z]{2}$/', $cf) && $cf !== 'XX' && $cf !== 'T1') {
            return ['cc' => strtolower($cf), 'isp' => null, 'asn' => null];
        }
    }

    // 2. cache lokal
    $cacheDir = __DIR__ . '/../storage/geoip_cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . md5($ip) . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400 * 30) {
        $data = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($data)) {
            return [
                'cc'  => !empty($data['cc'])  ? strtolower((string)$data['cc']) : null,
                'isp' => !empty($data['isp']) ? (string)$data['isp'] : null,
                'asn' => !empty($data['asn']) ? (string)$data['asn'] : null,
            ];
        }
    }

    // 3. live lookup via ip-api.com
    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,isp,org,as';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 4,
            'method'  => 'GET',
            'header'  => "User-Agent: banten-exploiter-geoip/1.0\r\n",
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return $empty;

    $d = json_decode($resp, true);
    if (!is_array($d) || ($d['status'] ?? '') !== 'success') {
        return $empty;
    }

    $result = [
        'cc'  => strtolower((string)($d['countryCode'] ?? '')) ?: null,
        'isp' => (string)($d['isp'] ?? $d['org'] ?? '') ?: null,
        'asn' => (string)($d['as'] ?? '') ?: null,
    ];

    @file_put_contents($cacheFile, json_encode($result + ['ts' => time()]));
    return $result;
}

function geoip_lookup_ipapi(string $ip): ?string {
    return geoip_full($ip)['cc'];
}

function geoip_batch(array $ips): array {
    $result = [];
    $toLookup = [];

    $cacheDir = __DIR__ . '/../storage/geoip_cache/';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) continue;

        $cacheFile = $cacheDir . md5($ip) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400 * 30) {
            $data = json_decode((string)file_get_contents($cacheFile), true);
            if (!empty($data['cc'])) {
                $result[$ip] = strtolower((string)$data['cc']);
                continue;
            }
        }
        $toLookup[] = $ip;
    }

    foreach (array_chunk($toLookup, 100) as $chunk) {
        $payload = json_encode(array_map(
            fn($ip) => ['query' => $ip, 'fields' => 'status,countryCode,query'],
            $chunk
        ));
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nUser-Agent: banten-exploiter-geoip/1.0\r\n",
                'content' => $payload,
            ],
        ]);
        $resp = @file_get_contents('http://ip-api.com/batch', false, $ctx);
        if ($resp === false) continue;

        $rows = json_decode($resp, true);
        if (!is_array($rows)) continue;

        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'success') continue;
            $ip = (string)($row['query'] ?? '');
            $cc = strtolower((string)($row['countryCode'] ?? ''));
            if (!preg_match('/^[a-z]{2}$/', $cc)) continue;

            $result[$ip] = $cc;
            $cacheFile = $cacheDir . md5($ip) . '.json';
            @file_put_contents($cacheFile, json_encode(['cc' => $cc, 'ts' => time()]));
        }
        usleep(400_000);
    }

    return $result;
}