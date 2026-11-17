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

if (!$employerId) {
    header("Location: employer_setup.php?step=1");
    exit;
}

$message = '';
$editJob = null;

// Soft Delete
if (isset($_GET['delete_id'])) {
    $delStmt = $pdo->prepare("UPDATE jobs SET is_deleted = 1 WHERE id = ? AND employer_id = ?");
    if ($delStmt->execute([(int)$_GET['delete_id'], $employerId])) {
        $message = "Job listing archived successfully.";
    }
}

// Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobId = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $empType = $_POST['employment_type'] ?? 'Full-time';
    $exp = (int)($_POST['experience_required'] ?? 0);
    $salary = trim($_POST['salary_range'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $jobType = trim($_POST['job_type'] ?? '');
    $workplace = $_POST['workplace_type'] ?? 'On-site';

    if ($jobId) {
        $upd = $pdo->prepare("
            UPDATE jobs 
            SET title=?, description=?, employment_type=?, experience_required=?, salary_range=?, location=?, job_type=?, workplace_type=? 
            WHERE id=? AND employer_id=?
        ");
        $upd->execute([$title, $desc, $empType, $exp, $salary, $location, $jobType, $workplace, $jobId, $employerId]);
        $message = "Job updated successfully.";
    } else {
        $ins = $pdo->prepare("
            INSERT INTO jobs (employer_id, title, description, employment_type, experience_required, salary_range, location, job_type, workplace_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$employerId, $title, $desc, $empType, $exp, $salary, $location, $jobType, $workplace]);
        $message = "New job posted successfully.";
    }
}

// Load job for editing
if (isset($_GET['edit_id'])) {
    $edStmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND employer_id = ? AND is_deleted = 0");
    $edStmt->execute([(int)$_GET['edit_id'], $employerId]);
    $editJob = $edStmt->fetch();
}

$jobsStmt = $pdo->prepare("SELECT * FROM jobs WHERE employer_id = ? AND is_deleted = 0 ORDER BY created_at DESC");
$jobsStmt->execute([$employerId]);
$jobs = $jobsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Manage Jobs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="employer_dashboard.php" class="navbar-brand">HireSwipe <span class="role-pill">Employer</span></a>
        <div class="nav-links">
            <a href="employer_dashboard.php">Dashboard</a>
            <a href="employer_home.php">Swipe Talent</a>
            <a href="post_job.php" class="active">Manage Jobs</a>
            <a href="employer_search.php">Talent Directory</a>
            <a href="interviews.php">Interviews</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1><?= $editJob ? 'Edit Job Listing' : 'Post a New Job' ?></h1>
            <a href="employer_home.php" class="btn btn-outline btn-auto">Go to Candidate Swipe →</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 2.5rem;">
            <form method="POST" action="post_job.php">
                <input type="hidden" name="job_id" value="<?= htmlspecialchars($editJob['id'] ?? '') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Job Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($editJob['title'] ?? '') ?>" placeholder="e.g. Junior SOC Analyst" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($editJob['location'] ?? '') ?>" placeholder="e.g. Ahmedabad, Gujarat" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Employment Type</label>
                        <select name="employment_type" required>
                            <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $type): ?>
                                <option value="<?= $type ?>" <?= (($editJob['employment_type'] ?? '') === $type) ? 'selected' : '' ?>><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Workplace Type</label>
                        <select name="workplace_type" required>
                            <?php foreach (['On-site', 'Remote', 'Hybrid'] as $wp): ?>
                                <option value="<?= $wp ?>" <?= (($editJob['workplace_type'] ?? '') === $wp) ? 'selected' : '' ?>><?= $wp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Job Category / Department</label>
                        <input type="text" name="job_type" value="<?= htmlspecialchars($editJob['job_type'] ?? '') ?>" placeholder="e.g. Cybersecurity / Engineering" required>
                    </div>
                    <div class="form-group">
                        <label>Experience Required (Years)</label>
                        <input type="number" name="experience_required" min="0" value="<?= htmlspecialchars($editJob['experience_required'] ?? '0') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Salary Package / Range</label>
                    <input type="text" name="salary_range" value="<?= htmlspecialchars($editJob['salary_range'] ?? '') ?>" placeholder="e.g. 6 - 7 LPA" required>
                </div>

                <div class="form-group">
                    <label>Role Description</label>
                    <textarea name="description" rows="4" placeholder="Outline core duties, tech stack, and qualifications..." required><?= htmlspecialchars($editJob['description'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-auto"><?= $editJob ? 'Save Changes' : 'Publish Job' ?></button>
                    <?php if ($editJob): ?>
                        <a href="post_job.php" class="btn btn-secondary btn-auto">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 style="margin-bottom: 1rem;">Active Job Listings (<?= count($jobs) ?>)</h2>
        <?php if (empty($jobs)): ?>
            <div class="empty-state">No active jobs found. Use the form above to publish your first opening.</div>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <div class="list-row">
                    <div class="list-info">
                        <h4><?= htmlspecialchars($job['title']) ?></h4>
                        <p>
                            📍 <?= htmlspecialchars($job['location']) ?> • 
                            🏢 <?= htmlspecialchars($job['workplace_type']) ?> (<?= htmlspecialchars($job['employment_type']) ?>) • 
                            ⭐ <?= (int)$job['experience_required'] ?>+ Yrs • 
                            💰 <?= htmlspecialchars($job['salary_range']) ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="post_job.php?edit_id=<?= $job['id'] ?>" class="btn btn-secondary btn-auto">Edit</a>
                        <a href="post_job.php?delete_id=<?= $job['id'] ?>" class="btn btn-danger btn-auto" onclick="return confirm('Archive this job listing?');">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>