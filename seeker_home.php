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

if (!$seekerId) {
    header("Location: seeker_setup.php?step=1");
    exit;
}

$query = "
    SELECT j.*, e.company_name, e.company_logo 
    FROM jobs j
    JOIN employer_profiles e ON j.employer_id = e.id
    WHERE j.is_deleted = 0 
    AND j.id NOT IN (
        SELECT job_id FROM swipes WHERE job_seeker_id = ? AND initiator = 'job_seeker'
    )
    ORDER BY RAND() LIMIT 1
";
$stmt = $pdo->prepare($query);
$stmt->execute([$seekerId]);
$job = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Swipe Jobs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="jobseeker_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Candidate</span></a>
        <div class="nav-links">
            <a href="jobseeker_dashboard.php">Dashboard</a>
            <a href="seeker_home.php" class="active">Swipe Jobs</a>
            <a href="seeker_apply.php">Applications & Invites</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="swipe-stage">
        <?php if ($job): ?>
            <div class="swipe-card">
                <?php if ($job['company_logo']): ?>
                    <img src="<?= htmlspecialchars($job['company_logo']) ?>" alt="Logo" class="avatar-lg">
                <?php else: ?>
                    <div class="avatar-lg"><?= strtoupper(substr($job['company_name'], 0, 1)) ?></div>
                <?php endif; ?>

                <h2 style="font-size: 1.45rem; font-weight: 800; margin-bottom: 4px;"><?= htmlspecialchars($job['title']) ?></h2>
                <p style="color: var(--primary); font-weight: 700; font-size: 1.05rem; margin-bottom: 1.25rem;"><?= htmlspecialchars($job['company_name']) ?></p>

                <div class="badge-group" style="justify-content: center; margin-bottom: 1.5rem;">
                    <span class="badge">📍 <?= htmlspecialchars($job['location'] ?: 'Remote') ?></span>
                    <span class="badge">💼 <?= htmlspecialchars($job['employment_type']) ?></span>
                    <span class="badge">🏢 <?= htmlspecialchars($job['workplace_type']) ?></span>
                    <span class="badge">⭐ <?= (int)$job['experience_required'] ?>+ Yrs</span>
                    <?php if ($job['salary_range']): ?>
                        <span class="badge">💰 <?= htmlspecialchars($job['salary_range']) ?></span>
                    <?php endif; ?>
                </div>

                <div style="text-align: left; background: var(--bg-body); padding: 1.15rem; border-radius: var(--radius-md); border: 1px solid var(--border); font-size: 0.94rem; color: var(--text-main); max-height: 200px; overflow-y: auto;">
                    <?= nl2br(htmlspecialchars($job['description'])) ?>
                </div>

                <div class="swipe-actions">
                    <button class="btn-circle reject" title="Reject" onclick="handleSwipe('rejected', <?= $job['id'] ?>)">✕</button>
                    <button class="btn-circle accept" title="Accept" onclick="handleSwipe('interested', <?= $job['id'] ?>)">✓</button>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">You're All Caught Up!</h2>
                <p style="margin-bottom: 1.5rem;">You have reviewed every active job opening right now.</p>
                <a href="seeker_apply.php" class="btn btn-auto">View My Applications</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function handleSwipe(direction, jobId) {
            fetch('swipe_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_id: jobId,
                    job_seeker_id: <?= (int)$seekerId ?>,
                    direction: direction,
                    initiator: 'job_seeker'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.match) {
                        alert("🎉 It's a Mutual Match! An interview has been automatically scheduled.");
                    }
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to record swipe.');
                }
            });
        }
    </script>
</body>
</html>