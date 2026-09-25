<?php
// includes/functions.php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function domain_from_url(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?? $url;
    return strtolower(preg_replace('/^www\./', '', $host));
}

function paginate(int $total, int $perPage, int $current): array {
    $pages   = max(1, (int)ceil($total / $perPage));
    $current = max(1, min($current, $pages));
    return [
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
    ];
}

function classify_domain(string $domain): string {
    $d = strtolower($domain);
    if (str_ends_with($d, '.go.id'))  return 'special';
    if (str_ends_with($d, '.ac.id'))  return 'special';
    if (str_ends_with($d, '.gov'))    return 'special';
    if (str_ends_with($d, '.edu'))    return 'special';
    if (str_ends_with($d, '.sch.id')) return 'archive';
    if (str_ends_with($d, '.id'))     return 'archive';
    return 'onhold';
}

function category_label(string $c): string {
    return match ($c) {
        'special' => 'Special',
        'archive' => 'Archive',
        'onhold'  => 'Onhold',
        default   => '—',
    };
}

function category_class(string $c): string {
    return match ($c) {
        'special' => 'cat-special',
        'archive' => 'cat-archive',
        'onhold'  => 'cat-onhold',
        default   => '',
    };
}

function poc_label(?int $v): string {
    static $map = [
        1  => 'known vulnerability (i.e. unpatched system)',
        2  => 'undisclosed (new) vulnerability',
        3  => 'configuration / admin. mistake',
        4  => 'brute force attack',
        5  => 'social engineering',
        6  => 'Web Server intrusion',
        7  => 'Web Server external module intrusion',
        8  => 'Mail Server intrusion',
        9  => 'FTP Server intrusion',
        10 => 'SSH Server intrusion',
        11 => 'Telnet Server intrusion',
        12 => 'RPC Server intrusion',
        13 => 'Shares misconfiguration',
        14 => 'Other Server intrusion',
        15 => 'SQL Injection',
        16 => 'URL Poisoning',
        17 => 'File Inclusion',
        18 => 'Other Web Application bug',
        19 => 'Remote administrative panel access through bruteforcing',
        20 => 'Remote administrative panel access through password guessing',
        21 => 'Remote administrative panel access through social engineering',
        22 => 'Attack against the administrator/user (password stealing/sniffing)',
        23 => 'Access credentials through Man In the Middle attack',
        24 => 'Remote service password guessing',
        25 => 'Remote service password bruteforce',
        26 => 'Rerouting after attacking the Firewall',
        27 => 'Rerouting after attacking the Router',
        28 => 'DNS attack through social engineering',
        29 => 'DNS attack through cache poisoning',
        30 => 'Not available',
        31 => 'Cross-Site Scripting',
    ];
    return $map[$v] ?? '—';
}

function reason_label(?int $v): string {
    static $map = [
        1 => 'Heh...just for fun!',
        2 => 'Revenge against that website',
        3 => 'Political reasons',
        4 => 'As a challenge',
        5 => 'I just want to be the best defacer',
        6 => 'Patriotism',
        7 => 'Not available',
    ];
    return $map[$v] ?? '—';
}

function normalize_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    return $url;
}

function is_homepage(string $url): bool {
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
    return in_array($path, ['', '/', '/index.php', '/index.html', '/index.htm'], true);
}

function generate_uid(PDO $pdo): string {
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $uid = (string)random_int(1000000, 9999999);
        $check = $pdo->prepare('SELECT 1 FROM archives WHERE uid = :u LIMIT 1');
        $check->execute([':u' => $uid]);
        if (!$check->fetchColumn()) {
            return $uid;
        }
    }
    return substr((string)time(), -7);
}