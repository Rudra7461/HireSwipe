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

$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'invite') {
    $seekerId = (int)$_POST['seeker_id'];
    $jobId = (int)$_POST['job_id'];

    $checkStmt = $pdo->prepare("SELECT id FROM invitations WHERE employer_id = ? AND job_seeker_id = ? AND job_id = ?");
    $checkStmt->execute([$employerId, $seekerId, $jobId]);

    if ($checkStmt->fetch()) {
        $alert = "<div class='alert alert-error'>You have already invited this candidate to the selected job.</div>";
    } else {
        $invStmt = $pdo->prepare("INSERT INTO invitations (employer_id, job_seeker_id, job_id) VALUES (?, ?, ?)");
        if ($invStmt->execute([$employerId, $seekerId, $jobId])) {
            $alert = "<div class='alert alert-success'>Invitation sent to candidate!</div>";
        }
    }
}

$activeJobsStmt = $pdo->prepare("SELECT id, title FROM jobs WHERE employer_id = ? AND is_deleted = 0");
$activeJobsStmt->execute([$employerId]);
$employerJobs = $activeJobsStmt->fetchAll();

$activeTab = $_GET['tab'] ?? 'people';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Talent Directory</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="employer_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Employer</span></a>
        <div class="nav-links">
            <a href="employer_dashboard.php">Dashboard</a>
            <a href="employer_home.php">Swipe Talent</a>
            <a href="post_job.php">Manage Jobs</a>
            <a href="employer_search.php" class="active">Talent Directory</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1>Talent Directory & Activity</h1>
        </div>

        <?= $alert ?>

        <div class="tabs">
            <a href="?tab=people" class="tab-link <?= $activeTab === 'people' ? 'active' : '' ?>">People</a>
            <a href="?tab=job" class="tab-link <?= $activeTab === 'job' ? 'active' : '' ?>">Job</a>
            <a href="?tab=invited" class="tab-link <?= $activeTab === 'invited' ? 'active' : '' ?>">Invited</a>
            <a href="?tab=applied" class="tab-link <?= $activeTab === 'applied' ? 'active' : '' ?>">Applied</a>
        </div>

        <?php if ($activeTab === 'people'): ?>
            <?php
            $limit = 8;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $offset = ($page - 1) * $limit;

            $totalSeekers = (int)$pdo->query("SELECT COUNT(*) FROM job_seeker_profiles")->fetchColumn();
            $totalPages = (int)ceil($totalSeekers / $limit);

            $seekersStmt = $pdo->prepare("
                SELECT p.*, u.full_name 
                FROM job_seeker_profiles p 
                JOIN users u ON p.user_id = u.id 
                ORDER BY p.id DESC LIMIT :limit OFFSET :offset
            ");
            $seekersStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $seekersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $seekersStmt->execute();
            $seekers = $seekersStmt->fetchAll();
            ?>
            <?php if (empty($seekers)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($seekers as $seeker): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($seeker['full_name']) ?><?= $seeker['age'] ? ' (' . (int)$seeker['age'] . ')' : '' ?></h4>
                            <p>📍 <?= htmlspecialchars($seeker['location'] ?: 'India') ?> • 🎓 <?= htmlspecialchars($seeker['qualification'] ?: 'N/A') ?> • ⭐ <?= (int)$seeker['experience_years'] ?> Yrs Exp</p>
                        </div>
                        <?php if (!empty($employerJobs)): ?>
                            <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                                <input type="hidden" name="action" value="invite">
                                <input type="hidden" name="seeker_id" value="<?= $seeker['id'] ?>">
                                <select name="job_id" required style="width: auto; min-width: 180px;">
                                    <option value="">Select Job Role...</option>
                                    <?php foreach ($employerJobs as $j): ?>
                                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-auto">Invite</button>
                            </form>
                        <?php else: ?>
                            <a href="post_job.php" class="btn btn-outline btn-auto">Post Job to Invite</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?tab=people&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($activeTab === 'job'): ?>
            <?php if (empty($employerJobs)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($employerJobs as $job): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($job['title']) ?></h4>
                            <p>Active Job Listing</p>
                        </div>
                        <a href="post_job.php?edit_id=<?= $job['id'] ?>" class="btn btn-secondary btn-auto">Manage Job</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($activeTab === 'invited'): ?>
            <?php
            $invStmt = $pdo->prepare("
                SELECT i.*, j.title, u.full_name 
                FROM invitations i
                JOIN jobs j ON i.job_id = j.id
                JOIN job_seeker_profiles p ON i.job_seeker_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE i.employer_id = ?
                ORDER BY i.created_at DESC
            ");
            $invStmt->execute([$employerId]);
            $invitations = $invStmt->fetchAll();
            ?>
            <?php if (empty($invitations)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($invitations as $inv): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($inv['full_name']) ?></h4>
                            <p>Invited for role: <strong><?= htmlspecialchars($inv['title']) ?></strong></p>
                        </div>
                        <span class="badge" style="text-transform: uppercase;"><?= htmlspecialchars($inv['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($activeTab === 'applied'): ?>
            <?php
            $appStmt = $pdo->prepare("
                SELECT s.*, j.title, u.full_name, p.qualification
                FROM swipes s
                JOIN jobs j ON s.job_id = j.id
                JOIN job_seeker_profiles p ON s.job_seeker_id = p.id
                JOIN users u ON p.user_id = u.id
                WHERE j.employer_id = ? AND s.initiator = 'job_seeker' AND s.direction = 'interested'
                ORDER BY s.created_at DESC
            ");
            $appStmt->execute([$employerId]);
            $applications = $appStmt->fetchAll();
            ?>
            <?php if (empty($applications)): ?>
                <div class="empty-state">No data found</div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="list-row">
                        <div class="list-info">
                            <h4><?= htmlspecialchars($app['full_name']) ?></h4>
                            <p>Swiped Right on: <strong><?= htmlspecialchars($app['title']) ?></strong> • <?= htmlspecialchars($app['qualification'] ?: '') ?></p>
                        </div>
                        <a href="employer_home.php?job_id=<?= $app['job_id'] ?>" class="btn btn-auto">Review Candidate</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>