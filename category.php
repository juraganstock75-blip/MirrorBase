<?php
// category.php — template reusable untuk special/archive/onhold
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

if (!isset($CATEGORY) || !in_array($CATEGORY, ['special','archive','onhold'], true)) {
    http_response_code(400);
    exit('Kategori tidak valid.');
}
$PAGE_TITLE = $PAGE_TITLE ?? ucfirst($CATEGORY);

$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));

$cs = $pdo->prepare("
    SELECT
      COUNT(*) AS total,
      SUM(flag_home = 1) AS home_total,
      SUM(is_special = 1) AS special_total
    FROM archives
    WHERE category = :c
");
$cs->execute([':c' => $CATEGORY]);
$cs = $cs->fetch();

$total = (int)$cs['total'];
$pg = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT a.*, t.name AS team_name
    FROM archives a
    LEFT JOIN teams t ON t.id = a.team_id
    WHERE a.category = :c
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':c', $CATEGORY);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = $PAGE_TITLE;
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head">
    <h1>Mirror <?= e($PAGE_TITLE) ?></h1>
  </div>
  <div class="panel-body">
    <div class="table-container">

      <div class="count-statistic">
        <center>
          <?php if ($CATEGORY === 'special'): ?>
            <span>Special Deface: <?= number_format($total) ?></span>&nbsp;&nbsp;&nbsp;
            <span>Homepage Defacements: <?= number_format((int)$cs['home_total']) ?></span>
          <?php else: ?>
            <span>Total Defacements: <?= number_format($total) ?></span>&nbsp;&nbsp;&nbsp;
            <span>Homepage Defacements: <?= number_format((int)$cs['home_total']) ?></span>&nbsp;&nbsp;&nbsp;
            <span>Special Deface: <?= number_format((int)$cs['special_total']) ?></span>
          <?php endif; ?>
        </center>
      </div>

      <table class="mirror-table table-responsive">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Attacker</th>
            <th>Team</th>
            <th class="S">H</th>
            <th class="S">M</th>
            <th class="S">R</th>
            <th class="S">L</th>
            <th class="S gold">★</th>
            <th>Url</th>
            <th>OS</th>
            <th>Mirror</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="11" class="muted" style="text-align:center;padding:20px">
              Belum ada arsip <?= e($PAGE_TITLE) ?>.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="nowrap"><?= e($r['created_at']) ?></td>
              <td>
                <?php if ($r['defacer']): ?>
                  <a href="/search.php?q=<?= urlencode($r['defacer']) ?>"><?= e($r['defacer']) ?></a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($r['team_name']): ?>
                  <a href="/search.php?q=<?= urlencode($r['team_name']) ?>"><?= e($r['team_name']) ?></a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?php if (!empty($r['flag_home'])): ?><span class="flag-h">H</span><?php endif; ?></td>
              <td><?php if (!empty($r['flag_mass'])): ?><span class="flag-m">M</span><?php endif; ?></td>
              <td><?php if (!empty($r['flag_redef'])): ?><span class="flag-r">R</span><?php endif; ?></td>
              <td>
                <?php if (!empty($r['country_code'])): ?>
                  <img class="flagimg" alt="<?= e($r['country_code']) ?>"
                       title="<?= e($r['country_code']) ?>"
                       src="/assets/images/flags/<?= e(strtolower($r['country_code'])) ?>.png">
                <?php endif; ?>
              </td>
              <td><?php if (!empty($r['is_special'])): ?><span class="gold">★</span><?php endif; ?></td>
              <td class="url-cell">
                <?php
                  $path = parse_url($r['target_url'], PHP_URL_PATH) ?? '';
                  $disp = $r['target_domain'] . ($path ? '/' . ltrim($path, '/') : '');
                ?>
                <a href="/mirror.php/id/<?= e($r['uid'] ?? '') ?>">
                  <?= e(mb_strimwidth($disp, 0, 42, '...')) ?>
                </a>
              </td>
              <td class="os-cell">
                <?php if (!empty($r['target_os'])): ?>
                  <?= e($r['target_os']) ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td><a href="/mirror.php/id/<?= e($r['uid'] ?? '') ?>">Detail</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($pg['pages'] > 1): ?>
        <ul class="pagination">
          <?php
          $start = max(1, $pg['current'] - 2);
          $end   = min($pg['pages'], $start + 4);
          if ($end - $start < 4) $start = max(1, $end - 4);
          ?>
          <?php if ($pg['current'] > 1): ?>
            <li><a href="?page=<?= $pg['current'] - 1 ?>">&laquo;</a></li>
          <?php endif; ?>
          <?php if ($start > 1): ?>
            <li><a href="?page=1">1</a></li>
            <?php if ($start > 2): ?><li class="dots">…</li><?php endif; ?>
          <?php endif; ?>
          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $pg['current']): ?>
              <li class="active"><a><?= $i ?></a></li>
            <?php else: ?>
              <li><a href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($end < $pg['pages']): ?>
            <?php if ($end < $pg['pages'] - 1): ?><li class="dots">…</li><?php endif; ?>
            <li><a href="?page=<?= $pg['pages'] ?>"><?= $pg['pages'] ?></a></li>
          <?php endif; ?>
          <?php if ($pg['current'] < $pg['pages']): ?>
            <li><a href="?page=<?= $pg['current'] + 1 ?>">&raquo;</a></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>