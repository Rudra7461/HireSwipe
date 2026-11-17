<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.full_name, e.id as employer_id, e.company_name, e.location, e.company_logo, e.is_setup
    FROM users u
    LEFT JOIN employer_profiles e ON u.id = e.user_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$emp = $stmt->fetch();

if (!$emp || !$emp['employer_id'] || $emp['company_name'] === 'Pending Setup') {
    header("Location: employer_setup.php?step=1");
    exit;
}

$employerId = $emp['employer_id'];

// Dashboard metrics
$activeJobsStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND is_deleted = 0");
$activeJobsStmt->execute([$employerId]);
$activeJobsCount = $activeJobsStmt->fetchColumn();

$applicantsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM swipes s
    JOIN jobs j ON s.job_id = j.id
    WHERE j.employer_id = ? AND s.initiator = 'job_seeker' AND s.direction = 'interested'
");
$applicantsStmt->execute([$employerId]);
$applicantsCount = $applicantsStmt->fetchColumn();

$interviewsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews intv
    JOIN invitations i ON intv.invitation_id = i.id
    WHERE i.employer_id = ?
");
$interviewsStmt->execute([$employerId]);
$interviewsCount = $interviewsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Employer Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="employer_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Employer</span></a>
        <div class="nav-links">
            <a href="employer_dashboard.php" class="active">Dashboard</a>
            <a href="employer_home.php">Swipe Talent</a>
            <a href="post_job.php">Manage Jobs</a>
            <a href="employer_search.php">Talent Directory</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <?php if ($emp['company_logo']): ?>
                    <img src="<?= htmlspecialchars($emp['company_logo']) ?>" alt="Logo" class="avatar-lg" style="margin: 0; width: 76px; height: 76px;">
                <?php else: ?>
                    <div class="avatar-lg" style="margin: 0; width: 76px; height: 76px;"><?= strtoupper(substr($emp['company_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 800;"><?= htmlspecialchars($emp['company_name']) ?></h1>
                    <p style="color: var(--text-muted);">📍 <?= htmlspecialchars($emp['location'] ?: 'Location not set') ?> • Managed by <?= htmlspecialchars($emp['full_name']) ?></p>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="post_job.php" class="btn btn-auto">+ Post New Job</a>
                <a href="update_plan.php" class="btn btn-outline btn-auto">Upgrade Plan</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $activeJobsCount ?></h3>
                <p>Active Job Postings</p>
            </div>
            <div class="stat-card">
                <h3><?= $applicantsCount ?></h3>
                <p>Interested Candidates</p>
            </div>
            <div class="stat-card">
                <h3><?= $interviewsCount ?></h3>
                <p>Scheduled Interviews</p>
            </div>
        </div>

        <h2 style="margin-bottom: 1rem; font-size: 1.35rem;">Recruitment Workspace</h2>
        <div class="cards-grid">
            <a href="employer_home.php" class="action-tile">
                <span class="icon">🔥</span>
                <h4>Swipe Candidates</h4>
                <p>Review candidate cards one by one and match with top talent.</p>
            </a>
            <a href="employer_search.php" class="action-tile">
                <span class="icon">🔎</span>
                <h4>Search & Invite</h4>
                <p>Browse paginated candidate lists and send direct job invitations.</p>
            </a>
            <a href="post_job.php" class="action-tile">
                <span class="icon">📋</span>
                <h4>Manage Job Roles</h4>
                <p>Create, edit, or archive open positions at your company.</p>
            </a>
            <a href="interviews.php" class="action-tile">
                <span class="icon">🎥</span>
                <h4>Video Interviews</h4>
                <p>Launch instant Jitsi Meet video calls with matched candidates.</p>
            </a>
        </div>
    </div>
</body>
</html>