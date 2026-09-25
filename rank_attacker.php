<?php
// rank_attacker.php — Attacker Ranking
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));

$totalAttackers = (int)$pdo->query("
    SELECT COUNT(DISTINCT defacer)
    FROM archives
    WHERE defacer IS NOT NULL AND defacer <> ''
")->fetchColumn();

$pg = paginate($totalAttackers, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT
      a.defacer,
      SUM(a.flag_home = 1)         AS home_deface,
      SUM(a.category = 'special')  AS special_deface,
      SUM(a.category = 'onhold')   AS onhold,
      SUM(a.category = 'archive')  AS archive_total,
      COUNT(a.id)                  AS total
    FROM archives a
    WHERE a.defacer IS NOT NULL AND a.defacer <> ''
    GROUP BY a.defacer
    ORDER BY total DESC, archive_total DESC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Attacker Rank';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head"><h1>Attacker Rank</h1></div>
  <div class="panel-body">
    <div class="table-container">

      <div class="count-statistic">
        <center><span>Total Attacker: <?= number_format($totalAttackers) ?></span></center>
      </div>

      <table class="mirror-table table-responsive">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Attacker</th>
            <th>Home Deface</th>
            <th>Special Deface</th>
            <th>Onhold</th>
            <th>Archive</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:20px">Belum ada data attacker.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= ($pg['offset'] + $i + 1) ?>.</td>
              <td><a href="/search.php?q=<?= urlencode($r['defacer']) ?>"><?= e($r['defacer']) ?></a></td>
              <td><?= number_format((int)$r['home_deface']) ?></td>
              <td><?= number_format((int)$r['special_deface']) ?></td>
              <td><?= number_format((int)$r['onhold']) ?></td>
              <td><strong><?= number_format((int)$r['archive_total']) ?></strong></td>
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
          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $pg['current']): ?>
              <li class="active"><a><?= $i ?></a></li>
            <?php else: ?>
              <li><a href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($pg['current'] < $pg['pages']): ?>
            <li><a href="?page=<?= $pg['current'] + 1 ?>">&raquo;</a></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>