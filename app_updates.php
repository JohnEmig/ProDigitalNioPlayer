<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

// Handle upload/create
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $versionCode = (int)($_POST['version_code'] ?? 0);
  $versionName = trim((string)($_POST['version_name'] ?? ''));
  $changelog   = trim((string)($_POST['changelog'] ?? ''));

  if ($versionCode <= 0 || $versionName === '') {
    flash('info','Versão inválida. Informe código e nome.');
    header('Location: app_updates.php'); exit;
  }

  $dir = __DIR__ . '/releases';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    flash('info','Diretório releases não gravável. Verifique permissões.');
    header('Location: app_updates.php'); exit;
  }

  $destFile = $dir . "/app-{$versionCode}.apk";
  if (!isset($_FILES['apk']) || (int)$_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
    $err = (int)($_FILES['apk']['error'] ?? 0);
    $msg = 'Envio do APK falhou.';
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
      $msg = 'O arquivo excede o limite de upload do servidor. Aumente upload_max_filesize e post_max_size.';
    } elseif ($err === UPLOAD_ERR_NO_FILE) {
      $msg = 'Nenhum arquivo foi enviado.';
    }
    flash('info', $msg);
    header('Location: app_updates.php'); exit;
  }
  $tmp = (string)$_FILES['apk']['tmp_name'];
  $name = (string)$_FILES['apk']['name'];
  if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'apk') {
    flash('info','Arquivo inválido. Envie um .apk'); header('Location: app_updates.php'); exit;
  }
  if (!@move_uploaded_file($tmp, $destFile)) {
    flash('info','Não foi possível salvar o APK.'); header('Location: app_updates.php'); exit;
  }

  // Build absolute URL
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $url    = $scheme . '://' . $host . '/releases/' . basename($destFile);

  DB::exec('INSERT INTO app_releases (version_code,version_name,url,changelog,created_at) VALUES (?,?,?,?,?)',
           [$versionCode,$versionName,$url,$changelog,gmdate('c')]);

  flash('info','Nova versão publicada com sucesso.');
  header('Location: app_updates.php'); exit;
}

// Data
// Forçando exibição da versão 5 como mais recente
$latest = [
    'version_code' => 5,
    'version_name' => '5.0.0',
    'url' => 'http://31.97.92.232/nioplayer/releases/nio_player_v5.apk',
    'changelog' => 'Nova versão com melhorias e correções',
    'created_at' => date('Y-m-d H:i:s')
];
$rows = [$latest];
// Leitura/atualização simples de metadados da chave de assinatura (sem armazenar segredos)
if (isset($_POST['save_keymeta'])) {
  require_csrf();
  $name  = trim((string)($_POST['signing_key_name'] ?? ''));
  $sha   = trim((string)($_POST['signing_key_sha256'] ?? ''));
  $notes = trim((string)($_POST['signing_key_notes'] ?? ''));
  foreach ([['signing_key_name',$name],['signing_key_sha256',$sha],['signing_key_notes',$notes]] as [$k,$v]) {
    DB::exec("INSERT INTO nio_settings (k,v,created_at,updated_at) VALUES (?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
              ON CONFLICT(k) DO UPDATE SET v=excluded.v, updated_at=CURRENT_TIMESTAMP", [$k,$v]);
  }
  flash('info','Metadados da chave atualizados.');
  header('Location: app_updates.php'); exit;
}
$key_name  = (string)(DB::one("SELECT v FROM nio_settings WHERE k='signing_key_name'")['v'] ?? '');
$key_sha   = (string)(DB::one("SELECT v FROM nio_settings WHERE k='signing_key_sha256'")['v'] ?? '');
$key_notes = (string)(DB::one("SELECT v FROM nio_settings WHERE k='signing_key_notes'")['v'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Atualizações do Aplicativo</h1>

<div class="grid md:grid-cols-2 gap-6">
  <form method="post" enctype="multipart/form-data" class="surface p-4 rounded">
    <?php echo csrf_input(); ?>
    <label>Código da versão (versionCode)</label>
    <input name="version_code" type="number" min="1" required>
    <label class="mt-2">Nome da versão (versionName)</label>
    <input name="version_name" placeholder="1.2.3" required>
    <label class="mt-2">APK</label>
    <input name="apk" type="file" accept=".apk" required>
    <label class="mt-2">Novidades (opcional)</label>
    <textarea name="changelog" rows="3"></textarea>
    <div class="mt-3"><button class="btn">Publicar atualização</button></div>
  </form>

  <div class="surface p-4 rounded">
    <div class="mb-2 font-semibold">Última versão</div>
    <?php if ($latest): ?>
      <div>versionCode: <strong><?php echo (int)$latest['version_code']; ?></strong></div>
      <div>versionName: <strong><?php echo h((string)$latest['version_name']); ?></strong></div>
      <div class="mt-1"><a class="btn" href="<?php echo h((string)$latest['url']); ?>" target="_blank" rel="noopener">Baixar APK</a></div>
      <?php if (trim((string)$latest['changelog'])!==''): ?>
        <div class="mt-2 text-sm">Notas: <?php echo nl2br(h((string)$latest['changelog'])); ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-sm text-subtle">Nenhuma versão publicada ainda.</div>
    <?php endif; ?>
  </div>

  <form method="post" class="surface p-4 rounded">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="save_keymeta" value="1">
    <div class="mb-2 font-semibold">Chave de Assinatura (metadados)</div>
    <label>Nome/Identificador</label>
    <input name="signing_key_name" value="<?php echo h($key_name); ?>" placeholder="ex.: release-2025" >
    <label class="mt-2">SHA-256 do certificado</label>
    <input name="signing_key_sha256" value="<?php echo h($key_sha); ?>" placeholder="AA:BB:...">
    <label class="mt-2">Anotações</label>
    <textarea name="signing_key_notes" rows="3"><?php echo h($key_notes); ?></textarea>
    <div class="mt-3"><button class="btn">Salvar metadados</button></div>
    <p class="small text-subtle mt-2">Observação: por segurança, não armazenamos senhas nem o arquivo .keystore aqui. Guarde-os em local seguro. Use estes campos apenas para referência.</p>
  </form>
</div>

<div class="surface p-2 rounded mt-6">
  <table class="table">
    <thead><tr><th>ID</th><th>versionCode</th><th>versionName</th><th>Data</th><th>APK</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><?php echo (int)$r['version_code']; ?></td>
        <td><?php echo h((string)$r['version_name']); ?></td>
        <td class="small"><?php echo h((string)$r['created_at']); ?></td>
        <td><a href="<?php echo h((string)$r['url']); ?>" target="_blank" rel="noopener">arquivo</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!count($rows)): ?><div class="text-sm text-subtle p-2">Sem registros.</div><?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


