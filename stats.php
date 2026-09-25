<?php
// stats.php — Hacked Website Statistics
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/country_names.php';

$s = $pdo->query("
    SELECT
      SUM(DATE(created_at) = CURDATE())                    AS today,
      SUM(DATE(created_at) = CURDATE() - INTERVAL 1 DAY)   AS yesterday,
      SUM(created_at >= CURDATE() - INTERVAL 7 DAY)        AS this_week,
      COUNT(DISTINCT defacer)                              AS attackers,
      COUNT(DISTINCT team_id)                              AS teams,
      SUM(flag_home = 1)                                   AS home_deface,
      SUM(category = 'special')                            AS special,
      SUM(category = 'onhold')                             AS onhold,
      SUM(category = 'archive')                            AS archive,
      COUNT(*)                                             AS all_deface
    FROM archives
")->fetch();

$webServers = $pdo->query("
    SELECT
      COALESCE(NULLIF(TRIM(server_type), ''), 'unknown') AS server,
      COUNT(*) AS cnt
    FROM archives
    GROUP BY server
    ORDER BY cnt DESC
    LIMIT 15
")->fetchAll();
$totalServer = array_sum(array_column($webServers, 'cnt'));

$countries = $pdo->query("
    SELECT
      COALESCE(NULLIF(country_code, ''), '') AS cc,
      COUNT(*) AS cnt
    FROM archives
    GROUP BY cc
    ORDER BY cnt DESC
    LIMIT 100
")->fetchAll();
$totalCountry = array_sum(array_column($countries, 'cnt'));

$title = 'Stats';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-head"><h1>Hacked Website Statistics</h1></div>
  <div class="panel-body">

    <div class="statgrid">
      <div class="statbox">Deface today : <strong><?= number_format((int)$s['today']) ?></strong></div>
      <div class="statbox">Deface yesterday : <strong><?= number_format((int)$s['yesterday']) ?></strong></div>
      <div class="statbox">Deface this week : <strong><?= number_format((int)$s['this_week']) ?></strong></div>
      <div class="statbox">Attacker : <strong><?= number_format((int)$s['attackers']) ?></strong></div>
      <div class="statbox">Team : <strong><?= number_format((int)$s['teams']) ?></strong></div>
      <div class="statbox">Home Deface : <strong><?= number_format((int)$s['home_deface']) ?></strong></div>
      <div class="statbox">Special Deface : <strong><?= number_format((int)$s['special']) ?></strong></div>
      <div class="statbox">Onhold : <strong><?= number_format((int)$s['onhold']) ?></strong></div>
      <div class="statbox">Archive : <strong><?= number_format((int)$s['archive']) ?></strong></div>
      <div class="statbox">All Deface : <strong><?= number_format((int)$s['all_deface']) ?></strong></div>
    </div>

    <label class="stats-label">Stats Web Server</label>
    <table class="mirror-table table-responsive">
      <thead>
        <tr><th>Web Server</th><th>Percent (%)</th></tr>
      </thead>
      <tbody>
        <?php if (!$webServers): ?>
          <tr><td colspan="2" class="muted" style="text-align:center;padding:16px">Belum ada data server_type.</td></tr>
        <?php endif; ?>
        <?php foreach ($webServers as $w):
          $pct = $totalServer > 0 ? ($w['cnt'] / $totalServer) * 100 : 0;
        ?>
          <tr>
            <td class="stats-cell">
              <p>
                <a href="/search.php?q=<?= urlencode($w['server']) ?>"><?= e($w['server']) ?></a>
                (<?= number_format((int)$w['cnt']) ?>)
              </p>
            </td>
            <td>
              <div class="progress">
                <div class="progress-bar" style="width: <?= number_format($pct, 4, '.', '') ?>%"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <label class="stats-label">Stats Country Attacked</label>
    <table class="mirror-table table-responsive">
      <thead>
        <tr><th>Country</th><th>Percent (%)</th></tr>
      </thead>
      <tbody>
        <?php if (!$countries): ?>
          <tr><td colspan="2" class="muted" style="text-align:center;padding:16px">
            Belum ada data country.
          </td></tr>
        <?php endif; ?>
        <?php foreach ($countries as $c):
          $pct = $totalCountry > 0 ? ($c['cnt'] / $totalCountry) * 100 : 0;
          $cc  = strtolower($c['cc']);
          $name = country_name($cc) ?? '—';
        ?>
          <tr>
            <td class="stats-cell">
              <p>
                <a href="/search.php?q=<?= urlencode($name) ?>"><?= e($name) ?></a>
                (<?= number_format((int)$c['cnt']) ?>)
              </p>
            </td>
            <td>
              <div class="progress">
                <div class="progress-bar" style="width: <?= number_format($pct, 4, '.', '') ?>%"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>