<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: Final.php');
    exit;
}

$current_user = $_SESSION['user'];
$is_admin     = ($current_user === '');
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
} catch (PDOException $e) {
    die('<div style="padding:30px;color:red"><h2>DB Error</h2><p>'.$e->getMessage().'</p></div>');
}


if (!$is_admin) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: Final.php');
    exit;
}

// $host = 'localhost'; $dbname = 'usjr'; $dbuser = 'root'; $dbpass = '';
// try {
//     $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
// } catch (PDOException $e) {
//     die('<div style="padding:30px;color:red"><h2>DB Error</h2><p>'.$e->getMessage().'</p></div>');
// }

$pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
    `userid` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `usertype` VARCHAR(30) NOT NULL DEFAULT 'User',
    `userrole` VARCHAR(30) NOT NULL DEFAULT 'Viewer',
    `userpassword` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`userid`)
)");

$chk = $pdo->prepare("SELECT userid FROM users WHERE username='admin'");
$chk->execute();
if (!$chk->fetch()) {
    $pdo->prepare("INSERT INTO users (username,usertype,userrole,userpassword) VALUES('admin','Administrator','Administrator',?)")
        ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
}

function p($k,$fb=''){return $_POST[$k]??$fb;}

$action   = $_GET['action'] ?? 'dashboard';
$edit_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors   = [];
$msg      = ''; $msg_type = 'success';
$saved    = null;

$user_types = ['Administrator','User'];
$user_roles = ['Administrator','Creator','Updater','Viewer','Remover'];

$per_page = 7;
$page_num = isset($_GET['page_num']) ? max(1,(int)$_GET['page_num']) : 1;

if ($action==='delete' && $edit_id>0) {
    $self = $pdo->prepare("SELECT * FROM users WHERE userid=?"); $self->execute([$edit_id]);
    $del_user = $self->fetch();
    if ($del_user && $del_user['username']==='admin') {
        $msg='Cannot delete the admin account.'; $msg_type='error'; $action='list';
    } else {
        $pdo->prepare("DELETE FROM users WHERE userid=?")->execute([$edit_id]);
        $msg='User deleted successfully.'; $action='list';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='update') {
    $upd_id   = (int)p('edit_id');
    $username = trim(p('username'));
    $usertype = trim(p('usertype'));
    $userrole = trim(p('userrole'));
    $newpass  = trim(p('userpassword'));
    $confpass = trim(p('confirm_password'));

    if (!$username) $errors['username']='User Name entry cannot be empty';
    if (!$usertype) $errors['usertype']='User Type entry cannot be empty';
    if (!$userrole) $errors['userrole']='User Role entry cannot be empty';

    if ($newpass || $confpass) {
        if ($newpass !== $confpass) $errors['confirm_password']='Passwords do not match';
        elseif (strlen($newpass)<4)  $errors['userpassword']='Password must be at least 4 characters';
    }

    if (empty($errors)) {
        if ($newpass) {
            $pdo->prepare("UPDATE users SET username=?,usertype=?,userrole=?,userpassword=? WHERE userid=?")
                ->execute([$username,$usertype,$userrole,password_hash($newpass,PASSWORD_DEFAULT),$upd_id]);
        } else {
            $pdo->prepare("UPDATE users SET username=?,usertype=?,userrole=? WHERE userid=?")
                ->execute([$username,$usertype,$userrole,$upd_id]);
        }
        $msg='User settings updated successfully.';
        $saved=['username'=>$username,'usertype'=>$usertype,'userrole'=>$userrole];
        $action='updated';
    } else {
        $action='edit'; $edit_id=$upd_id;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='reset_password') {
    $upd_id = (int)p('edit_id');
    $pdo->prepare("UPDATE users SET userpassword=? WHERE userid=?")
        ->execute([password_hash('password',PASSWORD_DEFAULT),$upd_id]);
    $msg='Password reset to default (password).'; $msg_type='info';
    $action='edit'; $edit_id=$upd_id;
}

$upload_msgs = []; $upload_errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && p('form_action')==='upload_csv') {
    if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error']===UPLOAD_ERR_OK) {
        $tmp = $_FILES['csvfile']['tmp_name'];
        $fh  = fopen($tmp,'r');
        $first = true;
        while (($row=fgetcsv($fh))!==false) {
            if ($first) { $first=false; continue; }
            if (count($row)<4) continue;
            [$uname,$upass,$utype,$urole] = array_map('trim',$row);
            if (!$uname) continue;
            $chk2=$pdo->prepare("SELECT userid FROM users WHERE username=?"); $chk2->execute([$uname]);
            if ($chk2->fetch()) {
                $upload_errors[]="User already exists: $uname. Skipping insertion.";
            } else {
                $pdo->prepare("INSERT INTO users (username,usertype,userrole,userpassword) VALUES(?,?,?,?)")
                    ->execute([$uname,$utype,$urole,password_hash($upass,PASSWORD_DEFAULT)]);
                $upload_msgs[]="User $uname added successfully.";
            }
        }
        fclose($fh);
        if (empty($upload_errors)) $msg='All users added successfully.';
        else $msg='File processed with some errors. Please review the error messages.';
        $msg_type = empty($upload_errors)?'success':'error';
    } else {
        $msg='Please select a CSV file to upload.'; $msg_type='error';
    }
    $action='upload';
}

$edit_user=null;
if ($action==='edit' && $edit_id>0) {
    $s=$pdo->prepare("SELECT * FROM users WHERE userid=?"); $s->execute([$edit_id]); $edit_user=$s->fetch();
    if (!$edit_user) $action='list';
}

$users=[]; $total_users=0;
if ($action==='list') {
    $total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $offset = ($page_num-1)*$per_page;
    $users = $pdo->query("SELECT * FROM users ORDER BY userid LIMIT $per_page OFFSET $offset")->fetchAll();
}

$total_pages = max(1, ceil($total_users/$per_page));

$nav_links=[
    ['key'=>'home',        'label'=>'Home',        'href'=>'dashboard.php'],
    ['key'=>'schools',     'label'=>'Schools',     'href'=>'schools.php'],
    ['key'=>'departments', 'label'=>'Departments', 'href'=>'departments.php'],
    ['key'=>'programs',    'label'=>'Programs',    'href'=>'programs.php'],
    ['key'=>'students',    'label'=>'Students',    'href'=>'students.php'],
    ['key'=>'users',       'label'=>'Users',       'href'=>'users.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — USJ-R SMS v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="departments.css">
    <link rel="stylesheet" href="students.css">
    <link rel="stylesheet" href="users.css">
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
        <a href="<?= $link['href'] ?>" class="sidebar-link <?= $link['key']==='users'?'active':'' ?>">
            <?= $link['label'] ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">

    <?php if ($action==='dashboard'): ?>

    <div class="user-dashboard-wrap">
        <div class="user-dashboard-box">
            <h2 class="ud-title">User Dashboard</h2>
            <p class="ud-sub">Welcome to the User Dashboard. Here you can manage user accounts and settings.</p>
            <div class="ud-btns">
                <a href="?action=list" class="ud-btn">Manage Users</a>
                <a href="?action=upload" class="ud-btn">Add Users</a>
            </div>
        </div>
    </div>

    <?php elseif ($action==='list'): ?>

    <?php if ($msg): ?>
    <div class="prog-msg <?= $msg_type==='error'?'prog-msg--error':'prog-msg--success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h2 class="page-title">User List</h2>
        <a href="?action=dashboard" class="btn-back">↩ Back</a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>User Name</th>
                    <th>User Type</th>
                    <th>User Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                <tr><td colspan="5" class="empty-row">No users found.</td></tr>
                <?php else: foreach($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['userid']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['usertype']) ?></td>
                    <td><?= htmlspecialchars($u['userrole']) ?></td>
                    <td class="actions-cell">
                        <a href="?action=edit&id=<?= $u['userid'] ?>" class="btn-settings">⚙ Settings</a>
                        <?php if ($u['username']!=='admin'): ?>
                        <a href="?action=delete&id=<?= $u['userid'] ?>" class="btn-delete"
                           onclick="return confirm('Delete user <?= htmlspecialchars($u['username'],ENT_QUOTES) ?>?')">🗑 Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="list-footer">
        <p class="table-count">Total of: <?= $total_users ?> Users in the database</p>
        <div class="pagination">
            <?php if ($page_num > 1): ?>
            <a href="?action=list&page_num=<?= $page_num-1 ?>" class="pg-btn">Previous</a>
            <?php else: ?>
            <span class="pg-btn pg-disabled">Previous</span>
            <?php endif; ?>

            <?php if ($page_num < $total_pages): ?>
            <a href="?action=list&page_num=<?= $page_num+1 ?>" class="pg-btn pg-active">Next</a>
            <?php else: ?>
            <span class="pg-btn pg-disabled">Next</span>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($action==='edit' && $edit_user): ?>

    <?php if ($msg): ?>
    <div class="prog-msg <?= $msg_type==='info'?'prog-msg--info':($msg_type==='error'?'prog-msg--error':'prog-msg--success') ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <h2 class="page-title">User Update</h2>

    <!-- FIX: Reset Password is now a standalone form, completely separate from the update form -->
    <div class="reset-pw-row">
        <form method="POST" action="users.php" style="display:inline" onsubmit="return confirm('Reset password to default?')">
            <input type="hidden" name="form_action" value="reset_password">
            <input type="hidden" name="edit_id"     value="<?= $edit_user['userid'] ?>">
            <button type="submit" class="btn-reset-pw">Reset Password</button>
        </form>
    </div>

    <form method="POST" action="users.php" class="entry-form user-form" autocomplete="off">
        <input type="hidden" name="form_action" value="update">
        <input type="hidden" name="edit_id"     value="<?= $edit_user['userid'] ?>">

        <div class="user-section-title">User Account Details</div>

        <div class="form-row">
            <label>User Name:</label>
            <div class="input-wrap">
                <input type="text" name="username" value="<?= htmlspecialchars(p('username',$edit_user['username'])) ?>"
                       class="<?= isset($errors['username'])?'is-error':'' ?>">
            </div>
            <?php if(isset($errors['username'])): ?><span class="field-error"><?= $errors['username'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>User Type:</label>
            <div class="input-wrap">
                <select name="usertype" class="<?= isset($errors['usertype'])?'is-error':'' ?>">
                    <?php foreach($user_types as $t): ?>
                    <option value="<?=$t?>" <?= p('usertype',$edit_user['usertype'])===$t?'selected':'' ?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(isset($errors['usertype'])): ?><span class="field-error"><?= $errors['usertype'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>User Role:</label>
            <div class="input-wrap">
                <select name="userrole" class="<?= isset($errors['userrole'])?'is-error':'' ?>">
                    <?php foreach($user_roles as $r): ?>
                    <option value="<?=$r?>" <?= p('userrole',$edit_user['userrole'])===$r?'selected':'' ?>><?=$r?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(isset($errors['userrole'])): ?><span class="field-error"><?= $errors['userrole'] ?></span><?php endif; ?>
        </div>

        <div class="user-section-title" style="margin-top:16px">Password Settings</div>

        <div class="form-row">
            <label>User Password:</label>
            <div class="input-wrap">
                <input type="password" name="userpassword" value=""
                       class="<?= isset($errors['userpassword'])?'is-error':'' ?>" placeholder="Leave blank to keep current">
            </div>
            <?php if(isset($errors['userpassword'])): ?><span class="field-error"><?= $errors['userpassword'] ?></span><?php endif; ?>
        </div>

        <div class="form-row">
            <label>User Confirm Password:</label>
            <div class="input-wrap">
                <input type="password" name="confirm_password" value=""
                       class="<?= isset($errors['confirm_password'])?'is-error':'' ?>">
            </div>
            <?php if(isset($errors['confirm_password'])): ?><span class="field-error"><?= $errors['confirm_password'] ?></span><?php endif; ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn-save">Update User Settings</button>
            <button type="reset"  class="btn-reset">Reset Form</button>
            <a href="?action=list" class="btn-exit">Exit</a>
        </div>
    </form>

    <?php elseif ($action==='updated' && $saved): ?>

    <h2 class="page-title">User Update</h2>
    <p class="prog-msg prog-msg--success">✔ <?= htmlspecialchars($msg) ?></p>
    <div class="entry-form user-form readonly-form">
        <div class="form-row"><label>User Name:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved['username']) ?>" readonly></div></div>
        <div class="form-row"><label>User Type:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved['usertype']) ?>" readonly></div></div>
        <div class="form-row"><label>User Role:</label><div class="input-wrap"><input type="text" value="<?= htmlspecialchars($saved['userrole']) ?>" readonly></div></div>
        <div class="form-buttons">
            <a href="?action=list" class="btn-exit">Back to User List</a>
        </div>
    </div>

    <?php elseif ($action==='upload'): ?>

    <h2 class="page-title">Add Users From File</h2>

    <?php if ($msg): ?>
    <p class="prog-msg <?= $msg_type==='error'?'prog-msg--error':'prog-msg--success' ?>"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <form method="POST" action="users.php" enctype="multipart/form-data" class="upload-form">
        <input type="hidden" name="form_action" value="upload_csv">
        <div class="upload-row">
            <label class="btn-choose-file">
                Choose File
                <input type="file" name="csvfile" accept=".csv" style="display:none" onchange="document.getElementById('fname').textContent=this.files[0]?this.files[0].name:'No file chosen'">
            </label>
            <span id="fname" class="file-name">No file chosen</span>
            <span class="upload-hint">Select a CSV file to upload</span>
        </div>
        <div class="upload-btns">
            <button type="submit" class="btn-upload">⬆ Upload</button>
            <a href="?action=dashboard" class="btn-upload-exit">↩ Exit</a>
        </div>
    </form>

    <p class="csv-hint">CSV format: <code>username, password, usertype, userrole</code> (first row = header, will be skipped)</p>

    <?php if (!empty($upload_errors)): ?>
    <div class="upload-errors">
        <?php foreach($upload_errors as $e): ?>
        <div class="upload-err-line"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($upload_msgs)): ?>
    <div class="upload-success-list">
        <?php foreach($upload_msgs as $m): ?>
        <div class="upload-ok-line">✔ <?= htmlspecialchars($m) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>
</body>
</html>