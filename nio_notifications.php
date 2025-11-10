<?php
declare(strict_types=1);
// Carrega apenas bootstrap (sem HTML) para permitir redirects em POST
require_once __DIR__ . '/includes/config.php';

/* -------- helpers -------- */
function csrf_field(): string {
  return function_exists('csrf_input')
    ? csrf_input()
    : '<input type="hidden" name="csrf_token" value="'.h($_SESSION['csrf_token'] ?? '').'">';
}
function fmt_time(?string $s): string {
  $s = trim((string)$s);
  if ($s === '') return '—';
  $t = strtotime($s);
  return $t === false ? $s : date('d/m/Y H:i', $t);
}
function excerpt(string $txt, int $len = 160): string {
  if (mb_strlen($txt) <= $len) return $txt;
  return mb_substr($txt, 0, $len) . '…';
}

/* ----------------- CRUD (no status fields in UI) ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf();
  $a = (string)($_POST['action'] ?? '');

  // Announcements
  if ($a === 'create_announcement') {
    $title = trim((string)($_POST['title'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));
    $img   = trim((string)($_POST['image'] ?? ''));
    if ($title !== '' && $msg !== '') {
      DB::exec(
        'INSERT INTO nio_announcements (title,message,image,created_at,updated_at) VALUES (?,?,?,?,?)',
        [$title,$msg,$img,gmdate('c'),gmdate('c')]
      );
      flash('info','Aviso criado');
    } else {
      flash('info','Obrigatório: Título e Descrição');
    }
    redirect('nio_notifications.php');
  }
  if ($a === 'update_announcement') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));
    $img   = trim((string)($_POST['image'] ?? ''));
    if ($id>0 && $title!=='' && $msg!=='') {
      DB::exec('UPDATE nio_announcements SET title=?, message=?, image=?, updated_at=? WHERE id=?',
               [$title,$msg,$img,gmdate('c'),$id]);
      flash('info','Aviso atualizado');
    } else {
      flash('info','Campos obrigatórios ausentes');
    }
    redirect('nio_notifications.php');
  }
  if ($a === 'delete_announcement') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) { DB::exec('DELETE FROM nio_announcements WHERE id=?',[$id]); flash('info','Aviso excluído'); }
    redirect('nio_notifications.php');
  }

  // Notifications
  if ($a === 'create_notification') {
    $title = trim((string)($_POST['title'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));
    if ($title !== '' && $msg !== '') {
      DB::exec(
        'INSERT INTO nio_notifications (title,message,created_at,updated_at) VALUES (?,?,?,?)',
        [$title,$msg,gmdate('c'),gmdate('c')]
      );
      flash('info','Notificação criada');
    } else {
      flash('info','Obrigatório: Título e Mensagem');
    }
    redirect('nio_notifications.php');
  }
  if ($a === 'update_notification') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));
    if ($id>0 && $title!=='' && $msg!=='') {
      DB::exec('UPDATE nio_notifications SET title=?, message=?, updated_at=? WHERE id=?',
               [$title,$msg,gmdate('c'),$id]);
      flash('info','Notificação atualizada');
    } else {
      flash('info','Campos obrigatórios ausentes');
    }
    redirect('nio_notifications.php');
  }
  if ($a === 'delete_notification') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) { DB::exec('DELETE FROM nio_notifications WHERE id=?',[$id]); flash('info','Notificação excluída'); }
    redirect('nio_notifications.php');
  }
}

/* --------- lists (no paging) --------- */
$annRows   = DB::all('SELECT id,title,message,image,created_at FROM nio_announcements ORDER BY id DESC');
$notifRows = DB::all('SELECT id,title,message,created_at FROM nio_notifications ORDER BY id DESC');

/* edit state (inline forms) */
$editA = null; if (isset($_GET['editA'])) { $eid=(int)$_GET['editA']; $editA=DB::one('SELECT * FROM nio_announcements WHERE id=?',[$eid]); }
$editN = null; if (isset($_GET['editN'])) { $eid=(int)$_GET['editN']; $editN=DB::one('SELECT * FROM nio_notifications WHERE id=?',[$eid]); }
// Somente após tratar POST incluímos o layout para renderizar a página
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* compact, side-by-side layout */
.section{margin:1rem 0}
.twocol{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start}
@media(max-width:900px){.twocol{grid-template-columns:1fr}}

.panel{border:1px solid #374151;border-radius:.75rem}
.hdr{padding:.6rem .8rem;border-bottom:1px solid #374151;font-weight:600}
.body{padding:1rem}

/* inner cards for forms & tables */
.subcard{border:1px solid #2a2f3a;border-radius:.5rem;overflow:hidden}
.subhdr{padding:.5rem .7rem;border-bottom:1px solid #2a2f3a;font-weight:600;opacity:.9}
.subbd{padding:.8rem}

.form-col{display:flex;flex-direction:column;gap:10px}
.form-col label{font-weight:600}
input[type="text"],input[type="url"],textarea,select{width:100%}

.table{width:100%;border-collapse:separate;border-spacing:0}
.table th,.table td{border-bottom:1px solid #2a2f3a;padding:.55rem .6rem;text-align:left;vertical-align:top}
.table thead th{background:var(--surface,#111827);position:sticky;top:0}

.actions{display:flex;gap:.4rem;justify-content:flex-end}
.btn{padding:.45rem .7rem;border-radius:.5rem;border:1px solid #374151;cursor:pointer}
.btn-primary{background:#1f6feb;border-color:#1f6feb;color:#fff}
.btn-danger{background:#ef4444;border-color:#ef4444;color:#fff}
.btn-link{border-color:#374151;background:transparent}
.small{opacity:.75}

/* message clamp */
.msg{max-width:520px;white-space:normal}
.img-thumb{max-height:48px;max-width:96px;border-radius:4px}
</style>

<h1 class="text-2xl font-bold mb-4">Avisos & Notificações</h1>

<!-- ===================== Announcements ===================== -->
<div class="panel section">
  <div class="hdr">Avisos</div>
  <div class="body">
    <div class="twocol">
      <!-- Left: form (add or edit) -->
      <div class="subcard">
        <div class="subhdr"><?php echo $editA ? 'Editar Aviso #'.(int)$editA['id'] : 'Adicionar Aviso'; ?></div>
        <div class="subbd">
          <form method="post" class="form-col">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editA ? 'update_announcement':'create_announcement'; ?>">
            <?php if($editA): ?>
              <input type="hidden" name="id" value="<?php echo (int)$editA['id']; ?>">
            <?php endif; ?>
            <label>Título
              <input type="text" name="title" value="<?php echo $editA ? h($editA['title']) : ''; ?>" required>
            </label>
            <label>Descrição
              <textarea name="message" rows="5" required><?php echo $editA ? h($editA['message']) : ''; ?></textarea>
            </label>
            <label>URL da Imagem
              <input type="url" name="image" value="<?php echo $editA ? h((string)$editA['image']) : ''; ?>" placeholder="https://example.com/banner.png">
            </label>
            <div>
              <button class="btn btn-primary" type="submit"><?php echo $editA ? 'Salvar Alterações':'Criar'; ?></button>
              <?php if($editA): ?><a class="btn btn-link" href="nio_notifications.php">Cancelar</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <!-- Right: table (in its own card) -->
      <div class="subcard">
        <div class="subhdr">Avisos Existentes</div>
        <div class="subbd">
          <table class="table">
            <thead>
              <tr>
                <th style="width:70px">ID</th>
                <th>Título</th>
                <th>Descrição</th>
                <th>Imagem</th>
                <th>Criado em</th>
                <th style="width:180px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($annRows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo h($r['title']); ?></td>
                  <td class="msg" title="<?php echo h($r['message']); ?>"><?php echo h(excerpt((string)$r['message'])); ?></td>
                  <td>
                    <?php $img = trim((string)($r['image']??'')); if ($img!==''): ?>
                      <a href="<?php echo h($img); ?>" target="_blank" rel="noopener">
                        <img class="img-thumb" src="<?php echo h($img); ?>" alt="imagem">
                      </a>
                    <?php else: ?>
                      <span class="small">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?php echo h(fmt_time((string)$r['created_at'])); ?></td>
                  <td class="actions">
                    <a class="btn btn-link" href="nio_notifications.php?editA=<?php echo (int)$r['id']; ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir este aviso?');" style="display:inline">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_announcement">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn btn-danger" type="submit">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!count($annRows)): ?>
                <tr><td colspan="6" class="small">Ainda não há avisos.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ===================== Notifications ===================== -->
<div class="panel section">
  <div class="hdr">Notificações</div>
  <div class="body">
    <div class="twocol">
      <!-- Left: form (add or edit) -->
      <div class="subcard">
        <div class="subhdr"><?php echo $editN ? 'Editar Notificação #'.(int)$editN['id'] : 'Adicionar Notificação'; ?></div>
        <div class="subbd">
          <form method="post" class="form-col">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editN ? 'update_notification':'create_notification'; ?>">
            <?php if($editN): ?>
              <input type="hidden" name="id" value="<?php echo (int)$editN['id']; ?>">
            <?php endif; ?>
            <label>Título
              <input type="text" name="title" value="<?php echo $editN ? h($editN['title']) : ''; ?>" required>
            </label>
            <label>Mensagem
              <textarea name="message" rows="5" required><?php echo $editN ? h($editN['message']) : ''; ?></textarea>
            </label>
            <div>
              <button class="btn btn-primary" type="submit"><?php echo $editN ? 'Salvar Alterações':'Criar'; ?></button>
              <?php if($editN): ?><a class="btn btn-link" href="nio_notifications.php">Cancelar</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <!-- Right: table (in its own card) -->
      <div class="subcard">
        <div class="subhdr">Notificações Existentes</div>
        <div class="subbd">
          <table class="table">
            <thead>
              <tr>
                <th style="width:70px">ID</th>
                <th>Título</th>
                <th>Mensagem</th>
                <th>Criado em</th>
                <th style="width:180px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($notifRows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo h($r['title']); ?></td>
                  <td class="msg" title="<?php echo h($r['message']); ?>"><?php echo h(excerpt((string)$r['message'])); ?></td>
                  <td class="small"><?php echo h(fmt_time((string)$r['created_at'])); ?></td>
                  <td class="actions">
                    <a class="btn btn-link" href="nio_notifications.php?editN=<?php echo (int)$r['id']; ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir esta notificação?');" style="display:inline">
                      <?= csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_notification">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn btn-danger" type="submit">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!count($notifRows)): ?>
                <tr><td colspan="5" class="small">Ainda não há notificações.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
