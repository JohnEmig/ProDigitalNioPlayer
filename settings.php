<?php
require_once __DIR__ . '/includes/header.php';
$error=null; $ok=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  $new_user=trim((string)($_POST['username']??''));
  $new_pass=(string)($_POST['password']??''); $new_pass2=(string)($_POST['password2']??'');
  if($new_user===''||$new_pass===''||$new_pass2===''){ $error='Todos os campos são obrigatórios.'; }
  elseif($new_pass!==$new_pass2){ $error='As senhas não conferem.'; }
  elseif(strlen($new_pass)<8){ $error='A senha deve ter pelo menos 8 caracteres.'; }
  else{
    $hash=password_hash($new_pass,PASSWORD_BCRYPT);
    try{ DB::exec('UPDATE users SET username=?, password_hash=?, must_change=0 WHERE id=?',[$new_user,$hash,(int)$user['id']]); $ok='Credenciais atualizadas.'; }
    catch(\PDOException $e){ $error='Falha ao atualizar: '.$e->getMessage(); }
  }
}
?><h1 class="text-2xl font-bold mb-4">Configurações</h1>
<?php if($error):?><div class="alert alert-bad mb-4"><?php echo h($error); ?></div><?php endif; ?>
<?php if($ok):?><div class="alert alert-good mb-4"><?php echo h($ok); ?></div><?php endif; ?>
<form method="post" class="surface p-4 max-w-2xl"><?php echo csrf_input(); ?>
<label>Novo usuário</label><input name="username" value="<?php echo h($user['username']); ?>" required>
<label class="mt-2">Nova senha</label><input name="password" type="password" required>
<label class="mt-2">Confirmar nova senha</label><input name="password2" type="password" required>
<div class="mt-2"><button class="btn">Salvar</button></div></form>

<?php if ((int)($user['must_change'] ?? 0) === 1): ?>
<div class="modal-backdrop show" id="mustChange">
  <div class="modal">
    <header><div>Ação necessária</div><a href="#" data-modal-close="mustChange">×</a></header>
    <div class="content">
      <div class="alert alert-info">Você deve alterar as credenciais padrão do administrador antes de continuar usando o painel.</div>
    </div>
    <footer><span class="text-subtle text-sm">Defina uma senha forte e salve.</span></footer>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
