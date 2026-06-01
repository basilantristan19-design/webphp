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
$programs    = $pdo->query("SELECT * FROM programs    ORDER BY progfullname")->fetchAll();

function p($k,$fb=''){return $_POST[$k]??$fb;}

$action  = $_GET['action'] ?? 'select';
$coll_id = isset($_GET['coll_id']) ? (int)$_GET['coll_id'] : (isset($_SESSION['st_coll_id']) ? (int)$_SESSION['st_coll_id'] : 0);
$dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : (isset($_SESSION['st_dept_id']) ? (int)$_SESSION['st_dept_id'] : 0);
$prog_id = isset($_GET['prog_id']) ? (int)$_GET['prog_id'] : (isset($_SESSION['st_prog_id']) ? (int)$_SESSION['st_prog_id'] : 0);
$edit_id = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$errors  = [];
$msg     = ''; $msg_type = 'success';
$saved   = null;

if ($coll_id) $_SESSION['st_coll_id'] = $coll_id;
if ($dept_id) $_SESSION['st_dept_id'] = $dept_id;
if ($prog_id) $_SESSION['st_prog_id'] = $prog_id;

$cur_coll = null; $cur_dept = null; $cur_prog = null;
if ($coll_id){ $s=$pdo->prepare("SELECT * FROM colleges    WHERE collid=?"); $s->execute([$coll_id]); $cur_coll=$s->fetch(); }
if ($dept_id){ $s=$pdo->prepare("SELECT * FROM departments WHERE deptid=?"); $s->execute([$dept_id]); $cur_dept=$s->fetch(); }
if ($prog_id){ $s=$pdo->prepare("SELECT * FROM programs    WHERE progid=?"); $s->execute([$prog_id]); $cur_prog=$s->fetch(); }

$year_levels = [1=>'1st Year',2=>'2nd Year',3=>'3rd Year',4=>'4th Year',5=>'5th Year'];

if ($action==='select_school' && $coll_id) { $dept_id=0; $prog_id=0; unset($_SESSION['st_dept_id'],$_SESSION['st_prog_id']); $action='select_dept'; }
if ($action==='select_dept'   && $dept_id) { $prog_id=0; unset($_SESSION['st_prog_id']); $action='select_prog'; }
if ($action==='select_prog'   && $prog_id) { $action='list'; }
if ($action==='list' && (!$coll_id||!$dept_id||!$prog_id)) $action='select';

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='create') {
    $coll_id = (int)p('coll_id'); $dept_id=(int)p('dept_id'); $prog_id=(int)p('prog_id');
    $studid    = trim(p('studid'));
    $firstname = trim(p('studfirstname'));
    $midname   = trim(p('studmidname'));
    $lastname  = trim(p('studlastname'));
    $year      = (int)p('studyear');

    if (!$studid)    $errors['studid']        = 'Student ID entry cannot be empty';
    if (!$firstname) $errors['studfirstname'] = 'Student First Name entry cannot be empty';
    if (!$lastname)  $errors['studlastname']  = 'Student Last Name entry cannot be empty';
    if (!$year)      $errors['studyear']      = 'Student Year entry cannot be empty';
    elseif ($year < 1 || $year > 5) $errors['studyear'] = 'Invalid Year entry or format';

    if (empty($errors['studid'])) {
        $chk=$pdo->prepare("SELECT studid FROM students WHERE studid=?"); $chk->execute([$studid]);
        if ($chk->fetch()) $errors['studid']='Student ID already exists';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO students (studid,studfirstname,studlastname,studmidname,studcollid,studcolldeptid,studprogid,studyear) VALUES(?,?,?,?,?,?,?,?)")
            ->execute([$studid,$firstname,$lastname,$midname?:null,$coll_id,$dept_id,$prog_id,$year]);
        $saved = ['studid'=>$studid,'studfirstname'=>$firstname,'studmidname'=>$midname,'studlastname'=>$lastname,'studyear'=>$year];
        $msg   = 'Student entry created successfully';
        $action= 'created';
    } else {
        $action='create';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='update') {
    $upd_id    = (int)p('edit_id');
    $coll_id=(int)p('coll_id'); $dept_id=(int)p('dept_id'); $prog_id=(int)p('prog_id');
    $studid    = trim(p('studid'));
    $firstname = trim(p('studfirstname'));
    $midname   = trim(p('studmidname'));
    $lastname  = trim(p('studlastname'));
    $year      = (int)p('studyear');

    if (!$studid)    $errors['studid']        = 'Student ID entry cannot be empty';
    if (!$firstname) $errors['studfirstname'] = 'Student First Name entry cannot be empty';
    if (!$lastname)  $errors['studlastname']  = 'Student Last Name entry cannot be empty';
    if (!$year)      $errors['studyear']      = 'Student Year entry cannot be empty';
    elseif ($year<1||$year>5) $errors['studyear']='Invalid Year entry or format';

    if (empty($errors)) {
        $pdo->prepare("UPDATE students SET studid=?,studfirstname=?,studlastname=?,studmidname=?,studcollid=?,studcolldeptid=?,studprogid=?,studyear=? WHERE studid=?")
            ->execute([$studid,$firstname,$lastname,$midname?:null,$coll_id,$dept_id,$prog_id,$year,$upd_id]);
        $saved = ['studid'=>$studid,'studfirstname'=>$firstname,'studmidname'=>$midname,'studlastname'=>$lastname,'studyear'=>$year];
        $msg   = 'Student entry updated successfully';
        $action= 'updated';
    } else {
        $msg='Please fix the errors indicated below.'; $msg_type='error';
        $action='edit'; $edit_id=$upd_id;
    }
}

if ($action==='delete' && $edit_id>0) $action='delete_confirm';

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='delete_proceed') {
    $del_id=(int)p('del_id');
    $coll_id=(int)p('coll_id'); $dept_id=(int)p('dept_id'); $prog_id=(int)p('prog_id');
    $s=$pdo->prepare("SELECT * FROM students WHERE studid=?"); $s->execute([$del_id]); $saved=$s->fetch();
    $pdo->prepare("DELETE FROM students WHERE studid=?")->execute([$del_id]);
    $msg='Student record deleted successfully.'; $action='deleted';
}

$students     = [];
$total_stud   = 0;
$total_pages  = 0;
$per_page     = 7;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($action === 'list' && $prog_id) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE studprogid = ?");
    $cnt->execute([$prog_id]);
    $total_stud   = (int)$cnt->fetchColumn();
    $total_pages  = (int)ceil($total_stud / $per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset       = ($current_page - 1) * $per_page;
    $st = $pdo->prepare("SELECT * FROM students WHERE studprogid = ? ORDER BY studlastname, studfirstname LIMIT ? OFFSET ?");
    $st->bindValue(1, $prog_id,  PDO::PARAM_INT);
    $st->bindValue(2, $per_page, PDO::PARAM_INT);
    $st->bindValue(3, $offset,   PDO::PARAM_INT);
    $st->execute();
    $students = $st->fetchAll();
}

$edit_st=null;
if (in_array($action,['edit','delete_confirm']) && $edit_id>0) {
    $s=$pdo->prepare("SELECT * FROM students WHERE studid=?"); $s->execute([$edit_id]); $edit_st=$s->fetch();
    if (!$edit_st) $action='list';
    else { $coll_id=$edit_st['studcollid']; $dept_id=$edit_st['studcolldeptid']; $prog_id=$edit_st['studprogid']; }
}

function ctxLabel($coll,$dept,$prog){
    $parts=[];
    if($coll) $parts[]=$coll['collid'].': '.$coll['collshortname'];
    if($dept) $parts[]=$dept['deptid'].': '.($dept['deptshortname']?:substr($dept['deptfullname'],0,4));
    if($prog) $parts[]=$prog['progid'].': '.$prog['progshortname'];
    return implode(' | ',$parts);
}

$nav_links=[
    ['key'=>'home',        'label'=>'Home',        'href'=>'dashboard.php'],
    ['key'=>'schools',     'label'=>'Schools',     'href'=>'schools.php'],
    ['key'=>'departments', 'label'=>'Departments', 'href'=>'departments.php'],
    ['key'=>'programs',    'label'=>'Programs',    'href'=>'programs.php'],
    ['key'=>'students',    'label'=>'Students',    'href'=>'students.php'],
];
if ($is_admin) $nav_links[]=['key'=>'users','label'=>'Users','href'=>'users.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — USJ-R SMS v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="departments.css">
    <link rel="stylesheet" href="programs.css">
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
        <a href="<?= $link['href'] ?>" class="sidebar-link <?= $link['key']==='students'?'active':'' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">
    <?php
    if ($action==='select'):
    ?>
    <h2 class="page-title">Select School, Department and Program</h2>
    <form method="GET" action="students.php" class="select-form">
        <div class="select-row">
            <select name="coll_id" class="sel-control" required>
                <option value="">Select School</option>
                <?php foreach($colleges as $c): ?>
                <option value="<?=$c['collid']?>"><?=htmlspecialchars($c['collfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select_school" class="btn-select-school">Select School</button>
        </div>
        <div class="select-row">
            <select class="sel-control sel-locked" disabled><option>Select Department</option></select>
            <button class="btn-select-school btn-grayed" disabled type="button">Select Department</button>
        </div>
        <div class="select-row">
            <select class="sel-control sel-locked" disabled><option>Select Program</option></select>
            <button class="btn-select-school btn-grayed" disabled type="button">Select Program</button>
        </div>
    </form>

    <?php
    elseif ($action==='select_dept'):
    ?>
    <h2 class="page-title">Select School, Department and Program</h2>
    <form method="GET" action="students.php" class="select-form">
        <input type="hidden" name="coll_id" value="<?=$coll_id?>">
        <div class="select-row">
            <select class="sel-control sel-locked" disabled>
                <?php foreach($colleges as $c): ?>
                <option value="<?=$c['collid']?>" <?=$c['collid']==$coll_id?'selected':''?>><?=htmlspecialchars($c['collfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-select-school btn-grayed">Select School</button>
        </div>
        <div class="select-row">
            <select name="dept_id" class="sel-control" required>
                <option value="">Select Department</option>
                <?php foreach($departments as $d): if($d['deptcollid']!=$coll_id) continue; ?>
                <option value="<?=$d['deptid']?>"><?=htmlspecialchars($d['deptfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select_dept" class="btn-select-school">Select Department</button>
            <a href="students.php" class="btn-back">Back</a>
        </div>
        <div class="select-row">
            <select class="sel-control sel-locked" disabled><option>Select Program</option></select>
            <button class="btn-select-school btn-grayed" disabled type="button">Select Program</button>
        </div>
    </form>

    <?php
    elseif ($action==='select_prog'):
    ?>
    <h2 class="page-title">Select School, Department and Program</h2>
    <form method="GET" action="students.php" class="select-form">
        <input type="hidden" name="coll_id" value="<?=$coll_id?>">
        <input type="hidden" name="dept_id" value="<?=$dept_id?>">
        <div class="select-row">
            <select class="sel-control sel-locked" disabled>
                <?php foreach($colleges as $c): ?>
                <option <?=$c['collid']==$coll_id?'selected':''?>><?=htmlspecialchars($c['collfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-select-school btn-grayed">Select School</button>
        </div>
        <div class="select-row">
            <select class="sel-control sel-locked" disabled>
                <?php foreach($departments as $d): if($d['deptid']!=$dept_id) continue; ?>
                <option selected><?=htmlspecialchars($d['deptfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-select-school btn-grayed">Select Department</button>
            <a href="?action=select_school&coll_id=<?=$coll_id?>" class="btn-back">Back</a>
        </div>
        <div class="select-row">
            <select name="prog_id" class="sel-control" required>
                <option value="">Select Program</option>
                <?php foreach($programs as $pr): if($pr['progcolldeptid']!=$dept_id) continue; ?>
                <option value="<?=$pr['progid']?>"><?=htmlspecialchars($pr['progfullname'])?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="select_prog" class="btn-select-school">Select Program</button>
        </div>
    </form>

    <?php
    elseif ($action==='list'):
    ?>
    <h2 class="page-title">Student List</h2>
    <div class="list-context">
        <div><?=htmlspecialchars($cur_coll['collfullname']??'')?></div>
        <div><?=htmlspecialchars($cur_dept['deptfullname']??'')?></div>
        <div><?=htmlspecialchars($cur_prog['progfullname']??'')?></div>
    </div>
    <div class="page-header" style="margin-top:12px">
        <div class="header-btns">
            <a href="?action=create&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-create">● Create Student Entry</a>
            <a href="students.php" class="btn-back">↩ Back</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID #</th>
                    <th>LastName</th>
                    <th>FirstName</th>
                    <th>MiddleName</th>
                    <th>Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                <tr><td colspan="6" class="empty-row">No students yet for this program.</td></tr>
                <?php else: foreach($students as $st): ?>
                <tr>
                    <td><?=htmlspecialchars($st['studid'])?></td>
                    <td><?=htmlspecialchars($st['studlastname'])?></td>
                    <td><?=htmlspecialchars($st['studfirstname'])?></td>
                    <td><?=htmlspecialchars($st['studmidname']??'')?></td>
                    <td><?=$st['studyear']?></td>
                    <td class="actions-cell">
                        <a href="?action=edit&id=<?=$st['studid']?>&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-update">✎ Update</a>
                        <a href="?action=delete&id=<?=$st['studid']?>&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-delete">🗑 Delete</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p class="table-count">Total of: <?= $total_stud ?> student(s) in the database</p>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>&page=<?= $current_page - 1 ?>" class="page-btn">← Prev</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>&page=<?= $p ?>"
               class="page-btn <?= $p === $current_page ? 'page-btn-active' : '' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>&page=<?= $current_page + 1 ?>" class="page-btn">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    elseif ($action==='create'):
        $ctx = ctxLabel($cur_coll,$cur_dept,$cur_prog);
    ?>
    <h2 class="page-title">Student Create<?= $ctx ? ' — '.$ctx : '' ?></h2>
    <form method="POST" action="students.php" class="entry-form" autocomplete="off">
        <input type="hidden" name="form_action" value="create">
        <input type="hidden" name="coll_id" value="<?=$coll_id?>">
        <input type="hidden" name="dept_id" value="<?=$dept_id?>">
        <input type="hidden" name="prog_id" value="<?=$prog_id?>">

        <div class="form-row">
            <label>Student ID:</label>
            <div class="input-wrap"><input type="number" name="studid" value="<?=htmlspecialchars(p('studid'))?>" class="<?=isset($errors['studid'])?'is-error':''?>"></div>
            <?php if(isset($errors['studid'])): ?><span class="field-error"><?=$errors['studid']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student First Name:</label>
            <div class="input-wrap"><input type="text" name="studfirstname" value="<?=htmlspecialchars(p('studfirstname'))?>" class="<?=isset($errors['studfirstname'])?'is-error':''?>"></div>
            <?php if(isset($errors['studfirstname'])): ?><span class="field-error"><?=$errors['studfirstname']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student Middle Name:</label>
            <div class="input-wrap"><input type="text" name="studmidname" value="<?=htmlspecialchars(p('studmidname'))?>"></div>
        </div>
        <div class="form-row">
            <label>Student Last Name:</label>
            <div class="input-wrap"><input type="text" name="studlastname" value="<?=htmlspecialchars(p('studlastname'))?>" class="<?=isset($errors['studlastname'])?'is-error':''?>"></div>
            <?php if(isset($errors['studlastname'])): ?><span class="field-error"><?=$errors['studlastname']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student Year:</label>
            <div class="input-wrap">
                <input type="number" name="studyear" id="fYear" min="1" max="5"
                       value="<?=htmlspecialchars(p('studyear'))?>"
                       class="<?=isset($errors['studyear'])?'is-error':''?>"
                       autocomplete="off"
                       onfocus="showYearDrop()" onblur="hideYearDrop()"
                       oninput="filterYearDrop(this.value)">
                <ul class="suggest-drop" id="yearDrop">
                    <?php foreach($year_levels as $n=>$lbl): ?>
                    <li onmousedown="pickYear(<?=$n?>)"><?=$n?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if(isset($errors['studyear'])): ?><span class="field-error"><?=$errors['studyear']?></span><?php endif; ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-save">Save New Student Entry</button>
            <button type="reset"  class="btn-reset">Reset Form</button>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-exit">Exit</a>
        </div>
    </form>

    <?php
    elseif ($action==='created' && $saved):
        $ctx = ctxLabel($cur_coll,$cur_dept,$cur_prog);
    ?>
    <h2 class="page-title">Student Create<?= $ctx ? ' — '.$ctx : '' ?></h2>
    <p class="prog-msg prog-msg--success-inline"><?=htmlspecialchars($msg)?></p>
    <div class="entry-form readonly-form">
        <div class="form-row"><label>Student ID:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studid'])?>" readonly></div></div>
        <div class="form-row"><label>Student First Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studfirstname'])?>" readonly></div></div>
        <div class="form-row"><label>Student Middle Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studmidname']??'')?>" readonly></div></div>
        <div class="form-row"><label>Student Last Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studlastname'])?>" readonly></div></div>
        <div class="form-row"><label>Student Year:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studyear'])?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=create&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-save">Save New Student Entry</a>
            <a href="?action=create&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-reset">Reset Form</a>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>"   class="btn-exit">Exit</a>
        </div>
    </div>
    <?php

    elseif ($action==='edit' && $edit_st):
        $ctx = ctxLabel($cur_coll,$cur_dept,$cur_prog);
    ?>
    <h2 class="page-title">Student Update<?= $ctx ? ' — '.$ctx : '' ?></h2>
    <?php if($msg_type==='error'): ?><p class="prog-msg prog-msg--error"><?=htmlspecialchars($msg)?></p><?php endif; ?>
    <form method="POST" action="students.php" class="entry-form" autocomplete="off">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="edit_id"     value="<?=$edit_st['studid']?>">
        <input type="hidden" name="coll_id"     value="<?=$coll_id?>">
        <input type="hidden" name="dept_id"     value="<?=$dept_id?>">
        <input type="hidden" name="prog_id"     value="<?=$prog_id?>">

        <div class="form-row">
            <label>Student ID:</label>
            <div class="input-wrap"><input type="number" name="studid" value="<?=htmlspecialchars(p('studid',$edit_st['studid']))?>" class="<?=isset($errors['studid'])?'is-error':''?>"></div>
            <?php if(isset($errors['studid'])): ?><span class="field-error"><?=$errors['studid']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student First Name:</label>
            <div class="input-wrap"><input type="text" name="studfirstname" value="<?=htmlspecialchars(p('studfirstname',$edit_st['studfirstname']))?>" class="<?=isset($errors['studfirstname'])?'is-error':''?>"></div>
            <?php if(isset($errors['studfirstname'])): ?><span class="field-error"><?=$errors['studfirstname']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student Middle Name:</label>
            <div class="input-wrap"><input type="text" name="studmidname" value="<?=htmlspecialchars(p('studmidname',$edit_st['studmidname']??''))?>"></div>
        </div>
        <div class="form-row">
            <label>Student Last Name:</label>
            <div class="input-wrap"><input type="text" name="studlastname" value="<?=htmlspecialchars(p('studlastname',$edit_st['studlastname']))?>" class="<?=isset($errors['studlastname'])?'is-error':''?>"></div>
            <?php if(isset($errors['studlastname'])): ?><span class="field-error"><?=$errors['studlastname']?></span><?php endif; ?>
        </div>
        <div class="form-row">
            <label>Student Year:</label>
            <div class="input-wrap"><input type="number" name="studyear" min="1" max="5" value="<?=htmlspecialchars(p('studyear',$edit_st['studyear']))?>" class="<?=isset($errors['studyear'])?'is-error':''?>"></div>
            <?php if(isset($errors['studyear'])): ?><span class="field-error"><?=$errors['studyear']?></span><?php endif; ?>
        </div>
        <div class="form-buttons">
            <button type="submit" class="btn-save">Update Student Entry</button>
            <button type="reset"  class="btn-reset">Reset Form</button>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-exit">Exit</a>
        </div>
    </form>

    <?php
    elseif ($action==='updated' && $saved):
    ?>
    <h2 class="page-title">Student Update</h2>
    <p class="prog-msg prog-msg--success"><?=htmlspecialchars($msg)?></p>
    <div class="entry-form readonly-form">
        <div class="form-row"><label>Student ID:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studid'])?>" readonly></div></div>
        <div class="form-row"><label>Student First Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studfirstname'])?>" readonly></div></div>
        <div class="form-row"><label>Student Middle Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studmidname']??'')?>" readonly></div></div>
        <div class="form-row"><label>Student Last Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studlastname'])?>" readonly></div></div>
        <div class="form-row"><label>Student Year:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studyear'])?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=edit&id=<?=$saved['studid']?>&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-save">Update Student Entry</a>
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-exit">Exit</a>
        </div>
    </div>

    <?php
    elseif ($action==='delete_confirm' && $edit_st):
    ?>
    <h2 class="page-title">Student Delete</h2>
    <p class="delete-warning">You are about to delete the following student entry:</p>
    <div class="delete-info-table">
        <div class="di-row"><span class="di-label">Student ID:</span>          <span class="di-val"><?=htmlspecialchars($edit_st['studid'])?></span></div>
        <div class="di-row"><span class="di-label">Student First Name:</span>  <span class="di-val"><?=htmlspecialchars($edit_st['studfirstname'])?></span></div>
        <div class="di-row"><span class="di-label">Student Middle Name:</span> <span class="di-val"><?=htmlspecialchars($edit_st['studmidname']??'')?></span></div>
        <div class="di-row"><span class="di-label">Student Last Name:</span>   <span class="di-val"><?=htmlspecialchars($edit_st['studlastname'])?></span></div>
    </div>
    <p class="delete-sub">Are you sure you want to delete this student entry?</p>
    <p class="delete-note">This entry is part of a high-level relationship in the database.<br>Deleting this entry may affect related data.</p>
    <form method="POST" action="students.php" class="delete-form">
        <input type="hidden" name="form_action" value="delete_proceed">
        <input type="hidden" name="del_id"      value="<?=$edit_st['studid']?>">
        <input type="hidden" name="coll_id"     value="<?=$coll_id?>">
        <input type="hidden" name="dept_id"     value="<?=$dept_id?>">
        <input type="hidden" name="prog_id"     value="<?=$prog_id?>">
        <button type="submit" class="btn-yes-delete">Yes, Delete Entry</button>
        <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-no-cancel">No, Cancel</a>
    </form>

    <?php
    elseif ($action==='deleted' && $saved):
    ?>
    <h2 class="page-title">Student Delete</h2>
    <p class="prog-msg prog-msg--success">✔ <?=htmlspecialchars($msg)?></p>
    <div class="entry-form readonly-form">
        <div class="form-row"><label>Student ID:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studid'])?>" readonly></div></div>
        <div class="form-row"><label>Student First Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studfirstname'])?>" readonly></div></div>
        <div class="form-row"><label>Student Middle Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studmidname']??'')?>" readonly></div></div>
        <div class="form-row"><label>Student Last Name:</label><div class="input-wrap"><input type="text" value="<?=htmlspecialchars($saved['studlastname'])?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=list&coll_id=<?=$coll_id?>&dept_id=<?=$dept_id?>&prog_id=<?=$prog_id?>" class="btn-save">Back to Student List</a>
        </div>
    </div>

    <?php endif; ?>
    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>

<script>
function showYearDrop(){ filterYearDrop(document.getElementById('fYear').value); }
function hideYearDrop(){ setTimeout(()=>document.getElementById('yearDrop')?.classList.remove('open'),160); }
function filterYearDrop(val){
    const drop=document.getElementById('yearDrop'); if(!drop)return;
    const q=val.toLowerCase(); let any=false;
    drop.querySelectorAll('li').forEach(li=>{
        const show=!q||li.textContent.includes(q);
        li.style.display=show?'':'none'; if(show)any=true;
    });
    drop.classList.toggle('open',any||val==='');
}
function pickYear(n){
    document.getElementById('fYear').value=n;
    document.getElementById('yearDrop').classList.remove('open');
}
</script>
</body>
</html>