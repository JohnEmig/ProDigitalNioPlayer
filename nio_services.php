<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/header.php';

/* ------------ helpers ------------ */
function nio_get_setting(string $k, $default = null): string {
  $row = DB::one('SELECT v FROM nio_settings WHERE k=? LIMIT 1', [$k]);
  if (!$row || $row['v'] === null) return (string)($default ?? '');
  return (string)$row['v'];
}
function csrf_field(): string {
  return function_exists('csrf_input')
    ? csrf_input()
    : '<input type="hidden" name="csrf_token" value="'.h($_SESSION['csrf_token'] ?? '').'">';
}

/* ------------ load settings ------------ */
$app_mode       = nio_get_setting('app_mode','Xtream'); // 'Xtream' or 'Activate'
$logo_url       = nio_get_setting('logo_url','');
$background_url = nio_get_setting('background_url','');
$privacy_policy = nio_get_setting('privacy_policy','https://pastebin.com/raw/JiimGEjk');

/* ------------ lists ------------ */
$services = DB::all("SELECT id, name, url, status FROM nio_services ORDER BY name COLLATE NOCASE ASC");
$codes    = DB::all("SELECT c.id, c.code, c.username, c.password, c.status, s.name AS service_name
                     FROM nio_active_codes c LEFT JOIN nio_services s ON s.id=c.service_id
                     ORDER BY c.id DESC");
?>
<style>
/* carry the same look & feel you used */
.btn-sm{font-size:.85rem;padding:.38rem .65rem;line-height:1;border-radius:.5rem}
.icon-btn{padding:.35rem .5rem;border-radius:.5rem}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.table th,.table td{vertical-align:middle}
.truncate{max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.section{margin-top:1rem;border-top:1px dashed #374151;padding-top:1rem}
.card{border:1px solid #374151;border-radius:.75rem;margin-bottom:1rem}
.card > .hdr{padding:.6rem .8rem;border-bottom:1px solid #374151;font-weight:600;opacity:.9}
.card > .bd{padding:1rem}
.grid{display:grid;gap:12px}
@media(min-width:1024px){ .grid-2{grid-template-columns:1fr 1fr} .grid-3{grid-template-columns:1fr 1fr 1fr}}
.label{display:block;font-weight:600;margin-bottom:.25rem}
.note{display:flex;gap:.6rem;align-items:flex-start;background:#0ea5e920;border:1px solid #0ea5e9;color:#d1ecff;padding:.6rem .75rem;border-radius:.5rem;font-size:.9rem}
.badge{display:inline-block;padding:.1rem .4rem;border-radius:.35rem;font-size:.75rem;opacity:.85}
.badge-ok{background:#1f6feb33;color:#58a6ff}
.badge-no{background:#ef444422;color:#ef4444}
</style>

<h1 class="text-2xl font-bold mb-4">NiO Player — Serviços & Códigos</h1>

<!-- SETTINGS -->
<div class="card">
  <div class="hdr">CONFIGURAÇÕES</div>
  <div class="bd">
    <form method="post" action="post_nio_services.php">
      <?= csrf_field(); ?>
      <input type="hidden" name="action" value="post_settings">
      <div class="grid grid-2">
        <div>
          <label class="label">Tipo de Login</label>
          <select name="app_mode">
            <option value="Xtream"  <?= $app_mode==='Xtream' ? 'selected':''; ?>>Login de Usuário</option>
            <option value="Activate"<?= $app_mode==='Activate' ? 'selected':''; ?>>Código Ativo</option>
          </select>
        </div>
      </div>

      <div class="grid grid-3 section">
        <div>
          <label class="label">URL do Logotipo</label>
          <input type="url" name="logo_url" value="<?= h($logo_url); ?>" placeholder="https://example.com/logo.png">
        </div>
        <div>
          <label class="label">URL do Fundo</label>
          <input type="url" name="background_url" value="<?= h($background_url); ?>" placeholder="https://example.com/background.png">
        </div>
        <div>
          <label class="label">URL da Política de Privacidade</label>
          <input type="url" name="privacy_policy" value="<?= h($privacy_policy); ?>" placeholder="https://pastebin.com/raw/JiimGEjk">
        </div>
      </div>

      <div class="section">
        <button class="btn">Salvar Configurações</button>
      </div>
    </form>
  </div>
</div>

<!-- SERVICES -->
<div class="card">
  <div class="hdr">SERVIÇOS</div>
  <div class="bd">
    <div class="grid grid-2">
      <!-- Add service -->
      <div>
        <h3 class="mb-2 font-semibold">Adicionar Serviço</h3>
        <form method="post" action="post_nio_services.php">
          <?= csrf_field(); ?>
          <input type="hidden" name="action" value="post_service">
          <div>
            <label class="label">Nome</label>
            <input name="name" required placeholder="Informe o nome do serviço">
          </div>
          <div class="section">
            <label class="label">URL</label>
            <input name="url" type="url" placeholder="https://exemplo.com (opcional)">
          </div>
          <div class="section">
            <button class="btn">Adicionar Serviço</button>
          </div>
        </form>
      </div>

      <!-- Service list -->
      <div>
        <h3 class="mb-2 font-semibold">Lista de Serviços</h3>
        <div class="surface p-2 rounded">
          <table class="table">
            <thead>
              <tr>
                <th class="text-center">NOME</th>
                <th class="text-center">URL</th>
                <th class="text-center">AÇÕES</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $s): ?>
                <tr>
                  <td class="text-center"><?= h($s['name']); ?></td>
                  <td class="text-center">
                    <?php if (trim((string)$s['url']) !== ''): ?>
                      <a href="<?= h($s['url']); ?>" target="_blank" rel="noopener"><?= h($s['url']); ?></a>
                    <?php else: ?>
                      <span class="subtle">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <form method="post" action="post_nio_services.php" onsubmit="return confirm('Excluir este serviço?');" style="display:inline">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_service">
                      <input type="hidden" name="id" value="<?= (int)$s['id']; ?>">
                      <button class="btn-rose btn-sm">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!count($services)): ?>
                <tr><td colspan="3" class="text-center subtle">Ainda não há serviços.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($app_mode !== 'Xtream'): ?>
<!-- CODES (only when Activate mode) -->
<div class="card">
  <div class="hdr">LISTA DE CÓDIGOS</div>
  <div class="bd">
    <div class="grid grid-2">
      <!-- Add code -->
      <div>
        <h3 class="mb-2 font-semibold">Adicionar Código</h3>
        <form method="post" action="post_nio_services.php">
          <?= csrf_field(); ?>
          <input type="hidden" name="action" value="post_activecode">
          <div>
            <label class="label">Código Ativo</label>
            <input name="code" pattern="\d+" inputmode="numeric" title="O código ativo deve ser numérico" placeholder="12345678" required>
          </div>
          <div class="section">
            <label class="label">Serviço</label>
            <select name="service_id" required>
              <option value="" disabled selected>Selecione um Serviço</option>
              <?php foreach ($services as $s): if (strtolower((string)$s['status'])!=='active') continue; ?>
                <option value="<?= (int)$s['id']; ?>"><?= h($s['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="grid grid-2 section">
            <div>
              <label class="label">Usuário</label>
              <input name="username" placeholder="Informe o usuário">
            </div>
            <div>
              <label class="label">Senha</label>
              <input name="password" placeholder="Informe a senha">
            </div>
          </div>
          <div class="section">
            <button class="btn">Adicionar Código</button>
          </div>
        </form>
      </div>

      <!-- Code list -->
      <div>
        <h3 class="mb-2 font-semibold">Lista de Códigos</h3>
        <div class="surface p-2 rounded">
          <table class="table">
            <thead>
              <tr>
                <th class="text-center">CÓDIGO</th>
                <th class="text-center">USUÁRIO</th>
                <th class="text-center">SERVIÇO</th>
                <th class="text-center">AÇÕES</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($codes as $c): ?>
                <tr>
                  <td class="text-center"><code><?= h($c['code']); ?></code></td>
                  <td class="text-center"><?= h($c['username'] ?: '—'); ?></td>
                  <td class="text-center"><?= h($c['service_name'] ?: '—'); ?></td>
                  <td class="text-center">
                    <form method="post" action="post_nio_services.php" onsubmit="return confirm('Excluir este código?');" style="display:inline">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_activecode">
                      <input type="hidden" name="id" value="<?= (int)$c['id']; ?>">
                      <button class="btn-rose btn-sm">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!count($codes)): ?>
                <tr><td colspan="4" class="text-center subtle">Ainda não há códigos.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
