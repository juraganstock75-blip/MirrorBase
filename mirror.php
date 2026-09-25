<?php
// mirror.php — Defacement Details (URL: /mirror.php/id/<7digit>)
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/country_names.php';
require_once __DIR__ . '/includes/geoip.php';

$uid = trim((string)($_GET['id'] ?? ''));
if (!preg_match('/^\d{7}$/', $uid)) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $pdo->prepare("
    SELECT a.*, t.name AS team_name
    FROM archives a
    LEFT JOIN teams t ON t.id = a.team_id
    WHERE a.uid = :uid
    LIMIT 1
");
$stmt->execute([':uid' => $uid]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    exit('Not found');
}

$id = (int)$r['id'];

// auto-fill ISP/ASN/country dari IP defacer kalau kosong
if ((empty($r['isp']) || empty($r['country_code'])) && !empty($r['ip_address'])) {
    $geo = geoip_full($r['ip_address']);
    if ($geo['cc'] || $geo['isp'] || $geo['asn']) {
        $up = $pdo->prepare("
            UPDATE archives
            SET country_code = COALESCE(NULLIF(country_code, ''), :cc),
                isp          = COALESCE(NULLIF(isp, ''),          :isp),
                asn          = COALESCE(NULLIF(asn, ''),          :asn)
            WHERE id = :id
        ");
        $up->execute([
            ':cc'  => $geo['cc'],
            ':isp' => $geo['isp'],
            ':asn' => $geo['asn'],
            ':id'  => $id,
        ]);
        $r['country_code'] = $r['country_code'] ?: $geo['cc'];
        $r['isp']          = $r['isp']          ?: $geo['isp'];
        $r['asn']          = $r['asn']          ?: $geo['asn'];
    }
}

// screenshot
$screenshotSrc = null;
if (!empty($r['screenshot'])) {
    $screenshotSrc = '/uploads/screenshots/' . $r['screenshot'];
} elseif (!empty($r['screenshot_url'])) {
    $screenshotSrc = $r['screenshot_url'];
} else {
    $screenshotSrc = 'https://image.thum.io/get/width/1200/crop/900/noanimate/' . $r['target_url'];
}

$cleanUrl = rtrim($r['target_url'], '/') . '/';
$countryName = country_name($r['country_code']);

$title     = 'Defacement Details of ' . $r['target_domain'];
$meta_desc = $r['target_domain'] . ' hacked. Notified by ' . ($r['defacer'] ?? 'unknown');
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<div class="panel">
  <div class="panel-head">
    <h1 class="mirror-details">
      Defacement Details of
      <span id="url"
            onmouseover="showCopyIcon()"
            onmouseout="hideCopyIcon()"><?= e($cleanUrl) ?>
        <i title="Copy URL" class="fa fa-clipboard copy" id="copy"
           onclick="copyToClipboard()"></i>
      </span>
    </h1>
  </div>

  <div class="panel-body">

    <div class="detail-meta">

      <div class="detail-col">
        <p>Saved on: <strong><?= e($r['created_at']) ?></strong></p>
        <?php if ($r['ip_address']): ?>
          <p>IP: <strong><?= e($r['ip_address']) ?></strong></p>
        <?php endif; ?>
        <?php if ($r['isp']): ?>
          <p>ISP: <strong><?= e($r['isp']) ?></strong></p>
        <?php endif; ?>
        <?php if ($r['asn']): ?>
          <p>ASN: <strong><?= e($r['asn']) ?></strong></p>
        <?php endif; ?>
        <?php if ($r['target_os']): ?>
          <p>OS: <strong><?= e($r['target_os']) ?></strong></p>
        <?php endif; ?>
      </div>

      <div class="detail-col">
        <p>Defacer:
          <strong>
            <?php if ($r['defacer']): ?>
              <a href="/search.php?q=<?= urlencode($r['defacer']) ?>"><?= e($r['defacer']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </strong>
        </p>
        <p>Team:
          <strong>
            <?php if ($r['team_name']): ?>
              <a href="/search.php?q=<?= urlencode($r['team_name']) ?>"><?= e($r['team_name']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </strong>
        </p>
        <?php if ($r['poc']): ?>
          <p>PoC: <strong><?= e(poc_label((int)$r['poc'])) ?></strong></p>
        <?php endif; ?>
        <?php if ($r['reason']): ?>
          <p>Reason: <strong><?= e(reason_label((int)$r['reason'])) ?></strong></p>
        <?php endif; ?>
      </div>

      <div class="detail-col">
        <?php if ($countryName): ?>
          <p>Location: <strong><?= e($countryName) ?></strong></p>
        <?php endif; ?>
        <?php if ($r['server_type']): ?>
          <p>Web Server: <strong><?= e($r['server_type']) ?></strong></p>
        <?php endif; ?>
        <p>Category:
          <span class="catpill <?= e(category_class($r['category'])) ?>">
            <?= e(category_label($r['category'])) ?>
          </span>
        </p>
        <?php if (!empty($r['is_special'])): ?>
          <p>Special: <span class="gold">★</span></p>
        <?php endif; ?>
      </div>

    </div>

    <?php if ($r['mirror_url']): ?>
      <div class="detail-block">
        <p>Mirror URL:
          <a href="<?= e($r['mirror_url']) ?>" rel="noopener nofollow" target="_blank"><?= e($r['mirror_url']) ?></a>
        </p>
      </div>
    <?php endif; ?>

    <div class="detail-view">
      <img src="<?= e($screenshotSrc) ?>"
           alt="Defacement screenshot of <?= e($r['target_domain']) ?>"
           class="shot" loading="lazy"
           onerror="this.onerror=null;this.src='/assets/images/screenshot-fallback.png';">
    </div>

  </div>
</div>

<script>
function showCopyIcon() { document.querySelector('.copy').style.display = 'inline'; }
function hideCopyIcon() { document.querySelector('.copy').style.display = 'none'; }
function copyToClipboard() {
  var copyText = document.getElementById('url');
  var range = document.createRange();
  range.selectNode(copyText);
  window.getSelection().removeAllRanges();
  window.getSelection().addRange(range);
  document.execCommand('copy');
  window.getSelection().removeAllRanges();
  var c = document.getElementById('copy');
  c.classList.remove('fa-clipboard');
  c.classList.add('fa-check');
  setTimeout(function () {
    c.classList.remove('fa-check');
    c.classList.add('fa-clipboard');
  }, 1000);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>