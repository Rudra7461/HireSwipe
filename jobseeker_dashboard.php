<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.full_name, p.id as seeker_id, p.profile_picture, p.qualification, p.location, p.experience_years, p.age
    FROM users u 
    LEFT JOIN job_seeker_profiles p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['seeker_id'] || empty($user['age'])) {
    header("Location: seeker_setup.php?step=1");
    exit;
}

$seekerId = $user['seeker_id'];

$appliedStmt = $pdo->prepare("SELECT COUNT(*) FROM swipes WHERE job_seeker_id = ? AND initiator = 'job_seeker' AND direction = 'interested'");
$appliedStmt->execute([$seekerId]);
$appliedCount = $appliedStmt->fetchColumn();

$invitesStmt = $pdo->prepare("SELECT COUNT(*) FROM invitations WHERE job_seeker_id = ? AND status = 'pending'");
$invitesStmt->execute([$seekerId]);
$pendingInvites = $invitesStmt->fetchColumn();

$interviewsStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM interviews intv
    JOIN invitations i ON intv.invitation_id = i.id
    WHERE i.job_seeker_id = ?
");
$interviewsStmt->execute([$seekerId]);
$upcomingInterviews = $interviewsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Job Seeker Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="jobseeker_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Candidate</span></a>
        <div class="nav-links">
            <a href="jobseeker_dashboard.php" class="active">Dashboard</a>
            <a href="seeker_home.php">Swipe Jobs</a>
            <a href="seeker_apply.php">Applications & Invites</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <?php if ($user['profile_picture']): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar" class="avatar-lg" style="margin: 0; width: 76px; height: 76px;">
                <?php else: ?>
                    <div class="avatar-lg" style="margin: 0; width: 76px; height: 76px;"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 800;">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                    <p style="color: var(--text-muted);"><?= htmlspecialchars($user['qualification'] ?: 'Candidate') ?> • 📍 <?= htmlspecialchars($user['location'] ?: 'India') ?> • <?= (int)$user['experience_years'] ?> Yrs Exp</p>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="seeker_home.php" class="btn btn-auto">🔥 Start Swiping</a>
                <a href="update_plan.php" class="btn btn-outline btn-auto">Upgrade Plan</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $appliedCount ?></h3>
                <p>Jobs Swiped Right</p>
            </div>
            <div class="stat-card">
                <h3><?= $pendingInvites ?></h3>
                <p>Pending Invitations</p>
            </div>
            <div class="stat-card">
                <h3><?= $upcomingInterviews ?></h3>
                <p>Scheduled Interviews</p>
            </div>
        </div>

        <h2 style="margin-bottom: 1rem; font-size: 1.35rem;">Career Command Center</h2>
        <div class="cards-grid">
            <a href="seeker_home.php" class="action-tile">
                <span class="icon">🔥</span>
                <h4>Discover Jobs</h4>
                <p>Swipe through personalized job cards tailored to your profile.</p>
            </a>
            <a href="seeker_apply.php" class="action-tile">
                <span class="icon">📩</span>
                <h4>Invites & Applications</h4>
                <p>Accept or decline direct employer invitations and track swipes.</p>
            </a>
            <a href="interviews.php" class="action-tile">
                <span class="icon">🎥</span>
                <h4>Video Interviews</h4>
                <p>Join Jitsi Meet video rooms for your accepted job matches.</p>
            </a>
            <a href="profile.php" class="action-tile">
                <span class="icon">⚙️</span>
                <h4>Profile & Role Switch</h4>
                <p>Upload an updated PDF resume or switch to Employer mode.</p>
            </a>
        </div>
    </div>
</body>
</html>