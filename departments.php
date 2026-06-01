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

$colleges    = $pdo->query("SELECT * FROM colleges ORDER BY collfullname")->fetchAll();
$all_depts   = $pdo->query("SELECT * FROM departments ORDER BY deptfullname")->fetchAll();

function p($k,$fb=''){return $_POST[$k]??$fb;}

$action      = $_GET['action']  ?? 'select';
$coll_id     = isset($_GET['coll_id'])  ? (int)$_GET['coll_id']  : (isset($_SESSION['dept_coll_id']) ? (int)$_SESSION['dept_coll_id'] : 0);
$edit_id     = isset($_GET['id'])       ? (int)$_GET['id']        : 0;
$errors      = [];
$success     = '';
$saved_dept  = null;  

if ($coll_id) $_SESSION['dept_coll_id'] = $coll_id;

//mao ni ang current data for college
$current_coll = null;
if ($coll_id) {
    $st = $pdo->prepare("SELECT * FROM colleges WHERE collid=?");
    $st->execute([$coll_id]);
    $current_coll = $st->fetch();
}

//mo handle sa select school
if ($action === 'select' && isset($_GET['coll_id']) && $coll_id) {
    $action = 'list';
}

// mo handle sa select departments

// mao ni ang para sa create
if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='create') {
    $coll_id   = (int)p('coll_id');
    $deptid    = (int)trim(p('deptid'));
    $fullname  = trim(p('deptfullname'));
    $shortname = trim(p('deptshortname'));

    if (!$deptid)   $errors['deptid']       = 'Department ID entry cannot be empty';
    if (!$fullname) $errors['deptfullname'] = 'Department Full Name entry cannot be empty';

    if (!isset($errors['deptid'])) {
        $id_str    = (string)$deptid;
        $coll_str  = (string)$coll_id;
        $expected_len = strlen($coll_str) + 3;
        if (strlen($id_str) !== $expected_len || strpos($id_str, $coll_str) !== 0) {
            $errors['deptid'] = 'Invalid ID entry or format';
        }
    }

    if (empty($errors['deptid'])) {
        $chk = $pdo->prepare("SELECT deptid FROM departments WHERE deptid = ?");
        $chk->execute([$deptid]);
        if ($chk->fetch()) $errors['deptid'] = 'Department ID already exists';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO departments (deptid, deptfullname, deptshortname, deptcollid) VALUES (?, ?, ?, ?)")
            ->execute([$deptid, $fullname, $shortname ?: null, $coll_id]);
        $saved_dept = ['deptid'=>$deptid,'deptfullname'=>$fullname,'deptshortname'=>$shortname];
        $success = 'Department entry created successfully';
        $action  = 'created';
    } else {
        $action = 'create';
    }
}

// para sa update
if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='update') {
    $upd_id    = (int)p('edit_id');
    $coll_id   = (int)p('coll_id');
    $deptid    = (int)trim(p('deptid'));
    $fullname  = trim(p('deptfullname'));
    $shortname = trim(p('deptshortname'));

    if (!$deptid)   $errors['deptid']       = 'Department ID entry cannot be empty';
    if (!$fullname) $errors['deptfullname'] = 'Department Full Name entry cannot be empty';

    if (!isset($errors['deptid'])) {
        $id_str    = (string)$deptid;
        $coll_str  = (string)$coll_id;
        $expected_len = strlen($coll_str) + 3;
        if (strlen($id_str) !== $expected_len || strpos($id_str, $coll_str) !== 0) {
            $errors['deptid'] = 'Invalid ID entry or format';
        }
    }

    if (empty($errors)) {
    try {
        $pdo->prepare("UPDATE departments SET deptid=?,deptfullname=?,deptshortname=? WHERE deptid=?")
            ->execute([$deptid,$fullname,$shortname,$upd_id]);
        $success = 'Department updated successfully!';
        $action  = 'list';
    } catch (PDOException $e) {
        $errors['deptid'] = 'Department ID already exists or is not valid.';
        $action  = 'edit';
        $edit_id = $upd_id;
    }
} else {
    $action  = 'edit';
    $edit_id = $upd_id;

}
}

// ipa confirm og i delete bajud
if ($action === 'delete' && $edit_id > 0) {
    $action = 'delete_confirm';
}

// padayon sa pag delete
if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='delete_proceed') {
    $del_id  = (int)p('del_id');
    $coll_id = (int)p('coll_id');

    $st = $pdo->prepare("SELECT * FROM departments WHERE deptid=?");
    $st->execute([$del_id]);
    $saved_dept = $st->fetch();
    try {
        $pdo->prepare("DELETE FROM departments WHERE deptid=?")->execute([$del_id]);
        $action = 'deleted';
    } catch (PDOException $e) {
        $success = '';
        $action  = 'list';
    }
}

$edit_dept = null;
if (in_array($action,['edit','delete_confirm']) && $edit_id > 0) {
    $st = $pdo->prepare("SELECT * FROM departments WHERE deptid=?");
    $st->execute([$edit_id]);
    $edit_dept = $st->fetch();
    if (!$edit_dept) $action = 'list';
    else $coll_id = $edit_dept['deptcollid'];
}

$dept_list = [];
$total_dept = 0;
$total_pages   = 0;
$per_page = 6;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

if ($action === 'list' && $coll_id) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE deptcollid = ?");
    $cnt->execute([$coll_id]);
    $total_dept  = (int)$cnt->fetchColumn();
    $total_pages = (int)ceil($total_dept / $per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset       = ($current_page - 1) * $per_page;
    $st = $pdo->prepare("SELECT * FROM departments WHERE deptcollid = ? ORDER BY deptid LIMIT ? OFFSET ?");
    $st->bindValue(1, $coll_id,  PDO::PARAM_INT);
    $st->bindValue(2, $per_page, PDO::PARAM_INT);
    $st->bindValue(3, $offset,   PDO::PARAM_INT);
    $st->execute();
    $dept_list = $st->fetchAll();
}
$nav_links = [
    ['key'=>'home',        'label'=>'Home',        'href'=>'dashboard.php'],
    ['key'=>'schools',     'label'=>'Schools',     'href'=>'schools.php'],
    ['key'=>'departments', 'label'=>'Departments', 'href'=>'departments.php'],
    ['key'=>'programs',    'label'=>'Programs',    'href'=>'programs.php'],
    ['key'=>'students',    'label'=>'Students',    'href'=>'students.php'],
];
if ($is_admin) $nav_links[] = ['key'=>'users','label'=>'Users','href'=>'users.php'];

$coll_name = $current_coll ? $current_coll['collfullname'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments — USJ-R SMS v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="students.css">
    <link rel="stylesheet" href="departments.css">
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
        <a href="<?= $link['href'] ?>" class="sidebar-link <?= $link['key']==='departments'?'active':'' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">

    <?php
    // para sa selecting school list
    if ($action === 'select' || ($action === 'list' && !$coll_id)):
        
        // check if a user ni click sa (Select School) og valid ba ang ID
        $is_step2_active = isset($_GET['submit_school']) && (int)$_GET['coll_id'] > 0;
    ?>
        <h2 class="page-title">Select School and Department</h2>

        <form method="GET" action="departments.php" class="select-form" id="selectForm">
            
            <?php if (!$is_step2_active): ?>
                <div class="select-row">
                    <select name="coll_id" id="selSchool" class="sel-control" required>
                        <option value="">Select School</option>
                        <?php foreach ($colleges as $c): ?>
                        <option value="<?= $c['collid'] ?>"><?= htmlspecialchars($c['collfullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="submit_school" value="1" class="btn-select-school">Select School</button>
                </div>

            <?php else: ?>
                <input type="hidden" name="coll_id" value="<?= htmlspecialchars($_GET['coll_id']) ?>">

                <div class="select-row">
                    <select class="sel-control sel-locked" disabled>
                        <?php foreach ($colleges as $c): ?>
                        <option value="<?= $c['collid'] ?>" <?= $c['collid'] == $_GET['coll_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['collfullname']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-select-school btn-grayed" disabled>Select School</button>
                </div>

                <div class="select-row" id="deptRow">
                    <select name="dept_id" id="selDept" class="sel-control" required>
                        <option value="">Select Department</option>
                        <?php foreach ($all_depts as $d): ?>
                            <?php if ((int)$d['deptcollid'] === (int)$_GET['coll_id']): ?>
                                <option value="<?= $d['deptid'] ?>"><?= htmlspecialchars($d['deptfullname']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="list" class="btn-select-dept" id="btnSelDept">Select Department</button>
                    <a href="departments.php" class="btn-back" style="margin-left: 8px;">Back</a>
                </div>
            <?php endif; ?>

        </form>
        <script>
        const allDepts = <?= json_encode($all_depts) ?>;

        function loadDepts(collId) {
            const row   = document.getElementById('deptRow');
            const sel   = document.getElementById('selDept');
            const btn   = document.getElementById('btnSelDept');
            sel.innerHTML = '<option value="">Select Department</option>';

            if (!collId) { row.style.display='none'; return; }

            const filtered = allDepts.filter(d => String(d.deptcollid) === String(collId));
            filtered.forEach(d => {
                const o = document.createElement('option');
                o.value = d.deptid;
                o.textContent = d.deptfullname;
                sel.appendChild(o);
            });
            row.style.display = '';
            btn.disabled = filtered.length === 0;
        }
        </script>

    <?php
    //Para sa department list
    elseif ($action === 'list' && $coll_id):
    ?>
        <?php if ($success): ?>
        <div class="alert-success">✔ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h2 class="page-title">Department List — <?= htmlspecialchars($coll_name) ?></h2>
            <div class="header-btns">
                <a href="?action=create&coll_id=<?= $coll_id ?>" class="btn-create">● Create Department Entry</a>
                <a href="departments.php" class="btn-back">↩ Back</a>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Department ID</th>
                        <th>Department Full Name</th>
                        <th>Department Short Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dept_list)): ?>
                    <tr><td colspan="4" class="empty-row">No departments for this school yet.</td></tr>
                    <?php else: foreach ($dept_list as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['deptid']) ?></td>
                        <td><?= htmlspecialchars($d['deptfullname']) ?></td>
                        <td><?= htmlspecialchars($d['deptshortname'] ?: '—') ?></td>
                        <td class="actions-cell">
                            <a href="?action=edit&id=<?= $d['deptid'] ?>&coll_id=<?= $coll_id ?>" class="btn-update">✎ Update</a>
                            <a href="?action=delete&id=<?= $d['deptid'] ?>&coll_id=<?= $coll_id ?>" class="btn-delete">🗑 Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-count">Total of: <?= $total_dept ?> department(s) in the database</p>

        <?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($current_page > 1): ?>
        <a href="?action=list&coll_id=<?= $coll_id ?>&page=<?= $current_page - 1 ?>" class="page-btn">← Prev</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?action=list&coll_id=<?= $coll_id ?>&page=<?= $p ?>"
           class="page-btn <?= $p === $current_page ? 'page-btn-active' : '' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <?php if ($current_page < $total_pages): ?>
        <a href="?action=list&coll_id=<?= $coll_id ?>&page=<?= $current_page + 1 ?>" class="page-btn">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?> 

    <?php
    // para sa create
    elseif ($action === 'create'):
    ?>
        <h2 class="page-title">Department Create — <?= $current_coll ? $current_coll['collid'].': '.htmlspecialchars($coll_name) : '' ?></h2>

        <form method="POST" action="departments.php" class="entry-form" autocomplete="off">
            <input type="hidden" name="form_action" value="create">
            <input type="hidden" name="coll_id"    value="<?= $coll_id ?>">

            <div class="form-row">
                <label>Department ID:</label>
                <div class="input-wrap">
                    <input type="number" name="deptid" id="fDeptId"
                           value="<?= htmlspecialchars(p('deptid')) ?>"
                           class="<?= isset($errors['deptid'])?'is-error':'' ?>"
                           autocomplete="off"
                           onfocus="showIdDrop()" onblur="hideIdDrop()"
                           oninput="filterIdDrop(this.value)">
                    <ul class="suggest-drop" id="idDrop">
                        <?php foreach ($all_depts as $d): ?>
                        <li onmousedown="pickId('<?= $d['deptid'] ?>')"><?= $d['deptid'] ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (isset($errors['deptid'])): ?>
                <span class="field-error"><?= $errors['deptid'] ?></span>
                <?php endif; ?>
            </div>

           
            <div class="form-row">
                <label>Department Full Name:</label>
                <div class="input-wrap">
                    <input type="text" name="deptfullname" id="fFullName"
                           value="<?= htmlspecialchars(p('deptfullname')) ?>"
                           class="<?= isset($errors['deptfullname'])?'is-error':'' ?>"
                           autocomplete="off"
                           onfocus="filterSuggest(this.value,'nameDrop')"
                           onblur="hideSuggest('nameDrop')"
                           oninput="filterSuggest(this.value,'nameDrop')">
                    <ul class="suggest-drop" id="nameDrop">
                        <?php foreach ($all_depts as $d): ?>
                        <li onmousedown="pickSuggest('fFullName','nameDrop','<?= htmlspecialchars($d['deptfullname'],ENT_QUOTES) ?>')"><?= htmlspecialchars($d['deptfullname']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (isset($errors['deptfullname'])): ?>
                <span class="field-error"><?= $errors['deptfullname'] ?></span>
                <?php endif; ?>
            </div>

        
            <div class="form-row">
                <label>Department Short Name:</label>
                <div class="input-wrap">
                    <input type="text" name="deptshortname"
                           value="<?= htmlspecialchars(p('deptshortname')) ?>">
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-save">Save New Department Entry</button>
                <button type="reset"  class="btn-reset">Reset Form</button>
                <a href="?action=list&coll_id=<?= $coll_id ?>" class="btn-exit">Exit</a>
            </div>
        </form>

    <?php
    //para sa create success
    elseif ($action === 'created' && $saved_dept):
    ?>
        <h2 class="page-title">Department Create — <?= $current_coll ? $current_coll['collid'].': '.htmlspecialchars($coll_name) : '' ?></h2>
        <p class="success-inline">✔ Department entry created successfully</p>

        <div class="entry-form readonly-form">
            <div class="form-row">
                <label>Department ID:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptid']) ?>" readonly></div>
            </div>
            <div class="form-row">
                <label>Department Full Name:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptfullname']) ?>" readonly></div>
            </div>
            <div class="form-row">
                <label>Department Short Name:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptshortname'] ?? '') ?>" readonly></div>
            </div>
            <div class="form-buttons">
                <a href="?action=create&coll_id=<?= $coll_id ?>" class="btn-save">Save New Department Entry</a>
                <a href="?action=list&coll_id=<?= $coll_id ?>"   class="btn-exit">Exit</a>
            </div>
        </div>

    <?php
    // para sa EDIT
    elseif ($action === 'edit' && $edit_dept):
    ?>
        <h2 class="page-title">Department Update — <?= htmlspecialchars($coll_name) ?></h2>

        <form method="POST" action="departments.php" class="entry-form" autocomplete="off">
            <input type="hidden" name="form_action" value="update">
            <input type="hidden" name="edit_id"     value="<?= $edit_dept['deptid'] ?>">
            <input type="hidden" name="coll_id"     value="<?= $coll_id ?>">

            <div class="form-row">
                <label>Department ID:</label>
                <div class="input-wrap">
                    <input type="number" name="deptid"
                           value="<?= htmlspecialchars(p('deptid',$edit_dept['deptid'])) ?>"
                           class="<?= isset($errors['deptid'])?'is-error':'' ?>">
                </div>
                <?php if (isset($errors['deptid'])): ?><span class="field-error"><?= $errors['deptid'] ?></span><?php endif; ?>
            </div>

            <div class="form-row">
                <label>Department Full Name:</label>
                <div class="input-wrap">
                    <input type="text" name="deptfullname"
                           value="<?= htmlspecialchars(p('deptfullname',$edit_dept['deptfullname'])) ?>"
                           class="<?= isset($errors['deptfullname'])?'is-error':'' ?>">
                </div>
                <?php if (isset($errors['deptfullname'])): ?><span class="field-error"><?= $errors['deptfullname'] ?></span><?php endif; ?>
            </div>

            <div class="form-row">
                <label>Department Short Name:</label>
                <div class="input-wrap">
                    <input type="text" name="deptshortname"
                           value="<?= htmlspecialchars(p('deptshortname',$edit_dept['deptshortname']??'')) ?>">
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-save">Update Department Entry</button>
                <a href="?action=list&coll_id=<?= $coll_id ?>" class="btn-exit">Exit</a>
            </div>
        </form>

    <?php
    // para sa delete confirmation
    elseif ($action === 'delete_confirm' && $edit_dept):
    ?>
        <h2 class="page-title">Department Delete</h2>
        <p class="delete-warning">You are about to delete the following department entry:</p>

        <div class="delete-info-table">
            <div class="di-row"><span class="di-label">Department ID:</span>         <span class="di-val"><?= htmlspecialchars($edit_dept['deptid']) ?></span></div>
            <div class="di-row"><span class="di-label">Department Full Name:</span>  <span class="di-val"><?= htmlspecialchars($edit_dept['deptfullname']) ?></span></div>
            <div class="di-row"><span class="di-label">Department Short Name:</span> <span class="di-val"><?= htmlspecialchars($edit_dept['deptshortname']??'') ?></span></div>
        </div>

        <p class="delete-sub">Are you sure you want to delete this department entry?</p>
        <p class="delete-note">This entry is part of a high-level relationship in the database.<br>Deleting this entry may affect related data.</p>

        <form method="POST" action="departments.php" class="delete-form">
            <input type="hidden" name="form_action" value="delete_proceed">
            <input type="hidden" name="del_id"      value="<?= $edit_dept['deptid'] ?>">
            <input type="hidden" name="coll_id"     value="<?= $coll_id ?>">
            <button type="submit" class="btn-yes-delete">Yes, Delete Entry</button>
            <a href="?action=list&coll_id=<?= $coll_id ?>" class="btn-no-cancel">No, Cancel</a>
        </form>

    <?php
    // para sa success nga pag delete
    elseif ($action === 'deleted' && $saved_dept):
    ?>
        <h2 class="page-title">Department Delete</h2>
        <p class="success-inline">✔ Department record deleted successfully.</p>

        <div class="entry-form readonly-form">
            <div class="form-row">
                <label>Department ID:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptid']) ?>" readonly></div>
            </div>
            <div class="form-row">
                <label>Department Full Name:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptfullname']) ?>" readonly></div>
            </div>
            <div class="form-row">
                <label>Department Short Name:</label>
                <div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved_dept['deptshortname']??'') ?>" readonly></div>
            </div>
            <div class="form-buttons">
                <a href="?action=list&coll_id=<?= $coll_id ?>" class="btn-save">Back to Department List</a>
            </div>
        </div>

    <?php endif; ?>

    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>

<script>
// ID autocomplete
function showIdDrop()  { filterIdDrop(document.getElementById('fDeptId').value); }
function hideIdDrop()  { setTimeout(()=>document.getElementById('idDrop')?.classList.remove('open'),160); }
function filterIdDrop(val) {
    const drop = document.getElementById('idDrop');
    if (!drop) return;
    const q = val.toLowerCase();
    let any = false;
    drop.querySelectorAll('li').forEach(li=>{
        const show = li.textContent.toLowerCase().includes(q);
        li.style.display = show?'':'none';
        if(show) any=true;
    });
    drop.classList.toggle('open', any);
}
function pickId(val) {
    document.getElementById('fDeptId').value = val;
    document.getElementById('idDrop').classList.remove('open');
}

// Name autocomplete
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