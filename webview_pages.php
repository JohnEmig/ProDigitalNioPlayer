<?php
declare(strict_types=1);
// Bootstrap sem HTML para permitir redirects em POST
require_once __DIR__ . '/includes/config.php';

function validate_url(string $u): bool {
  return (bool) filter_var($u, FILTER_VALIDATE_URL);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if (in_array($action, ['create','update','delete','toggle'], true)) require_csrf();

  if ($action === 'create') {
    $title  = trim((string)($_POST['title'] ?? ''));
    $url    = trim((string)($_POST['url'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $notes  = trim((string)($_POST['notes'] ?? ''));

    if ($title === '' || $url === '') {
      flash('info', 'Título e URL são obrigatórios.');
      redirect('webview_pages.php');
    }
    if (!validate_url($url)) {
      flash('info', 'Informe uma URL válida (incluindo http/https).');
      redirect('webview_pages.php');
    }

    try {
      DB::exec(
        'INSERT INTO webview_pages (title,url,active,notes,created_at,updated_at) VALUES (?,?,?,?,?,?)',
        [$title,$url,$active,$notes,gmdate('c'),gmdate('c')]
      );
      flash('info','Página WebView criada');
    } catch (Throwable $e) {
      flash('info','Não foi possível criar a página.');
    }
    redirect('webview_pages.php');
  }

  if ($action === 'update') {
    $id     = (int)($_POST['id'] ?? 0);
    $title  = trim((string)($_POST['title'] ?? ''));
    $url    = trim((string)($_POST['url'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $notes  = trim((string)($_POST['notes'] ?? ''));

    if ($id <= 0 || $title === '' || $url === '') {
      flash('info','Todos os campos são obrigatórios.');
      redirect('webview_pages.php');
    }
    if (!validate_url($url)) {
      flash('info','URL inválida.');
      redirect('webview_pages.php');
    }

    try {
      DB::exec(
        'UPDATE webview_pages SET title=?, url=?, active=?, notes=?, updated_at=? WHERE id=?',
        [$title,$url,$active,$notes,gmdate('c'),$id]
      );
      flash('info','Página WebView atualizada');
    } catch (Throwable $e) {
      flash('info','Não foi possível atualizar a página.');
    }
    redirect('webview_pages.php');
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      DB::exec('DELETE FROM webview_pages WHERE id=?', [$id]);
      flash('info','Página WebView excluída');
    }
    redirect('webview_pages.php');
  }

  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $row = DB::one('SELECT active FROM webview_pages WHERE id=?', [$id]);
      if ($row) {
        $new = ((int)$row['active'] === 1) ? 0 : 1;
        DB::exec('UPDATE webview_pages SET active=?, updated_at=? WHERE id=?', [$new, gmdate('c'), $id]);
      }
    }
    redirect('webview_pages.php');
  }
}

// edit modal
$edit = null;
if (isset($_GET['edit'])) {
  $eid = (int)$_GET['edit'];
  $edit = DB::one('SELECT * FROM webview_pages WHERE id=?', [$eid]);
}

$rows = DB::all('SELECT * FROM webview_pages ORDER BY id DESC');
// A partir daqui, renderização (inclui layout)
require_once __DIR__ . '/includes/header.php';
?>
<h1 class="text-2xl font-bold mb-4">Páginas WebView</h1>

<div class="grid md:grid-cols-2 gap-6">
  <form method="post" class="surface p-4 rounded">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create">

    <label>Título</label>
    <input name="title" required>

    <label class="mt-2">URL</label>
    <input name="url" placeholder="https://example.com/page" required>

    <label class="mt-2">Observações (opcional)</label>
    <textarea name="notes" rows="3"></textarea>

    <div class="mt-2 flex items-center gap-2">
      <label class="cursor-pointer flex items-center gap-2">
        <input type="checkbox" name="active" checked> <span>Ativa</span>
      </label>
    </div>

    <div class="mt-3"><button class="btn">Adicionar Página WebView</button></div>
  </form>

  <div class="surface p-2 rounded">
    <table class="table">
      <thead><tr><th>ID</th><th>Título</th><th>URL</th><th>Ativa</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo h((string)$r['title']); ?></td>
          <td class="truncate max-w-[260px]" title="<?php echo h((string)$r['url']); ?>">
            <?php echo h((string)$r['url']); ?>
          </td>
          <td>
            <form method="post" style="display:inline">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <button class="btn-ghost" title="Alternar ativo">
                <?php echo ((int)$r['active']===1?'Sim':'Não'); ?>
              </button>
            </form>
          </td>
          <td class="text-right">
            <a class="btn-ghost" href="webview_pages.php?edit=<?php echo (int)$r['id']; ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Excluir página?');" style="display:inline">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <button class="btn-rose">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($edit): ?>
<div class="modal-backdrop show" id="modalWvEdit">
  <div class="modal">
    <header><div>Editar Página WebView #<?php echo (int)$edit['id']; ?></div><a href="webview_pages.php" data-modal-close="modalWvEdit">×</a></header>
    <div class="content">
      <form method="post" id="formWvEdit">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>">

        <label>Título</label>
        <input name="title" value="<?php echo h((string)$edit['title']); ?>" required>

        <label class="mt-2">URL</label>
        <input name="url" value="<?php echo h((string)$edit['url']); ?>" required>

        <label class="mt-2">Observações</label>
        <textarea name="notes" rows="3"><?php echo h((string)($edit['notes'] ?? '')); ?></textarea>

        <label class="mt-2 flex items-center gap-2">
          <input type="checkbox" name="active" <?php echo ((int)$edit['active']===1?'checked':''); ?>>
          <span>Ativa</span>
        </label>
      </form>
    </div>
    <footer>
      <a href="webview_pages.php" class="btn-ghost" data-modal-close="modalWvEdit">Cancelar</a>
      <button class="btn" onclick="document.getElementById('formWvEdit').submit()">Salvar Alterações</button>
    </footer>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
