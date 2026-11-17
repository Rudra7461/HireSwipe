<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Switch User Role Handler
if (isset($_GET['switch_role'])) {
    $targetRole = ($role === 'employer') ? 'job_seeker' : 'employer';

    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$targetRole, $userId]);
    $_SESSION['role'] = $targetRole;

    if ($targetRole === 'employer') {
        $check = $pdo->prepare("SELECT id, company_name FROM employer_profiles WHERE user_id = ?");
        $check->execute([$userId]);
        $prof = $check->fetch();
        if ($prof && $prof['company_name'] !== 'Pending Setup') {
            header("Location: employer_dashboard.php");
        } else {
            header("Location: employer_setup.php?step=1");
        }
    } else {
        $check = $pdo->prepare("SELECT id, age FROM job_seeker_profiles WHERE user_id = ?");
        $check->execute([$userId]);
        $prof = $check->fetch();
        if ($prof && !empty($prof['age'])) {
            header("Location: jobseeker_dashboard.php");
        } else {
            header("Location: seeker_setup.php?step=1");
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    if (!empty($fullName)) {
        $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")->execute([$fullName, $userId]);
    }

    if ($role === 'employer') {
        $companyName = trim($_POST['company_name'] ?? '');
        if (!empty($companyName)) {
            $pdo->prepare("UPDATE employer_profiles SET company_name = ? WHERE user_id = ?")->execute([$companyName, $userId]);
        }
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/logos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $path = $uploadDir . uniqid('logo_') . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $path);
                $pdo->prepare("UPDATE employer_profiles SET company_logo = ? WHERE user_id = ?")->execute([$path, $userId]);
            } else {
                $error = "Invalid logo image format.";
            }
        }
    } else {
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/cvs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['cv']['tmp_name']);
            if ($ext === 'pdf' && $mime === 'application/pdf') {
                $path = $uploadDir . uniqid('cv_') . '.pdf';
                move_uploaded_file($_FILES['cv']['tmp_name'], $path);
                $pdo->prepare("UPDATE job_seeker_profiles SET resume_path = ? WHERE user_id = ?")->execute([$path, $userId]);
            } else {
                $error = "Only valid PDF files are permitted for resumes.";
            }
        }
    }

    if (empty($error)) {
        $message = "Profile updated successfully.";
    }
}

$userStmt = $pdo->prepare("SELECT full_name, username, email, phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Profile Settings</title>
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
            <a href="interviews.php">Interviews</a>
            <a href="profile.php" class="active">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="center-page-wrapper">
        <div class="card narrow-container">
            <div class="page-header" style="margin-bottom: 1.25rem;">
                <h2>Account & Profile Settings</h2>
                <a href="update_plan.php" class="btn btn-outline btn-auto">Upgrade Plan</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Registered Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" value="+91 <?= htmlspecialchars($user['phone']) ?>" disabled>
                    </div>
                </div>

                <?php if ($role === 'employer'): ?>
                    <div class="form-group">
                        <label>Re-upload Company Logo (JPG, PNG, WebP)</label>
                        <input type="file" name="logo" accept="image/*">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Re-upload CV / Resume (PDF Only)</label>
                        <input type="file" name="cv" accept="application/pdf">
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn">Save Profile Changes</button>
            </form>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 1.75rem 0;">

            <div style="text-align: center;">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.75rem;">
                    Need to <?= $role === 'employer' ? 'search for jobs as a candidate' : 'hire candidates as an employer' ?>?
                </p>
                <a href="profile.php?switch_role=1" class="btn btn-secondary">
                    🔄 Switch User to <?= $role === 'employer' ? 'Job Seeker' : 'Employer' ?> Mode
                </a>
            </div>
        </div>
    </div>
</body>
</html>