<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: Final.php');
    exit;
}

$current_user = $_SESSION['user'];
$is_admin     = ($current_user === 'admin');
$host = 'localhost'; $dbname = 'usjr'; $dbuser = 'root'; $dbpass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if (!$is_admin) {
        $chk_adm = $pdo->prepare("SELECT usertype FROM users WHERE username = ?");
        $chk_adm->execute([$current_user]);
        $adm_row = $chk_adm->fetch();
        if ($adm_row && $adm_row['usertype'] === 'Administrator') $is_admin = true;
    }
} catch (PDOException $e) {}


if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: Final.php');
    exit;
}

$action  = $_GET['action'] ?? 'list';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors  = [];
$success = '';
$del_error = '';

function p($k, $fb = '') { return $_POST[$k] ?? $fb; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('form_action') === 'create') {
    $collid        = (int)trim(p('collid'));
    $collfullname  = trim(p('collfullname'));
    $collshortname = trim(p('collshortname'));

    if (!$collid)        $errors['collid']        = 'School ID entry cannot be empty';
    if (!$collfullname)  $errors['collfullname']  = 'School Full Name entry cannot be empty';
    if (!$collshortname) $errors['collshortname'] = 'School Short Name entry cannot be empty';

    if (empty($errors['collid'])) {
        $chk = $pdo->prepare("SELECT collid FROM colleges WHERE collid = ?");
        $chk->execute([$collid]);
        if ($chk->fetch()) $errors['collid'] = 'School ID already exists';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO colleges (collid, collfullname, collshortname) VALUES (?, ?, ?)")
            ->execute([$collid, $collfullname, $collshortname]);
        $success = 'School entry saved successfully!';
        $action  = 'list';
    } else {
        $action = 'create';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('form_action') === 'update') {
    $upd_id        = (int)p('edit_id');
    $collid        = (int)trim(p('collid'));
    $collfullname  = trim(p('collfullname'));
    $collshortname = trim(p('collshortname'));

    if (!$collid)        $errors['collid']        = 'School ID entry cannot be empty';
    if (!$collfullname)  $errors['collfullname']  = 'School Full Name entry cannot be empty';
    if (!$collshortname) $errors['collshortname'] = 'School Short Name entry cannot be empty';

    // duplicate check — only if ID is being changed
    if (!isset($errors['collid']) && $collid !== $upd_id) {
        $chk = $pdo->prepare("SELECT collid FROM colleges WHERE collid = ?");
        $chk->execute([$collid]);
        if ($chk->fetch()) $errors['collid'] = 'School ID already exists';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE colleges SET collid=?, collfullname=?, collshortname=? WHERE collid=?")
                ->execute([$collid, $collfullname, $collshortname, $upd_id]);
            $success = 'School entry updated successfully!';
            $action  = 'list';
        } catch (Throwable $e) {
            $errors['collid'] = 'Cannot update: this School ID is linked to existing departments.';
            $action  = 'edit';
            $edit_id = $upd_id;
        }
    } else {
        $action  = 'edit';
        $edit_id = $upd_id;
    }
}

$del_error = '';
if ($action === 'delete' && $edit_id > 0) {
    $action = 'list';
    try {
        $pdo->exec("DELETE FROM colleges WHERE collid = " . (int)$edit_id);
        $success = 'School entry deleted successfully!';
    } catch (Throwable $e) {
        $del_error = 'Cannot delete: this school has linked departments or programs.';
    }
}

$schools = [];
$total_schools = 0;
$total_pages   = 0;
$per_page = 6;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

if ($action === 'list') {
    $total_schools = (int)$pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
    $total_pages   = (int)ceil($total_schools / $per_page);
    $current_page  = min($current_page, max(1, $total_pages)); 
    $offset        = ($current_page - 1) * $per_page;         
    $schools = $pdo->prepare("SELECT * FROM colleges ORDER BY collid LIMIT ? OFFSET ?");
    $schools->bindValue(1, $per_page, PDO::PARAM_INT);
    $schools->bindValue(2, $offset,   PDO::PARAM_INT);
    $schools->execute();
    $schools = $schools->fetchAll();
}

$edit_school = null;
if ($action === 'edit' && $edit_id > 0) {
    $st = $pdo->prepare("SELECT * FROM colleges WHERE collid = ?");
    $st->execute([$edit_id]);
    $edit_school = $st->fetch();
    if (!$edit_school) $action = 'list';
}

$nav_links = [
    ['key' => 'home',        'label' => 'Home',        'href' => 'dashboard.php'],
    ['key' => 'schools',     'label' => 'Schools',     'href' => 'schools.php'],
    ['key' => 'departments', 'label' => 'Departments', 'href' => 'departments.php'],
    ['key' => 'programs',    'label' => 'Programs',    'href' => 'programs.php'],
    ['key' => 'students',    'label' => 'Students',    'href' => 'students.php'],
];
if ($is_admin) $nav_links[] = ['key' => 'users', 'label' => 'Users', 'href' => 'users.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schools — USJ-R SMS v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="schools.css">
    <link rel="stylesheet" href="students.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<nav class="topbar">
    <div class="topbar-brand">USJ-R School Management System <span class="version">v1.01</span></div>
    <div class="topbar-right">
        <span class="topbar-user">You are logged in as: <strong><?= htmlspecialchars($current_user) ?></strong></span>
        <span class="user-avatar">👤</span>
        <a href="?logout=1" class="btn-topbar-logout">Logout</a>
    </div>
</nav>

<div class="layout">
    <aside class="sidebar">
        <?php foreach ($nav_links as $link): ?>
        <a href="<?= $link['href'] ?>" class="sidebar-link <?= $link['key'] === 'schools' ? 'active' : '' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">

        <?php if (!empty($success)): ?>
        <div class="alert-success">✔ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($del_error)): ?>
        <div class="alert-error-bar">✖ <?= htmlspecialchars($del_error) ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>

        <div class="page-header">
            <h2 class="page-title">School List</h2>
            <a href="?action=create" class="btn-create">● Create School Entry</a>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School ID</th>
                        <th>School Full Name</th>
                        <th>School Short Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schools)): ?>
                    <tr><td colspan="4" class="empty-row">No schools yet. Click "Create School Entry" to add one.</td></tr>
                    <?php else: foreach ($schools as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['collid']) ?></td>
                        <td><?= htmlspecialchars($s['collfullname']) ?></td>
                        <td><?= htmlspecialchars($s['collshortname']) ?></td>
                        <td class="actions-cell">
                            <a href="?action=edit&id=<?= $s['collid'] ?>" class="btn-update">✎ Update</a>
                            <a href="?action=delete&id=<?= $s['collid'] ?>" class="btn-delete"
                               onclick="return confirm('Delete <?= htmlspecialchars($s['collfullname'], ENT_QUOTES) ?>?')">
                               🗑 Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-count">Total of: <?= $total_schools ?> school(s) in the database</p>

        <?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($current_page > 1): ?>
        <a href="?action=list&page=<?= $current_page - 1 ?>" class="page-btn">← Prev</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?action=list&page=<?= $p ?>"
           class="page-btn <?= $p === $current_page ? 'page-btn-active' : '' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <?php if ($current_page < $total_pages): ?>
        <a href="?action=list&page=<?= $current_page + 1 ?>" class="page-btn">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

        <?php elseif ($action === 'create'): ?>

        <h2 class="page-title">School Create</h2>

        <form method="POST" action="schools.php" class="entry-form" autocomplete="off">
            <input type="hidden" name="form_action" value="create">

            <div class="form-row">
                <label>School ID:</label>
                <div class="input-wrap">
                    <input type="number" name="collid"
                           value="<?= htmlspecialchars(p('collid')) ?>"
                           class="<?= isset($errors['collid']) ? 'is-error' : '' ?>">
                </div>
                <?php if (isset($errors['collid'])): ?>
                <span class="field-error"><?= $errors['collid'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label>School Full Name:</label>
                <div class="input-wrap">
                    <input type="text" name="collfullname"
                           value="<?= htmlspecialchars(p('collfullname')) ?>"
                           class="<?= isset($errors['collfullname']) ? 'is-error' : '' ?>"
                           id="fFullName" autocomplete="off"
                           oninput="filterSuggest(this.value,'fullNameDrop')"
                           onfocus="filterSuggest(this.value,'fullNameDrop')"
                           onblur="hideSuggest('fullNameDrop')">
                    <ul class="suggest-drop" id="fullNameDrop">
                        <?php
                        $existing = $pdo->query("SELECT collfullname FROM colleges ORDER BY collfullname")->fetchAll();
                        foreach ($existing as $ex):
                        ?>
                        <li onmousedown="pickSuggest('fFullName','fullNameDrop','<?= htmlspecialchars($ex['collfullname'], ENT_QUOTES) ?>')"><?= htmlspecialchars($ex['collfullname']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (isset($errors['collfullname'])): ?>
                <span class="field-error"><?= $errors['collfullname'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label>School Short Name:</label>
                <div class="input-wrap">
                    <input type="text" name="collshortname"
                           value="<?= htmlspecialchars(p('collshortname')) ?>"
                           class="<?= isset($errors['collshortname']) ? 'is-error' : '' ?>">
                </div>
                <?php if (isset($errors['collshortname'])): ?>
                <span class="field-error"><?= $errors['collshortname'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-save">Save New School Entry</button>
                <button type="reset"  class="btn-reset">Reset Form</button>
                <a href="schools.php" class="btn-exit">Exit</a>
            </div>
        </form>

        <?php elseif ($action === 'edit' && $edit_school): ?>

        <h2 class="page-title">School Update</h2>

        <form method="POST" action="schools.php" class="entry-form" autocomplete="off">
            <input type="hidden" name="form_action" value="update">
            <input type="hidden" name="edit_id"     value="<?= $edit_school['collid'] ?>">

            <div class="form-row">
                <label>School ID:</label>
                <div class="input-wrap">
                    <input type="number" name="collid"
                           value="<?= htmlspecialchars(p('collid', $edit_school['collid'])) ?>"
                           class="<?= isset($errors['collid']) ? 'is-error' : '' ?>">
                </div>
                <?php if (isset($errors['collid'])): ?>
                <span class="field-error"><?= $errors['collid'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label>School Full Name:</label>
                <div class="input-wrap">
                    <input type="text" name="collfullname"
                           value="<?= htmlspecialchars(p('collfullname', $edit_school['collfullname'])) ?>"
                           class="<?= isset($errors['collfullname']) ? 'is-error' : '' ?>">
                </div>
                <?php if (isset($errors['collfullname'])): ?>
                <span class="field-error"><?= $errors['collfullname'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label>School Short Name:</label>
                <div class="input-wrap">
                    <input type="text" name="collshortname"
                           value="<?= htmlspecialchars(p('collshortname', $edit_school['collshortname'])) ?>"
                           class="<?= isset($errors['collshortname']) ? 'is-error' : '' ?>">
                </div>
                <?php if (isset($errors['collshortname'])): ?>
                <span class="field-error"><?= $errors['collshortname'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-save">Update School Entry</button>
                <a href="schools.php" class="btn-exit">Exit</a>
            </div>
        </form>

        <?php endif; ?>
    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>

<script>
function filterSuggest(val, dropId) {
    const drop  = document.getElementById(dropId);
    if (!drop) return;
    const items = drop.querySelectorAll('li');
    const q     = val.toLowerCase();
    let any = false;
    items.forEach(li => {
        const show = li.textContent.toLowerCase().includes(q);
        li.style.display = show ? '' : 'none';
        if (show) any = true;
    });
    drop.classList.toggle('open', any);
}
function hideSuggest(dropId) {
    setTimeout(() => document.getElementById(dropId)?.classList.remove('open'), 160);
}
function pickSuggest(inputId, dropId, val) {
    document.getElementById(inputId).value = val;
    document.getElementById(dropId).classList.remove('open');
}
</script>
</body>
</html>