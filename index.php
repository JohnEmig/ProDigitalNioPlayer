<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/header.php';

/* ---------------- helpers ---------------- */
function safe_int($row, string $k): int { return (int)($row[$k] ?? 0); }
function fmt_time(?string $s): string {
  $s = trim((string)$s);
  if ($s === '') return '—';
  $t = strtotime($s);
  return $t === false ? $s : date('d/m/Y H:i', $t);
}
function excerpt(string $txt, int $len = 160): string {
  if (mb_strlen($txt) <= $len) return $txt;
  return mb_substr($txt, 0, $len).'…';
}

/* --------------- quick stats --------------- */
$stats = [
  'services'      => safe_int(DB::one('SELECT COUNT(*) c FROM nio_services') ?? [], 'c'),
  'codes'         => safe_int(DB::one('SELECT COUNT(*) c FROM nio_active_codes') ?? [], 'c'),
  'announcements' => safe_int(DB::one('SELECT COUNT(*) c FROM nio_announcements') ?? [], 'c'),
  'notifications' => safe_int(DB::one('SELECT COUNT(*) c FROM nio_notifications') ?? [], 'c'),
];

/* --------------- last N days aggregates --------------- */
$days  = 14;
$utc   = new DateTimeZone('UTC');
$today = new DateTimeImmutable('now', $utc);
$labels = [];
for ($i = $days - 1; $i >= 0; $i--) $labels[] = $today->modify("-{$i} days")->format('Y-m-d');
$since = $labels[0];

$aggCodes = DB::all('SELECT substr(created_at,1,10) d, COUNT(*) c FROM nio_active_codes WHERE date(created_at) >= ? GROUP BY d ORDER BY d', [$since]);
$aggServs = DB::all('SELECT substr(created_at,1,10) d, COUNT(*) c FROM nio_services      WHERE date(created_at) >= ? GROUP BY d ORDER BY d', [$since]);
$aggAnns  = DB::all('SELECT substr(created_at,1,10) d, COUNT(*) c FROM nio_announcements WHERE date(created_at) >= ? GROUP BY d ORDER BY d', [$since]);
$aggNotfs = DB::all('SELECT substr(created_at,1,10) d, COUNT(*) c FROM nio_notifications WHERE date(created_at) >= ? GROUP BY d ORDER BY d', [$since]);

$mapC=[]; foreach($aggCodes as $r) $mapC[$r['d']] = (int)$r['c'];
$mapS=[]; foreach($aggServs as $r) $mapS[$r['d']] = (int)$r['c'];
$mapA=[]; foreach($aggAnns  as $r) $mapA[$r['d']] = (int)$r['c'];
$mapN=[]; foreach($aggNotfs as $r) $mapN[$r['d']] = (int)$r['c'];

$dataCodes = array_map(fn($d)=> (int)($mapC[$d] ?? 0), $labels);
$dataServs = array_map(fn($d)=> (int)($mapS[$d] ?? 0), $labels);
$dataAnns  = array_map(fn($d)=> (int)($mapA[$d] ?? 0), $labels);
$dataNotfs = array_map(fn($d)=> (int)($mapN[$d] ?? 0), $labels);

/* --------------- top services by codes --------------- */
$topServices = DB::all('
  SELECT COALESCE(s.name,"(unset)") as s, COUNT(*) c
  FROM nio_active_codes c
  LEFT JOIN nio_services s ON s.id = c.service_id
  GROUP BY s.name
  ORDER BY c DESC
  LIMIT 5
');
$svcLabels = array_map(fn($t)=>(string)$t['s'], $topServices);
$svcCounts = array_map(fn($t)=>(int)$t['c'], $topServices);

/* --------------- recent items --------------- */
$recentCodes = DB::all('
  SELECT c.id, c.code, c.username, c.created_at, s.name AS service_name
  FROM nio_active_codes c
  LEFT JOIN nio_services s ON s.id = c.service_id
  ORDER BY c.id DESC
  LIMIT 8
');
$recentAnn   = DB::all('SELECT id,title,created_at FROM nio_announcements ORDER BY id DESC LIMIT 6');
$recentNotif = DB::all('SELECT id,title,created_at FROM nio_notifications ORDER BY id DESC LIMIT 6');
?>
<style>
/* layout & cards */
.grid{display:grid;gap:16px}
@media(min-width:900px){.grid-2{grid-template-columns:1fr 1fr}.grid-3{grid-template-columns:repeat(3,1fr)}}
.panel{border:1px solid #374151;border-radius:.75rem;overflow:hidden}
.hdr{padding:.6rem .8rem;border-bottom:1px solid #374151;font-weight:600}
.bd{padding:1rem}
.tile{border:1px solid #374151;border-radius:.65rem;padding:.85rem}
.tile .label{opacity:.75}
.badge{display:inline-block;padding:.05rem .35rem;border:1px solid #2a2f3a;border-radius:.35rem;font-size:.75rem}

/* tables */
.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{border-bottom:1px solid #2a2f3a;padding:.55rem .6rem;text-align:left;vertical-align:top}
.table thead th{background:var(--surface,#111827)}
.small{opacity:.75}
.msg{max-width:520px;white-space:normal}
.img-thumb{max-height:44px;max-width:90px;border-radius:4px}

.chartWrap{padding:1rem}
</style>

<h1 class="text-2xl font-bold mb-4">Painel</h1>

<!-- Top metrics -->
<div class="grid grid-4 grid-2">
  <div class="tile">
    <div class="label">Serviços</div>
    <div class="text-2xl font-bold mt-1"><?= (int)$stats['services'] ?></div>
  </div>
  <div class="tile">
    <div class="label">Códigos de Cliente</div>
    <div class="text-2xl font-bold mt-1"><?= (int)$stats['codes'] ?></div>
  </div>
  <div class="tile">
    <div class="label">Avisos</div>
    <div class="text-2xl font-bold mt-1"><?= (int)$stats['announcements'] ?></div>
  </div>
  <div class="tile">
    <div class="label">Notificações</div>
    <div class="text-2xl font-bold mt-1"><?= (int)$stats['notifications'] ?></div>
  </div>
</div>

<!-- Charts -->
<div class="grid grid-2" style="margin-top:16px">
  <div class="panel">
    <div class="hdr">Novos itens (últimos <?= (int)$days ?> dias)</div>
    <div class="bd chartWrap">
      <canvas id="chartAll" width="640" height="320"></canvas>
    </div>
  </div>
  <div class="panel">
    <div class="hdr">Serviços em Destaque (por códigos)</div>
    <div class="bd chartWrap">
      <?php if ($topServices): ?>
        <canvas id="chartServices" width="640" height="320"></canvas>
      <?php else: ?>
        <div class="small">Ainda não há serviços.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent lists -->
<div class="grid grid-3" style="margin-top:16px">
  <div class="panel">
    <div class="hdr">Códigos Recentes</div>
    <div class="bd">
      <table class="table">
        <thead><tr><th style="width:70px">ID</th><th>Código</th><th>Serviço</th><th>Usuário</th><th>Criado em</th></tr></thead>
        <tbody>
        <?php if ($recentCodes): foreach ($recentCodes as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><code><?= h((string)$r['code']) ?></code></td>
          <td><?= h($r['service_name'] ?? '(não definido)') ?></td>
            <td><?= h($r['username'] ?? '—') ?></td>
          <td class="small"><?= h(fmt_time($r['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5" class="small">Ainda não há códigos.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="hdr">Avisos Recentes</div>
    <div class="bd">
      <table class="table">
        <thead><tr><th style="width:70px">ID</th><th>Título</th><th>Criado em</th></tr></thead>
        <tbody>
        <?php if ($recentAnn): foreach ($recentAnn as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['title']) ?></td>
            <td class="small"><?= h(fmt_time($r['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="3" class="small">Ainda não há avisos.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="hdr">Notificações Recentes</div>
    <div class="bd">
      <table class="table">
        <thead><tr><th style="width:70px">ID</th><th>Título</th><th>Criado em</th></tr></thead>
        <tbody>
        <?php if ($recentNotif): foreach ($recentNotif as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['title']) ?></td>
            <td class="small"><?= h(fmt_time($r['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="3" class="small">Ainda não há notificações.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Chart.js (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>

<script defer>
(function boot(){
  if(!window.Chart) return setTimeout(boot, 20);

  var labels     = <?= json_encode($labels) ?>;
  var dataCodes  = <?= json_encode($dataCodes) ?>;
  var dataServs  = <?= json_encode($dataServs) ?>;
  var dataAnns   = <?= json_encode($dataAnns) ?>;
  var dataNotfs  = <?= json_encode($dataNotfs) ?>;

  var services   = <?= json_encode($svcLabels) ?>;
  var svcCounts  = <?= json_encode($svcCounts) ?>;

  // Multi-series bar
  new Chart(document.getElementById('chartAll').getContext('2d'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: 'Códigos', data: dataCodes, backgroundColor: 'rgba(99, 102, 241, 0.7)' },
        { label: 'Serviços', data: dataServs, backgroundColor: 'rgba(34, 197, 94, 0.7)'  },
        { label: 'Avisos',  data: dataAnns,  backgroundColor: 'rgba(234, 179, 8, 0.7)'  },
        { label: 'Notificações', data: dataNotfs, backgroundColor: 'rgba(239, 68, 68, 0.7)'  }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { stacked: false }
      }
    }
  });

  // Services pie or bar depending on count
  var ctxS = document.getElementById('chartServices');
  if (ctxS && services.length) {
    new Chart(ctxS.getContext('2d'), {
      type: services.length <= 5 ? 'bar' : 'bar',
      data: {
        labels: services,
        datasets: [{ data: svcCounts, backgroundColor: [
          'rgba(99,102,241,0.8)','rgba(34,197,94,0.8)',
          'rgba(234,179,8,0.8)','rgba(239,68,68,0.8)','rgba(59,130,246,0.8)'
        ] }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
