<?php
session_start();

$error = '';

// Database connection
$host = 'localhost'; $dbname = 'usjr'; $dbuser = 'root'; $dbpass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('<div style="padding:30px;color:red"><h2>DB Error</h2><p>'.htmlspecialchars($e->getMessage()).'</p></div>');
}

// mo handle sa log in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $st = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $st->execute([$username]);
    $user = $st->fetch();

    if ($user && password_verify($password, $user['userpassword'])) {
        $_SESSION['user'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$accounts = $pdo->query("SELECT username FROM users ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: Final.php');
    exit;
}

$logged_in    = isset($_SESSION['user']);
$current_user = $logged_in ? $_SESSION['user'] : '';

$modules = [
    ['icon' => '🏫', 'title' => 'Schools',     'desc' => 'Manage school information and details.',   'link' => '#schools'],
    ['icon' => '🏢', 'title' => 'Departments', 'desc' => 'Organize departments within schools.',      'link' => '#departments'],
    ['icon' => '🎓', 'title' => 'Programs',    'desc' => 'Manage academic programs and courses.',     'link' => '#programs'],
    ['icon' => '👥', 'title' => 'Students',    'desc' => 'Manage student records and information.',   'link' => '#students'],
];

$getting_started = [
    'Log in with your credentials.',
    'Navigate to any section using the sidebar menu.',
    'View, create, update, or delete records as needed.',
    'Contact administrator for access inquiries.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USJ-R School Management System v1.01</title>
    <link rel="stylesheet" href="final.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">
        USJ-R School Management System <span class="version">v1.01</span>
    </div>

    <div class="navbar-right">
        <?php if ($logged_in): ?>
            <span class="nav-user">
                <span class="user-dot"></span>
                <?= htmlspecialchars($current_user) ?>
            </span>
            <a href="?logout=1" class="btn-nav-logout">Logout</a>

        <?php else: ?>
            <form method="POST" class="navbar-login-form">
                <input type="hidden" name="action" value="login">

                <label class="nav-field-label">Username:</label>
                <div class="nav-input-wrap">
                    <input
                        type="text"
                        name="username"
                        id="navUsername"
                        class="nav-input<?= $error ? ' input-error' : '' ?>"
                        autocomplete="off"
                        onfocus="showDrop()"
                        onblur="hideDrop()"
                        oninput="filterDrop(this.value)"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                    <ul class="nav-user-dropdown" id="navDrop">
                        <?php foreach ($accounts as $acc): ?>
                            <li onmousedown="pickUser('<?= htmlspecialchars($acc, ENT_QUOTES) ?>')"><?= htmlspecialchars($acc) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <label class="nav-field-label">Password:</label>
                <input
                    type="password"
                    name="password"
                    id="navPassword"
                    class="nav-input nav-input-pw<?= $error ? ' input-error' : '' ?>"
                >

                <button type="submit" class="btn-nav-login">Login</button>
            </form>
        <?php endif; ?>
    </div>
</nav>

<?php if ($error): ?>
<div class="error-bar" id="errBar">
    ⚠ <?= htmlspecialchars($error) ?>
    <button onclick="document.getElementById('errBar').remove()">✕</button>
</div>
<?php endif; ?>

<header class="hero">
    <div class="hero-content">
        <h1 class="hero-title">Welcome to USJ-R School Management System</h1>
        <p class="hero-sub">Manage your school's operations efficiently</p>
    </div>
</header>

<main class="main-content">

    <section class="section" id="quick-access">
        <h2 class="section-title">Quick Access</h2>
        <div class="card-grid">
            <?php foreach ($modules as $mod): ?>
            <a href="<?= htmlspecialchars($mod['link']) ?>" class="module-card">
                <div class="module-icon"><?= $mod['icon'] ?></div>
                <h3 class="module-title"><?= htmlspecialchars($mod['title']) ?></h3>
                <p class="module-desc"><?= htmlspecialchars($mod['desc']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section" id="getting-started">
        <h2 class="section-title">Getting Started</h2>
        <div class="getting-started-box">
            <ol class="gs-list">
                <?php foreach ($getting_started as $step): ?>
                    <li><?= htmlspecialchars($step) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

</main>

<footer class="footer">
    <p>© <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos</p>
</footer>

<script>
const allUsers = <?= json_encode($accounts) ?>;

function showDrop() {
    filterDrop(document.getElementById('navUsername').value);
}
function hideDrop() {
    setTimeout(() => document.getElementById('navDrop').classList.remove('open'), 160);
}
function filterDrop(val) {
    const items = document.querySelectorAll('#navDrop li');
    const q = val.toLowerCase();
    let any = false;
    items.forEach(li => {
        const show = li.textContent.toLowerCase().includes(q);
        li.style.display = show ? '' : 'none';
        if (show) any = true;
    });
    document.getElementById('navDrop').classList.toggle('open', any);
}
function pickUser(name) {
    document.getElementById('navUsername').value = name;
    document.getElementById('navDrop').classList.remove('open');
    document.getElementById('navPassword').focus();
}

const eb = document.getElementById('errBar');
if (eb) setTimeout(() => eb.remove(), 4000);
</script>
</body>
</html>