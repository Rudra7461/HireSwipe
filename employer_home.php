<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$empStmt = $pdo->prepare("SELECT id FROM employer_profiles WHERE user_id = ?");
$empStmt->execute([$_SESSION['user_id']]);
$employerId = $empStmt->fetchColumn();

$jobsStmt = $pdo->prepare("SELECT id, title FROM jobs WHERE employer_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
$jobsStmt->execute([$employerId]);
$activeJobs = $jobsStmt->fetchAll();

$selectedJobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : ($activeJobs[0]['id'] ?? null);

$candidate = null;
if ($selectedJobId) {
    $query = "
        SELECT p.*, u.full_name, u.email 
        FROM job_seeker_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.id NOT IN (
            SELECT job_seeker_id FROM swipes WHERE job_id = ? AND initiator = 'employer'
        )
        ORDER BY RAND() LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$selectedJobId]);
    $candidate = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Swipe Candidates</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="employer_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Employer</span></a>
        <div class="nav-links">
            <a href="employer_dashboard.php">Dashboard</a>
            <a href="employer_home.php" class="active">Swipe Talent</a>
            <a href="post_job.php">Manage Jobs</a>
            <a href="employer_search.php">Talent Directory</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="swipe-stage">
        <?php if (!empty($activeJobs)): ?>
            <div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                <label style="margin-bottom: 6px;">Swiping Candidates For Role:</label>
                <select onchange="window.location.href='employer_home.php?job_id=' + this.value">
                    <?php foreach ($activeJobs as $j): ?>
                        <option value="<?= $j['id'] ?>" <?= $j['id'] == $selectedJobId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($j['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (empty($activeJobs)): ?>
            <div class="empty-state">
                <h3 style="color: var(--text-main); margin-bottom: 0.5rem;">No Active Jobs</h3>
                <p style="margin-bottom: 1.25rem;">Post a job opening first to start swiping on candidates.</p>
                <a href="post_job.php" class="btn btn-auto">+ Post a Job</a>
            </div>
        <?php elseif ($candidate): ?>
            <div class="swipe-card">
                <?php if ($candidate['profile_picture']): ?>
                    <img src="<?= htmlspecialchars($candidate['profile_picture']) ?>" alt="Candidate" class="avatar-lg">
                <?php else: ?>
                    <div class="avatar-lg"><?= strtoupper(substr($candidate['full_name'], 0, 1)) ?></div>
                <?php endif; ?>

                <h2 style="font-size: 1.45rem; font-weight: 800; margin-bottom: 4px;">
                    <?= htmlspecialchars($candidate['full_name']) ?><?= $candidate['age'] ? ', ' . (int)$candidate['age'] : '' ?>
                </h2>
                <p style="color: var(--primary); font-weight: 700; margin-bottom: 1.25rem;">
                    <?= htmlspecialchars($candidate['qualification'] ?: 'Professional Candidate') ?>
                </p>

                <div class="badge-group" style="justify-content: center; margin-bottom: 1.25rem;">
                    <span class="badge">📍 <?= htmlspecialchars($candidate['location'] ?: 'N/A') ?></span>
                    <span class="badge">⭐ <?= (int)$candidate['experience_years'] ?> Yrs Exp</span>
                    <?php if ($candidate['preferred_employment_type']): ?>
                        <span class="badge">💼 <?= htmlspecialchars($candidate['preferred_employment_type']) ?></span>
                    <?php endif; ?>
                    <?php if ($candidate['expected_salary']): ?>
                        <span class="badge">💰 ₹<?= number_format((float)$candidate['expected_salary']) ?>/yr</span>
                    <?php endif; ?>
                </div>

                <?php if ($candidate['tickets']): ?>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem;">
                        <strong>Certifications:</strong> <?= htmlspecialchars($candidate['tickets']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($candidate['resume_path']): ?>
                    <a href="<?= htmlspecialchars($candidate['resume_path']) ?>" target="_blank" class="btn btn-secondary" style="margin-bottom: 0.5rem;">
                        📄 View Candidate PDF Resume
                    </a>
                <?php endif; ?>

                <div class="swipe-actions">
                    <button class="btn-circle reject" title="Pass" onclick="handleSwipe('rejected', <?= $candidate['id'] ?>)">✕</button>
                    <button class="btn-circle accept" title="Shortlist" onclick="handleSwipe('interested', <?= $candidate['id'] ?>)">✓</button>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">No More Candidates!</h2>
                <p style="margin-bottom: 1.25rem;">You have reviewed all available candidates for this job role.</p>
                <a href="employer_search.php" class="btn btn-auto">Browse Talent Directory</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function handleSwipe(direction, seekerId) {
            fetch('swipe_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_id: <?= (int)($selectedJobId ?? 0) ?>,
                    job_seeker_id: seekerId,
                    direction: direction,
                    initiator: 'employer'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.match) {
                        alert("🎉 Mutual Match! Both you and the candidate swiped right. Video interview created!");
                    }
                    window.location.reload();
                } else {
                    alert(data.error || 'Error saving swipe.');
                }
            });
        }
    </script>
</body>
</html>