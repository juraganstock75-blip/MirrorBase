<?php
// search.php — search by domain, defacer, team, ip, country, server, isp
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$q       = trim((string)($_GET['q'] ?? ''));
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = '1';
$params = [];

if ($q !== '') {
    $where .= ' AND (a.target_url LIKE :q1
                OR a.target_domain LIKE :q2
                OR a.defacer LIKE :q3
                OR t.name LIKE :q4
                OR a.ip_address LIKE :q5
                OR a.ip_mass LIKE :q6
                OR a.country_code = :q7
                OR a.server_type LIKE :q8
                OR a.isp LIKE :q9)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
    $params[':q5'] = '%' . $q . '%';
    $params[':q6'] = '%' . $q . '%';
    $params[':q7'] = strtolower($q);
    $params[':q8'] = '%' . $q . '%';
    $params[':q9'] = '%' . $q . '%';
}

$countSql = "SELECT COUNT(*) FROM archives a
             LEFT JOIN teams t ON t.id = a.team_id
             WHERE $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pg = paginate($total, $perPage, $page);

$sql = "SELECT a.*, t.name AS team_name
        FROM archives a
        LEFT JOIN teams t ON t.id = a.team_id
        WHERE $where
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Search: ' . $q;
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head">
    <h1>Hasil pencarian: "<?= e($q) ?>"</h1>
  </div>
  <div class="panel-body">

    <form action="/search.php" method="get" class="searchbar">
      <input type="text" name="q" value="<?= e($q) ?>"
             placeholder="cari domain, attacker, team, ip, country, server..." required>
      <button type="submit">Search</button>
    </form>

    <div class="count-statistic">
      <center><span>Total: <?= number_format($total) ?> hasil</span></center>
    </div>

    <div class="table-container">
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
              Nggak ada hasil untuk "<?= e($q) ?>".
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
    </div>

    <?php if ($pg['pages'] > 1): ?>
      <ul class="pagination">
        <?php
        $start = max(1, $pg['current'] - 2);
        $end   = min($pg['pages'], $start + 4);
        if ($end - $start < 4) $start = max(1, $end - 4);
        ?>
        <?php if ($pg['current'] > 1): ?>
          <li><a href="?q=<?= urlencode($q) ?>&page=<?= $pg['current'] - 1 ?>">&laquo;</a></li>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <?php if ($i === $pg['current']): ?>
            <li class="active"><a><?= $i ?></a></li>
          <?php else: ?>
            <li><a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a></li>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pg['current'] < $pg['pages']): ?>
          <li><a href="?q=<?= urlencode($q) ?>&page=<?= $pg['current'] + 1 ?>">&raquo;</a></li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>

  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>