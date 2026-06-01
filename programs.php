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

$colleges    = $pdo->query("SELECT * FROM colleges    ORDER BY collfullname")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY deptfullname")->fetchAll();
$all_programs= $pdo->query("SELECT * FROM programs    ORDER BY progfullname")->fetchAll();

function p($k,$fb=''){return $_POST[$k]??$fb;}

$action   = $_GET['action']  ?? 'select';
$coll_id  = isset($_GET['coll_id'])  ? (int)$_GET['coll_id']  : (isset($_SESSION['prog_coll_id'])  ? (int)$_SESSION['prog_coll_id']  : 0);
$dept_id  = isset($_GET['dept_id'])  ? (int)$_GET['dept_id']  : (isset($_SESSION['prog_dept_id'])  ? (int)$_SESSION['prog_dept_id']  : 0);
$edit_id  = isset($_GET['id'])       ? (int)$_GET['id']        : 0;
$errors   = [];
$msg      = '';         
$msg_type = 'success';  
$saved_prog = null;

if ($coll_id) $_SESSION['prog_coll_id'] = $coll_id;
if ($dept_id) $_SESSION['prog_dept_id'] = $dept_id;


$current_coll = null; $current_dept = null;
if ($coll_id) {
    $s=$pdo->prepare("SELECT * FROM colleges WHERE collid=?"); $s->execute([$coll_id]); $current_coll=$s->fetch();
}
if ($dept_id) {
    $s=$pdo->prepare("SELECT * FROM departments WHERE deptid=?"); $s->execute([$dept_id]); $current_dept=$s->fetch();
}

if ($action==='select_school' && $coll_id) { $action='select_dept'; $dept_id=0; unset($_SESSION['prog_dept_id']); }
if ($action==='select_dept'   && $dept_id) { $action='list'; }
if ($action==='list' && (!$coll_id || !$dept_id)) { $action='select'; }

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='create') {
    $coll_id   = (int)p('coll_id');
    $dept_id   = (int)p('dept_id');
    $progid    = (int)trim(p('progid'));
    $fullname  = trim(p('progfullname'));
    $shortname = trim(p('progshortname'));

    if (!$progid)    $errors['progid']        = 'Program ID entry cannot be empty';
    if (!$fullname)  $errors['progfullname']  = 'Program Full Name entry cannot be empty';
    if (!$shortname) $errors['progshortname'] = 'Program Short Name entry cannot be empty';

    if (!isset($errors['progid'])) {
        $id_str       = (string)$progid;
        $coll_str     = (string)$coll_id;
        $doubled      = $coll_str . $coll_str;
        $expected_len = strlen($doubled) + 6;  
        if (strlen($id_str) !== $expected_len || strpos($id_str, $doubled) !== 0) {
            $errors['progid'] = 'Invalid ID entry or format';
        }
    }

    if (empty($errors['progid'])) {
        $chk = $pdo->prepare("SELECT progid FROM programs WHERE progid = ?");
        $chk->execute([$progid]);
        if ($chk->fetch()) $errors['progid'] = 'Program ID already exists';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO programs (progid, progfullname, progshortname, progcollid, progcolldeptid) VALUES (?, ?, ?, ?, ?)")
            ->execute([$progid, $fullname, $shortname, $coll_id, $dept_id]);
        $saved_prog = ['progid'=>$progid,'progfullname'=>$fullname,'progshortname'=>$shortname];
        $msg    = 'Program entry created successfully';
        $action = 'created';
    } else {
        $action = 'create';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='update') {

    $old_id    = (int)p('edit_id');
    $coll_id   = (int)p('coll_id');
    $dept_id   = (int)p('dept_id');
    $progid    = (int)p('progid');
    $fullname  = trim(p('progfullname'));
    $shortname = trim(p('progshortname'));

    if (!$progid)
        $errors['progid'] = 'Program ID entry cannot be empty';

    if (!$fullname)
        $errors['progfullname'] = 'Program Full Name entry cannot be empty';

    if (!$shortname)
        $errors['progshortname'] = 'Program Short Name entry cannot be empty';

    if (!isset($errors['progid'])) {
        $id_str       = (string)$progid;
        $coll_str     = (string)$coll_id;
        $doubled      = $coll_str . $coll_str;
        $expected_len = strlen($doubled) + 6;  
        if (strlen($id_str) !== $expected_len || strpos($id_str, $doubled) !== 0) {
            $errors['progid'] = 'Invalid ID entry or format';
        }
    }

    if (empty($errors)) {

        $chk = $pdo->prepare("
            SELECT progid
            FROM programs
            WHERE progid = ?
            AND progid <> ?
        ");
        $chk->execute([$progid, $old_id]);

        if ($chk->fetch()) {
            $errors['progid'] = 'Program ID already exists';
        }
    }

    if (empty($errors)) {

        $pdo->prepare("
            UPDATE programs
            SET progid=?,
                progfullname=?,
                progshortname=?
            WHERE progid=?
        ")->execute([
            $progid,
            $fullname,
            $shortname,
            $old_id
        ]);

        $saved_prog = [
            'progid' => $progid,
            'progfullname' => $fullname,
            'progshortname' => $shortname
        ];

        $action = 'updated';
    } else {
        $action   = 'edit';
        $edit_id  = $old_id;
        $msg      = 'Please fix the errors indicated below.';
        $msg_type = 'error';
    }
}

if ($action==='delete' && $edit_id>0) { $action='delete_confirm'; }

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='delete_proceed') {
    $del_id  = (int)p('del_id');
    $coll_id = (int)p('coll_id');
    $dept_id = (int)p('dept_id');
    $s=$pdo->prepare("SELECT * FROM programs WHERE progid=?"); $s->execute([$del_id]); $saved_prog=$s->fetch();
    try {
        $pdo->prepare("DELETE FROM programs WHERE progid=?")->execute([$del_id]);
        $action = 'deleted';
    } catch (PDOException $e) { $action='list'; }
}

$prog_list    = [];
$total_prog   = 0;
$total_pages  = 0;
$per_page     = 7;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($action === 'list' && $dept_id) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE progcolldeptid = ?");
    $cnt->execute([$dept_id]);
    $total_prog  = (int)$cnt->fetchColumn();
    $total_pages = (int)ceil($total_prog / $per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    $st = $pdo->prepare("SELECT * FROM programs WHERE progcolldeptid = ? ORDER BY progid LIMIT ? OFFSET ?");
    $st->bindValue(1, $dept_id,  PDO::PARAM_INT);
    $st->bindValue(2, $per_page, PDO::PARAM_INT);
    $st->bindValue(3, $offset,   PDO::PARAM_INT);
    $st->execute();
    $prog_list = $st->fetchAll();
}

$edit_prog = null;
if (in_array($action,['edit','delete_confirm']) && $edit_id>0) {
    $s=$pdo->prepare("SELECT * FROM programs WHERE progid=?"); $s->execute([$edit_id]); $edit_prog=$s->fetch();
    if (!$edit_prog) $action='list';
    else { $coll_id=$edit_prog['progcollid']; $dept_id=$edit_prog['progcolldeptid']; }
}

$nav_links = [
    ['key'=>'home',        'label'=>'Home',        'href'=>'dashboard.php'],
    ['key'=>'schools',     'label'=>'Schools',     'href'=>'schools.php'],
    ['key'=>'departments', 'label'=>'Departments', 'href'=>'departments.php'],
    ['key'=>'programs',    'label'=>'Programs',    'href'=>'programs.php'],
    ['key'=>'students',    'label'=>'Students',    'href'=>'students.php'],
];
if ($is_admin) $nav_links[] = ['key'=>'users','label'=>'Users','href'=>'users.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs — USJ-R SMS v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="students.css">
    <link rel="stylesheet" href="departments.css">
    <link rel="stylesheet" href="programs.css">
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
        <a href="<?= $link['href'] ?>" class="sidebar-link <?= $link['key']==='programs'?'active':'' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">

    <?php
    
    if ($action==='select'):
    ?>
    <h2 class="page-title">Select School</h2>
    <form method="GET" action="programs.php" class="select-form">
        <div class="select-row">
            <select name="coll_id" class="sel-control" required>
                <option value="">Select School</option>
                <?php foreach ($colleges as $c): ?>
                <option value="<?= $c['collid'] ?>"><?= htmlspecialchars($c['collfullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select_school" class="btn-select-school">Select School</button>
        </div>
        <div class="select-row">
            <select class="sel-control sel-locked" disabled><option>Select Department</option></select>
            <button class="btn-select-school btn-grayed" disabled type="button">Select Department</button>
        </div>
    </form>

    <?php
    elseif ($action==='select_dept'):
    ?>
    <h2 class="page-title">Select School and Department</h2>
    <form method="GET" action="programs.php" class="select-form">
        <input type="hidden" name="coll_id" value="<?= $coll_id ?>">
        <div class="select-row">
            <select class="sel-control sel-locked" disabled>
                <?php foreach ($colleges as $c): ?>
                <option value="<?= $c['collid'] ?>" <?= $c['collid']==$coll_id?'selected':'' ?>><?= htmlspecialchars($c['collfullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select" class="btn-select-school btn-grayed">Select School</button>
        </div>
        <div class="select-row">
            <select name="dept_id" class="sel-control" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): if ($d['deptcollid']!=$coll_id) continue; ?>
                <option value="<?= $d['deptid'] ?>"><?= htmlspecialchars($d['deptfullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select_dept" class="btn-select-school">Select Department</button>
            <a href="programs.php" class="btn-back">Back</a>
        </div>
    </form>

    <?php
    
    elseif ($action==='list'):
        $coll_name = $current_coll['collfullname'] ?? '';
        $dept_name = $current_dept['deptfullname'] ?? '';
    ?>
    <div class="page-header">
        <h2 class="page-title">Program List — <?= htmlspecialchars($coll_name) ?> | <?= htmlspecialchars($dept_name) ?></h2>
        <div class="header-btns">
            <a href="?action=create&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-create">● Create Program Entry</a>
            <a href="programs.php" class="btn-back">↩ Back</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Program ID</th>
                    <th>Program Full Name</th>
                    <th>Program Short Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prog_list)): ?>
                <tr><td colspan="4" class="empty-row">No programs yet for this department.</td></tr>
                <?php else: foreach ($prog_list as $pr): ?>
                <tr>
                    <td><?= htmlspecialchars($pr['progid']) ?></td>
                    <td><?= htmlspecialchars($pr['progfullname']) ?></td>
                    <td><?= htmlspecialchars($pr['progshortname']) ?></td>
                    <td class="actions-cell">
                        <a href="?action=edit&id=<?= $pr['progid'] ?>&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-update">✎ Update</a>
                        <a href="?action=delete&id=<?= $pr['progid'] ?>&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-delete">🗑 Delete</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p class="table-count">Total of: <?= $total_prog ?> program(s) in the database</p>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>&page=<?= $current_page - 1 ?>" class="page-btn">← Prev</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>&page=<?= $p ?>"
               class="page-btn <?= $p === $current_page ? 'page-btn-active' : '' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>&page=<?= $current_page + 1 ?>" class="page-btn">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($action==='create'):
        $coll_name = $current_coll['collfullname'] ?? '';
        $dept_name = $current_dept ? $current_dept['deptfullname'] : '';
    ?>
    <h2 class="page-title">Program Create</h2>
    <form method="POST" action="programs.php" class="entry-form" autocomplete="off">
        <input type="hidden" name="form_action" value="create">
        <input type="hidden" name="coll_id"     value="<?= $coll_id ?>">
        <input type="hidden" name="dept_id"     value="<?= $dept_id ?>">

        <div class="form-row">
            <label>Program ID:</label>
            <div class="input-wrap">
                <input type="number" name="progid" value="<?= htmlspecialchars(p('progid')) ?>"
                       class="<?= isset($errors['progid'])?'is-error':'' ?>">
            </div>
            <?php if (isset($errors['progid'])): ?><span class="field-error"><?= $errors['progid'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>Program Full Name:</label>
            <div class="input-wrap">
                <input type="text" name="progfullname" id="fProgName"
                       value="<?= htmlspecialchars(p('progfullname')) ?>"
                       class="<?= isset($errors['progfullname'])?'is-error':'' ?>"
                       autocomplete="off"
                       onfocus="filterSuggest(this.value,'progNameDrop')"
                       onblur="hideSuggest('progNameDrop')"
                       oninput="filterSuggest(this.value,'progNameDrop')">
                <ul class="suggest-drop" id="progNameDrop">
                    <?php foreach ($all_programs as $pr): ?>
                    <li onmousedown="pickSuggest('fProgName','progNameDrop','<?= htmlspecialchars($pr['progfullname'],ENT_QUOTES) ?>')"><?= htmlspecialchars($pr['progfullname']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (isset($errors['progfullname'])): ?><span class="field-error"><?= $errors['progfullname'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>Program Short Name:</label>
            <div class="input-wrap">
                <input type="text" name="progshortname" value="<?= htmlspecialchars(p('progshortname')) ?>"
                       class="<?= isset($errors['progshortname'])?'is-error':'' ?>">
            </div>
            <?php if (isset($errors['progshortname'])): ?><span class="field-error"><?= $errors['progshortname'] ?></span><?php endif; ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-save">Save New Program Entry</button>
            <button type="reset"  class="btn-reset">Reset Form</button>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-exit">Exit</a>
        </div>
    </form>

    <?php
    
    elseif ($action==='created' && $saved_prog):
    ?>
    <h2 class="page-title">Program Create</h2>
    <p class="prog-msg prog-msg--success">✔ <?= htmlspecialchars($msg) ?></p>
    <div class="entry-form readonly-form">
        <div class="form-row"><label>Program ID:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progid']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Full Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progfullname']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Short Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progshortname']) ?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=create&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-save">Save New Program Entry</a>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>"   class="btn-exit">Exit</a>
        </div>
    </div>

    <?php
    
    elseif ($action==='edit' && $edit_prog):
    ?>
    <h2 class="page-title">Program Update</h2>
    <?php if ($msg && $msg_type==='error'): ?>
    <p class="prog-msg prog-msg--error"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <form method="POST" action="programs.php" class="entry-form" autocomplete="off">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="edit_id"     value="<?= $edit_prog['progid'] ?>">
        <input type="hidden" name="coll_id"     value="<?= $coll_id ?>">
        <input type="hidden" name="dept_id"     value="<?= $dept_id ?>">

        <div class="form-row">
            <label>Program ID:</label>
            <div class="input-wrap">
                <input type="number" name="progid" value="<?= htmlspecialchars(p('progid',$edit_prog['progid'])) ?>"
                       class="<?= isset($errors['progid'])?'is-error':'' ?>">
            </div>
            <?php if (isset($errors['progid'])): ?><span class="field-error"><?= $errors['progid'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>Program Full Name:</label>
            <div class="input-wrap">
                <input type="text" name="progfullname" value="<?= htmlspecialchars(p('progfullname',$edit_prog['progfullname'])) ?>"
                       class="<?= isset($errors['progfullname'])?'is-error':'' ?>">
            </div>
            <?php if (isset($errors['progfullname'])): ?><span class="field-error"><?= $errors['progfullname'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>Program Short Name:</label>
            <div class="input-wrap">
                <input type="text" name="progshortname" value="<?= htmlspecialchars(p('progshortname',$edit_prog['progshortname'])) ?>"
                       class="<?= isset($errors['progshortname'])?'is-error':'' ?>">
            </div>
            <?php if (isset($errors['progshortname'])): ?><span class="field-error"><?= $errors['progshortname'] ?></span><?php endif; ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-save">Update Program Entry</button>
            <button type="reset"  class="btn-reset">Reset Form</button>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-exit">Exit</a>
        </div>
    </form>

    <?php
   
    elseif ($action==='updated' && $saved_prog):
    ?>
    <h2 class="page-title">Program Update</h2>
    <?php if ($msg_type==='info'): ?>
    <p class="prog-msg prog-msg--info"><?= htmlspecialchars($msg) ?></p>
    <?php else: ?>
    <p class="prog-msg prog-msg--success">Program entry <span class="highlight-word">updated</span> successfully.</p>
    <?php endif; ?>

    <div class="entry-form readonly-form">
        <div class="form-row"><label>Program ID:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progid']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Full Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progfullname']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Short Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progshortname']??'') ?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=edit&id=<?= $saved_prog['progid'] ?>&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-save">Update Program Entry</a>
            <a href="programs.php" class="btn-reset">Reset Form</a>
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-exit">Exit</a>
        </div>
    </div>

    <?php
    
    elseif ($action==='delete_confirm' && $edit_prog):
    ?>
    <h2 class="page-title">Program Delete</h2>
    <p class="delete-warning">You are about to delete the following program entry:</p>

    <div class="delete-info-table">
        <div class="di-row"><span class="di-label">Program ID:</span>         <span class="di-val"><?= htmlspecialchars($edit_prog['progid']) ?></span></div>
        <div class="di-row"><span class="di-label">Program Full Name:</span>  <span class="di-val"><?= htmlspecialchars($edit_prog['progfullname']) ?></span></div>
        <div class="di-row"><span class="di-label">Program Short Name:</span> <span class="di-val"><?= htmlspecialchars($edit_prog['progshortname']??'') ?></span></div>
    </div>

    <p class="delete-sub">Are you sure you want to delete this program entry?</p>
    <p class="delete-note">This entry is part of a high-level relationship in the database.<br>Deleting this entry may affect related data.</p>

    <form method="POST" action="programs.php" class="delete-form">
        <input type="hidden" name="form_action" value="delete_proceed">
        <input type="hidden" name="del_id"      value="<?= $edit_prog['progid'] ?>">
        <input type="hidden" name="coll_id"     value="<?= $coll_id ?>">
        <input type="hidden" name="dept_id"     value="<?= $dept_id ?>">
        <button type="submit" class="btn-yes-delete">Yes, Delete Entry</button>
        <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-no-cancel">No, Cancel</a>
    </form>

    <?php
    elseif ($action==='deleted' && $saved_prog):
    ?>
    <h2 class="page-title">Program Delete</h2>
    <p class="prog-msg prog-msg--success">✔ Program record deleted successfully.</p>
    <div class="entry-form readonly-form">
        <div class="form-row"><label>Program ID:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progid']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Full Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progfullname']) ?>" readonly></div></div>
        <div class="form-row"><label>Program Short Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_prog['progshortname']??'') ?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=list&coll_id=<?= $coll_id ?>&dept_id=<?= $dept_id ?>" class="btn-save">Back to Program List</a>
        </div>
    </div>

    <?php endif; ?>

    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>

<script>
function filterSuggest(val,dropId){
    const drop=document.getElementById(dropId); if(!drop)return;
    const q=val.toLowerCase(); let any=false;
    drop.querySelectorAll('li').forEach(li=>{
        const show=li.textContent.toLowerCase().includes(q);
        li.style.display=show?'':'none'; if(show)any=true;
    });
    drop.classList.toggle('open',any);
}
function hideSuggest(id){setTimeout(()=>document.getElementById(id)?.classList.remove('open'),160);}
function pickSuggest(inputId,dropId,val){
    document.getElementById(inputId).value=val;
    document.getElementById(dropId).classList.remove('open');
}
</script>
</body>
</html>