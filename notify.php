<?php
// notify.php — form submission publik
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$mkey = 'rlm_' . md5($ip);
$now = time();
$mbucket = $_SESSION[$mkey] ?? ['count' => 0, 'start' => $now];
if ($now - $mbucket['start'] > 3600) {
    $mbucket = ['count' => 0, 'start' => $now];
}
$massRateLimited = $mbucket['count'] >= 10;
$_SESSION[$mkey] = $mbucket;

if (empty($_SESSION['notify_csrf'])) {
    $_SESSION['notify_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['notify_csrf'];

$summary = null;
if (isset($_GET['ok'], $_GET['fail'])) {
    $summary = [
        'ok'    => (int)$_GET['ok'],
        'fail'  => (int)$_GET['fail'],
        'total' => (int)($_GET['total'] ?? 0),
        'errs'  => isset($_GET['errs']) ? array_filter(explode('||', (string)$_GET['errs'])) : [],
    ];
}
$err = (string)($_GET['err'] ?? '');

$old = [
    'attacker' => (string)($_GET['a'] ?? ''),
    'team'     => (string)($_GET['t'] ?? ''),
    'poc'      => (string)($_GET['p'] ?? ''),
    'reason'   => (string)($_GET['r'] ?? ''),
    'urls'     => (string)($_GET['u'] ?? ''),
];

$title = 'Notify Defacements';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head"><h1>Notify</h1></div>
  <div class="panel-body">

    <div class="rules-box">
      <div class="rules-title">Rules &amp; Disclaimer</div>
      <p class="rules-text">
        All the information contained in this Hacked Website Archive were either collected online
        from public sources or directly notified anonymously to us. This system is neither
        responsible for the reported computer crimes nor it is directly or indirectly involved
        with them.
      </p>
      <ol class="rules-list">
        <li>Add one url in each line in Urls textarea.</li>
        <li>Only sub-domains are allowed from Government (<code>.gov</code>) and Academic (<code>.ac</code>) sites.</li>
        <li>Any Kind of Picture Defacements will not accepted — ex: <code>http://site.com/example.png</code></li>
        <li>Defacement page will not be accepted with <strong>/~</strong></li>
        <li>The name of the attacker must be in the deface script and there must be at least one keyword
            that contains hacking elements (ex: <em>hacked by me</em>). Otherwise it will be declared invalid.</li>
      </ol>
    </div>

    <?php if ($summary): ?>
      <div class="alert alert-<?= $summary['fail'] > 0 ? 'warn' : 'ok' ?>">
        <strong>Hasil submit:</strong>
        <?= $summary['ok'] ?> sukses ·
        <?= $summary['fail'] ?> gagal ·
        total <?= $summary['total'] ?> URL
        <?php if ($summary['errs']): ?>
          <details style="margin-top:8px">
            <summary style="cursor:pointer">lihat detail error (<?= count($summary['errs']) ?>)</summary>
            <ul style="margin:8px 0 0 18px; font-size:13px">
              <?php foreach (array_slice($summary['errs'], 0, 30) as $er): ?>
                <li><?= e($er) ?></li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>
        · <a href="/index.php">lihat di browse</a>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="alert alert-err">✗ <?= e($err) ?></div>
    <?php endif; ?>

    <?php if ($massRateLimited): ?>
      <div class="alert alert-warn">⚠ Batas submit tercapai (10 per jam). Coba lagi nanti.</div>
    <?php else: ?>

    <form action="/notify_save.php" method="post" class="notify-form" id="notifyForm">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">

      <div class="nf-row">
        <label>Attacker:</label>
        <input type="text" name="attacker" required maxlength="128"
               placeholder="Attacker" value="<?= e($old['attacker']) ?>">
      </div>

      <div class="nf-row">
        <label>Team:</label>
        <input type="text" name="team" maxlength="128"
               placeholder="Team (kosongin kalau solo)" value="<?= e($old['team']) ?>">
      </div>

      <div class="nf-row">
        <label>Proof of Concept:</label>
        <select name="poc">
          <option value="">--------SELECT--------</option>
          <?php for ($i = 1; $i <= 31; $i++): ?>
            <option value="<?= $i ?>" <?= $old['poc'] == $i ? 'selected' : '' ?>>
              <?= e(poc_label($i)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="nf-row">
        <label>Reason:</label>
        <select name="reason">
          <option value="">--------SELECT--------</option>
          <?php for ($i = 1; $i <= 7; $i++): ?>
            <option value="<?= $i ?>" <?= $old['reason'] == $i ? 'selected' : '' ?>>
              <?= e(reason_label($i)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="nf-row nf-row-top">
        <label>Urls:</label>
        <textarea name="urls" required placeholder="example.com
http://example.com
http://example.com/hack.php"><?= e($old['urls']) ?></textarea>
      </div>

      <div class="nf-actions">
        <button type="submit" class="btn-primary" id="submitBtn">
          <span>Submit</span>
        </button>
      </div>
    </form>

    <?php endif; ?>

  </div>
</div>

<script>
document.getElementById('notifyForm')?.addEventListener('submit', function () {
  const b = document.getElementById('submitBtn');
  if (!b) return;
  b.disabled = true;
  b.querySelector('span').textContent = 'Loading...';
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>