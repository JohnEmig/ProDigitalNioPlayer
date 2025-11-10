<?php
require_once __DIR__ . '/includes/config.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  $u=trim((string)($_POST['username']??'')); $p=(string)($_POST['password']??'');
  if($u===''||$p===''){ $error='Informe usuário e senha.'; }
  else{
    $user=DB::one('SELECT * FROM users WHERE username=?',[$u]);
    if($user && password_verify($p,$user['password_hash'])){ session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; redirect('index.php'); }
    else $error='Credenciais inválidas.';
  }
}
$ASSETS = assets_local();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar</title><?php foreach ($ASSETS['css'] as $css): ?><link rel="stylesheet" href="<?php echo h($css); ?>"><?php endforeach; ?></head>
<body class="login-shell"><div class="login-card">
<div class="login-title">Acesso do Administrador</div><div class="text-subtle mb-3">Use suas credenciais de administrador</div>
<?php if($error):?><div class="alert alert-bad mb-3"><?php echo h($error); ?></div><?php endif; ?>
<form method="post"><?php echo csrf_input(); ?>
<label>Usuário</label><input name="username" autocomplete="username" required>
<label class="mt-2">Senha</label><input name="password" type="password" autocomplete="current-password" required class="mb-2">
<button class="btn" style="width:100%">Entrar</button>
</form></div><?php foreach ($ASSETS['js'] as $js): ?><script src="<?php echo h($js); ?>"></script><?php endforeach; ?></body></html>
