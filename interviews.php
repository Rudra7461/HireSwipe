<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

if ($role === 'employer') {
    $stmt = $pdo->prepare("SELECT id FROM employer_profiles WHERE user_id = ?");
} else {
    $stmt = $pdo->prepare("SELECT id FROM job_seeker_profiles WHERE user_id = ?");
}
$stmt->execute([$userId]);
$profileId = $stmt->fetchColumn();

// Auto-generate Jitsi Meet links for any accepted invitations or mutual matches
$autoGenQuery = "
    INSERT IGNORE INTO interviews (invitation_id, scheduled_at, meeting_link)
    SELECT i.id, DATE_ADD(NOW(), INTERVAL 1 DAY), CONCAT('https://meet.jit.si/HireSwipe-', SUBSTRING(MD5(RAND()), 1, 10))
    FROM invitations i
    LEFT JOIN interviews intv ON i.id = intv.invitation_id
    WHERE i.status = 'accepted' AND intv.id IS NULL
";
$pdo->exec($autoGenQuery);

if ($role === 'employer') {
    $fetchIntv = $pdo->prepare("
        SELECT intv.scheduled_at, intv.meeting_link, j.title, u.full_name as other_party, p.profile_picture as avatar
        FROM interviews intv
        JOIN invitations i ON intv.invitation_id = i.id
        JOIN jobs j ON i.job_id = j.id
        JOIN job_seeker_profiles p ON i.job_seeker_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE i.employer_id = ?
        ORDER BY intv.scheduled_at ASC
    ");
} else {
    $fetchIntv = $pdo->prepare("
        SELECT intv.scheduled_at, intv.meeting_link, j.title, e.company_name as other_party, e.company_logo as avatar
        FROM interviews intv
        JOIN invitations i ON intv.invitation_id = i.id
        JOIN jobs j ON i.job_id = j.id
        JOIN employer_profiles e ON i.employer_id = e.id
        WHERE i.job_seeker_id = ?
        ORDER BY intv.scheduled_at ASC
    ");
}
$fetchIntv->execute([$profileId]);
$interviews = $fetchIntv->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Video Interviews</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="<?= $role === 'employer' ? 'employer_dashboard.php' : 'jobseeker_dashboard.php' ?>" class="navbar-brand">
            HireSwipe <span class="role-pill"><?= $role === 'employer' ? 'Employer' : 'Candidate' ?></span>
        </a>
        <div class="nav-links">
            <a href="<?= $role === 'employer' ? 'employer_dashboard.php' : 'jobseeker_dashboard.php' ?>">Dashboard</a>
            <a href="<?= $role === 'employer' ? 'employer_home.php' : 'seeker_home.php' ?>">Swipe</a>
            <a href="<?= $role === 'employer' ? 'employer_search.php' : 'seeker_apply.php' ?>">Directory / Activity</a>
            <a href="interviews.php" class="active">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1>Scheduled Video Interviews</h1>
        </div>

        <?php if (empty($interviews)): ?>
            <div class="empty-state">
                <h3 style="color: var(--text-main); margin-bottom: 0.5rem;">No Interviews Scheduled Yet</h3>
                <p>When a mutual right-swipe occurs or an invitation is accepted, your Jitsi Meet link will appear here automatically.</p>
            </div>
        <?php else: ?>
            <?php foreach ($interviews as $interview): ?>
                <div class="list-row">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <?php if ($interview['avatar']): ?>
                            <img src="<?= htmlspecialchars($interview['avatar']) ?>" class="avatar-lg" style="margin: 0; width: 56px; height: 56px;" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-lg" style="margin: 0; width: 56px; height: 56px; font-size: 1.2rem;"><?= strtoupper(substr($interview['other_party'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="list-info">
                            <h4><?= htmlspecialchars($interview['other_party']) ?></h4>
                            <p>Role: <strong><?= htmlspecialchars($interview['title']) ?></strong> • 📅 <?= date('M d, Y - h:i A', strtotime($interview['scheduled_at'])) ?></p>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars($interview['meeting_link']) ?>" target="_blank" class="btn btn-auto">🎥 Join Jitsi Meet</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>