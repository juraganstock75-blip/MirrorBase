<?php
// index.php — landing page
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$stats = $pdo->query("
    SELECT
      COUNT(DISTINCT defacer)                                  AS total_attacker,
      COUNT(DISTINCT team_id)                                  AS total_team,
      SUM(category = 'archive')                                AS total_archive,
      SUM(category = 'special')                                AS total_special,
      SUM(category = 'onhold')                                 AS total_onhold,
      COUNT(*)                                                 AS total_all,
      SUM(DATE(created_at) = CURDATE())                        AS today,
      SUM(DATE(created_at) = CURDATE() - INTERVAL 1 DAY)       AS yesterday
    FROM archives
")->fetch();

$topDefacer = $pdo->query("
    SELECT defacer,
           SUM(category = 'archive') AS archive_cnt,
           SUM(category = 'special') AS special_cnt,
           COUNT(*)                  AS total
    FROM archives
    WHERE defacer IS NOT NULL AND defacer <> ''
    GROUP BY defacer
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

$topTeam = $pdo->query("
    SELECT t.id, t.name,
           SUM(a.category = 'archive') AS archive_cnt,
           SUM(a.category = 'special') AS special_cnt,
           COUNT(a.id)                 AS total
    FROM teams t
    LEFT JOIN archives a ON a.team_id = t.id
    GROUP BY t.id, t.name
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

function recentByCategory(PDO $pdo, string $cat, int $limit = 15): array {
    $stmt = $pdo->prepare("
        SELECT a.*, t.name AS team_name
        FROM archives a
        LEFT JOIN teams t ON t.id = a.team_id
        WHERE a.category = :cat
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':cat', $cat);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

$recentSpecial = recentByCategory($pdo, 'special');
$recentArchive = recentByCategory($pdo, 'archive');
$recentOnhold  = recentByCategory($pdo, 'onhold');

$title     = 'Global Cyber Vandalism Mirror Database';
$meta_desc = 'Global Cyber Vandalism Mirror Database';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head"><h2>Statistic</h2></div>
  <div class="panel-body">
    <div class="table-container">
      <table class="mirror-table">
        <thead>
          <tr>
            <th>Attacker</th><th>Team</th><th>Archive</th>
            <th>Special</th><th>Onhold</th><th>Total</th>
            <th>Today</th><th>Yesterday</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= number_format((int)$stats['total_attacker']) ?></td>
            <td><?= number_format((int)$stats['total_team']) ?></td>
            <td><?= number_format((int)$stats['total_archive']) ?></td>
            <td><?= number_format((int)$stats['total_special']) ?></td>
            <td><?= number_format((int)$stats['total_onhold']) ?></td>
            <td><strong><?= number_format((int)$stats['total_all']) ?></strong></td>
            <td><?= number_format((int)$stats['today']) ?></td>
            <td><?= number_format((int)$stats['yesterday']) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Top 10 Defacer</h2></div>
  <div class="panel-body">
    <div class="table-container">
      <table class="mirror-table">
        <thead>
          <tr><th>Rank</th><th>Attacker</th><th>Archive</th><th>Special</th><th>Total</th></tr>
        </thead>
        <tbody>
          <?php if (!$topDefacer): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:16px">Belum ada data.</td></tr>
          <?php endif; ?>
          <?php foreach ($topDefacer as $i => $d): ?>
            <tr>
              <td><?= $i + 1 ?>.</td>
              <td><a href="/search.php?q=<?= urlencode($d['defacer']) ?>"><?= e($d['defacer']) ?></a></td>
              <td><?= number_format((int)$d['archive_cnt']) ?></td>
              <td><?= number_format((int)$d['special_cnt']) ?></td>
              <td><strong><?= number_format((int)$d['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Top 10 Team</h2></div>
  <div class="panel-body">
    <div class="table-container">
      <table class="mirror-table">
        <thead>
          <tr><th>Rank</th><th>Team</th><th>Archive</th><th>Special</th><th>Total</th></tr>
        </thead>
        <tbody>
          <?php if (!$topTeam): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:16px">Belum ada data.</td></tr>
          <?php endif; ?>
          <?php foreach ($topTeam as $i => $t): ?>
            <tr>
              <td><?= $i + 1 ?>.</td>
              <td><a href="/search.php?q=<?= urlencode($t['name']) ?>"><?= e($t['name']) ?></a></td>
              <td><?= number_format((int)$t['archive_cnt']) ?></td>
              <td><?= number_format((int)$t['special_cnt']) ?></td>
              <td><strong><?= number_format((int)$t['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$sections = [
    ['title' => 'Recent Special', 'rows' => $recentSpecial, 'cat' => 'special'],
    ['title' => 'Recent Archive', 'rows' => $recentArchive, 'cat' => 'archive'],
    ['title' => 'Recent Onhold',  'rows' => $recentOnhold,  'cat' => 'onhold'],
];
foreach ($sections as $sec):
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= e($sec['title']) ?></h2>
    <a class="panel-more" href="/<?= e($sec['cat']) ?>.php">view all →</a>
  </div>
  <div class="panel-body">
    <div class="table-container">
      <table class="mirror-table table-responsive">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Attacker</th>
            <th>Team</th>
            <th class="S" title="Homepage Defacement">H</th>
            <th class="S" title="Mass Defacement">M</th>
            <th class="S" title="Redefacement">R</th>
            <th class="S" title="IP Address Location">L</th>
            <th class="S gold" title="Special Defacement">★</th>
            <th>Url</th>
            <th>OS</th>
            <th>Mirror</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$sec['rows']): ?>
            <tr><td colspan="11" class="muted" style="text-align:center;padding:16px">Belum ada data.</td></tr>
          <?php endif; ?>
          <?php foreach ($sec['rows'] as $r): ?>
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
              <td><?php if (!empty($r['flag_home'])): ?><span class="flag-h" title="Homepage">H</span><?php endif; ?></td>
              <td><?php if (!empty($r['flag_mass'])): ?><a href="/search.php?q=<?= urlencode((string)$r['ip_mass']) ?>" class="flag-m" title="Mass">M</a><?php endif; ?></td>
              <td><?php if (!empty($r['flag_redef'])): ?><span class="flag-r" title="Redefacement">R</span><?php endif; ?></td>
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
                <a href="/mirror.php/id/<?= e($r['uid'] ?? '') ?>" target="_blank">
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
              <td>
                <a href="/mirror.php/id/<?= e($r['uid'] ?? '') ?>">Detail</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>