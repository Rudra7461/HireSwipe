<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
    header("Location: login.php");
    exit;
}

$seekStmt = $pdo->prepare("SELECT id FROM job_seeker_profiles WHERE user_id = ?");
$seekStmt->execute([$_SESSION['user_id']]);
$seekerId = $seekStmt->fetchColumn();

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invitation_id'], $_POST['status'])) {
    $invId = (int)$_POST['invitation_id'];
    $status = $_POST['status'] === 'accepted' ? 'accepted' : 'declined';

    $updStmt = $pdo->prepare("UPDATE invitations SET status = ? WHERE id = ? AND job_seeker_id = ?");
    if ($updStmt->execute([$status, $invId, $seekerId])) {
        $alert = "<div class='alert alert-success'>Invitation " . htmlspecialchars($status) . "!</div>";
    }
}

$activeTab = $_GET['tab'] ?? 'invited';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Applications & Invitations</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="jobseeker_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Candidate</span></a>
        <div class="nav-links">
            <a href="jobseeker_dashboard.php">Dashboard</a>
            <a href="seeker_home.php">Swipe Jobs</a>
            <a href="seeker_apply.php" class="active">Applications & Invites</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1>My Applications & Invitations</h1>
        </div>

        <?= $alert ?>

        <div class="tabs">
            <a href="?tab=invited" class="tab-link <?= $activeTab === 'invited' ? 'active' : '' ?>">Invited</a>
            <a href="?tab=applied" class="tab-link <?= $activeTab === 'applied' ? 'active' : '' ?>">Applied</a>
            <a href="?tab=more_jobs" class="tab-link <?= $activeTab === 'more_jobs' ? 'active' : '' ?>">More jobs for you</a>
        </div>

        <?php if ($activeTab === 'invited'): ?>
            <?php
            $invStmt = $pdo->prepare("
                SELECT i.*, j.title, j.salary_range, j.location, e.company_name 
                FROM invitations i
                JOIN jobs j ON i.job_id = j.id
                JOIN employer_profiles e ON i.employer_id = e.id
                WHERE i.job_seeker_id = ?
                ORDER BY i.created_at DESC
            ");
            $invStmt->execute([$seekerId]);
            $invitations = $invStmt->fetchAll();
            ?>
            <?php if (empty($invitations)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($invitations as $inv): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($inv['title']) ?> @ <?= htmlspecialchars($inv['company_name']) ?></h4>
                            <p>📍 <?= htmlspecialchars($inv['location'] ?: 'Remote') ?> • 💰 <?= htmlspecialchars($inv['salary_range'] ?: 'Competitive') ?></p>
                        </div>
                        <?php if ($inv['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 10px;">
                                <form method="POST">
                                    <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                                    <input type="hidden" name="status" value="accepted">
                                    <button type="submit" class="btn btn-success btn-auto">Accept</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                                    <input type="hidden" name="status" value="declined">
                                    <button type="submit" class="btn btn-danger btn-auto">Decline</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="badge" style="text-transform: uppercase;"><?= htmlspecialchars($inv['status']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($activeTab === 'applied'): ?>
            <?php
            $appStmt = $pdo->prepare("
                SELECT s.*, j.title, j.location, e.company_name 
                FROM swipes s
                JOIN jobs j ON s.job_id = j.id
                JOIN employer_profiles e ON j.employer_id = e.id
                WHERE s.job_seeker_id = ? AND s.initiator = 'job_seeker' AND s.direction = 'interested'
                ORDER BY s.created_at DESC
            ");
            $appStmt->execute([$seekerId]);
            $applications = $appStmt->fetchAll();
            ?>
            <?php if (empty($applications)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($app['title']) ?></h4>
                            <p><?= htmlspecialchars($app['company_name']) ?> • 📍 <?= htmlspecialchars($app['location'] ?: 'Remote') ?></p>
                        </div>
                        <span class="badge" style="background: var(--primary-light); color: var(--primary-hover);">Applied ✓</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($activeTab === 'more_jobs'): ?>
            <?php
            $moreStmt = $pdo->prepare("
                SELECT j.*, e.company_name 
                FROM jobs j
                JOIN employer_profiles e ON j.employer_id = e.id
                WHERE j.is_deleted = 0 
                AND j.id NOT IN (
                    SELECT job_id FROM swipes WHERE job_seeker_id = ? AND initiator = 'job_seeker'
                )
                ORDER BY RAND() LIMIT 10
            ");
            $moreStmt->execute([$seekerId]);
            $moreJobs = $moreStmt->fetchAll();
            ?>
            <?php if (empty($moreJobs)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($moreJobs as $job): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($job['title']) ?></h4>
                            <p><?= htmlspecialchars($job['company_name']) ?> • 📍 <?= htmlspecialchars($job['location'] ?: 'Remote') ?></p>
                        </div>
                        <a href="seeker_home.php" class="btn btn-auto">Swipe Now →</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>