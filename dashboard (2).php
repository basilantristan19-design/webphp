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

$page = $_GET['page'] ?? 'home';

$modules = [
    [
        'key'   => 'schools',
        'icon'  => '🏫',
        'title' => 'Schools',
        'desc'  => 'Manage school information and details',
        'btn'   => 'View Schools',
        'color' => 'green',
        'href'  => 'schools.php',
    ],
    [
        'key'   => 'departments',
        'icon'  => '🏢',
        'title' => 'Departments',
        'desc'  => 'Organize departments within schools',
        'btn'   => 'View Departments',
        'color' => 'green',
        'href'  => 'departments.php',
    ],
    [
        'key'   => 'programs',
        'icon'  => '🎓',
        'title' => 'Programs',
        'desc'  => 'Manage academic programs and courses',
        'btn'   => 'View Programs',
        'color' => 'green',
        'href' => 'programs.php',
    ],
    [
        'key'   => 'students',
        'icon'  => '👥',
        'title' => 'Students',
        'desc'  => 'Manage student records and enrollment',
        'btn'   => 'View Students',
        'color' => 'green',
        'href' => 'students.php',
    ],
    [
        'key'   => 'users',
        'icon'  => '⚙️',
        'title' => 'User Management',
        'desc'  => 'Manage system users and permissions',
        'btn'   => 'Manage Users',
        'color' => 'gold',
        'admin' => true,
        'href' => 'users.php',
    ],
];

$nav_links = [
    ['key' => 'home',        'label' => 'Home'],
    ['key' => 'schools',     'label' => 'Schools', 'href' => 'schools.php'],
    ['key' => 'departments', 'label' => 'Departments','href' => 'departments.php'],
    ['key' => 'programs',    'label' => 'Programs', 'href' => 'programs.php'],
    ['key' => 'students',    'label' => 'Students', 'href' => 'students.php'],
    ['key' => 'users',       'label' => 'Users', 'admin' => true, 'href' => 'users.php',],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USJ-R School Management System v1.01</title>
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<nav class="topbar">
    <div class="topbar-brand">
        USJ-R School Management System <span class="version">v1.01</span>
    </div>
    <div class="topbar-right">
        <span class="topbar-user">
            You are logged in as: <strong><?= htmlspecialchars($current_user) ?></strong>
        </span>
        <span class="user-avatar">👤</span>
        <a href="?logout=1" class="btn-topbar-logout">Logout</a>
    </div>
</nav>

<div class="layout">

    <!-- mao ni siya para sa sidebar -->
    <aside class="sidebar">
        <?php foreach ($nav_links as $link):
            if (!empty($link['admin']) && !$is_admin) continue;
        ?>
        <a href="<?= !empty($link['href']) ? $link['href'] : ('?page=' . $link['key']) ?>"
           class="sidebar-link <?= $page === $link['key'] ? 'active' : '' ?>">
            <?= htmlspecialchars($link['label']) ?>
        </a>
        <?php endforeach; ?>
    </aside>

    <main class="main">

        <div class="hero">
            <div class="hero-text">
                <h1 class="hero-title">Welcome to USJ-R School Management System</h1>
                <p class="hero-greeting">Hello, <?= htmlspecialchars($current_user) ?>! 👋</p>
                <p class="hero-sub">Manage your school's operations efficiently</p>
            </div>
        </div>

        <!-- mao ni para sa Quick Access -->
        <section class="qa-section">
            <h2 class="qa-title">Quick Access</h2>
            <div class="qa-grid">
                <?php foreach ($modules as $mod):
                    if (!empty($mod['admin']) && !$is_admin) continue;
                ?>
                <div class="qa-card <?= !empty($mod['admin']) ? 'qa-card--admin' : '' ?>">
                    <div class="qa-icon"><?= $mod['icon'] ?></div>
                    <h3 class="qa-card-title"><?= htmlspecialchars($mod['title']) ?></h3>
                    <p class="qa-card-desc"><?= htmlspecialchars($mod['desc']) ?></p>
                    <?php
                        $mod_href = !empty($mod['href']) ? $mod['href'] : ('?page=' . $mod['key']);
                    ?>
                    <a href="<?= $mod_href ?>"
                       class="qa-btn qa-btn--<?= $mod['color'] ?>">
                        <?= htmlspecialchars($mod['btn']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Page content placeholder -->
        <?php if ($page !== 'home'): ?>
        <section class="page-content">
            <h2 class="page-content-title"><?= htmlspecialchars(ucfirst($page)) ?></h2>
            <p class="page-content-placeholder">Content for <strong><?= htmlspecialchars($page) ?></strong> will be displayed here.</p>
        </section>
        <?php endif; ?>

    </main>
</div>

<footer class="footer">
    © <?= date('Y') ?> USJ-R School Management System v1.01 &mdash; University of San Jose-Recoletos
</footer>

</body>
</html>