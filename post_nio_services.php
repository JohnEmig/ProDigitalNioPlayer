<?php
declare(strict_types=1);

// Bootstrap mínimo: DB::, sessão e helpers, sem renderizar layout/HTML
require_once __DIR__ . '/includes/config.php';

function require_csrf_compat(): void {
  if (function_exists('require_csrf')) { require_csrf(); return; }
  $tok = $_POST['csrf_token'] ?? '';
  if (!is_string($tok) || $tok === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $tok)) {
    http_response_code(400);
    echo "Invalid CSRF token";
    exit;
  }
}
function back(): void {
  header('Location: ./nio_services.php');
  exit;
}
// Unifica canal de mensagens com o cabeçalho (usa flash('info', ...))
function set_toast(string $msg, string $type='info'): void {
  // ignoramos $type para manter compatibilidade visual atual
  if (function_exists('flash')) { flash('info', $msg); return; }
  $_SESSION['flash']['info'] = $msg;
}

/* ---------- router ---------- */
$action = (string)($_POST['action'] ?? '');

try {
  if ($action === 'post_settings') {
    require_csrf_compat();
    $app_mode       = in_array(($_POST['app_mode'] ?? 'Xtream'), ['Xtream','Activate'], true) ? $_POST['app_mode'] : 'Xtream';
    $sports         = (string)($_POST['sports'] ?? '');
    $logo_url       = trim((string)($_POST['logo_url'] ?? ''));
    $background_url = trim((string)($_POST['background_url'] ?? ''));
    $privacy_policy = trim((string)($_POST['privacy_policy'] ?? 'https://pastebin.com/raw/JiimGEjk'));

    $pairs = [
      ['app_mode', $app_mode],
      ['sports', $sports],
      ['logo_url', $logo_url],
      ['background_url', $background_url],
      ['privacy_policy', $privacy_policy],
    ];
    foreach ($pairs as [$k,$v]) {
      DB::exec("INSERT INTO nio_settings (k,v,created_at,updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(k) DO UPDATE SET v=excluded.v, updated_at=CURRENT_TIMESTAMP", [$k, $v]);
    }
    set_toast('Configurações salvas', 'success');
    back();
  }

  if ($action === 'post_service') {
    require_csrf_compat();
    $name = trim((string)($_POST['name'] ?? ''));
    $url  = trim((string)($_POST['url']  ?? ''));
    if ($name === '') { set_toast('Nome do serviço é obrigatório','danger'); back(); }
    DB::exec('INSERT INTO nio_services (name,url,status,created_at,updated_at) VALUES (?,?,?,?,?)',
             [$name, $url, 'active', gmdate('c'), gmdate('c')]);
    set_toast('Serviço adicionado','success');
    back();
  }

  if ($action === 'delete_service') {
    require_csrf_compat();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      DB::exec('DELETE FROM nio_services WHERE id=?', [$id]); // active_codes.service_id is ON DELETE SET NULL
      set_toast('Serviço excluído','success');
    } else {
      set_toast('ID de serviço inválido','danger');
    }
    back();
  }

  if ($action === 'post_activecode') {
    require_csrf_compat();
    $code = trim((string)($_POST['code'] ?? ''));
    $service_id = (int)($_POST['service_id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if ($code === '' || !ctype_digit($code)) { set_toast('O código ativo deve ser numérico','danger'); back(); }
    if ($service_id <= 0) { set_toast('Selecione um serviço','danger'); back(); }

    try {
      DB::exec('INSERT INTO nio_active_codes (code,service_id,username,password,status,created_at,updated_at)
                VALUES (?, ?, ?, ?, "active", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
               [$code, $service_id, $username, $password]);
      set_toast('Código do cliente adicionado','success');
    } catch (Throwable $e) {
      // unique(code) conflict or other DB error
      set_toast('Não foi possível adicionar o código: ' . $e->getMessage(),'danger');
    }
    back();
  }

  if ($action === 'delete_activecode') {
    require_csrf_compat();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      DB::exec('DELETE FROM nio_active_codes WHERE id=?', [$id]);
      set_toast('Código excluído','success');
    } else {
      set_toast('ID de código inválido','danger');
    }
    back();
  }

  // unknown
  set_toast('Ação desconhecida','danger');
  back();

} catch (Throwable $e) {
  set_toast('Erro: '.$e->getMessage(),'danger');
  back();
}
