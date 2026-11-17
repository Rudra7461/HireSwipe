<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'pending') {
    header("Location: " . ($_SESSION['role'] === 'employer' ? "employer_dashboard.php" : "jobseeker_dashboard.php"));
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['role'])) {
    $selectedRole = $_POST['role'];

    if (in_array($selectedRole, ['employer', 'job_seeker'], true)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$selectedRole, $_SESSION['user_id']]);
            $_SESSION['role'] = $selectedRole;

            if ($selectedRole === 'employer') {
                $prof = $pdo->prepare("INSERT IGNORE INTO employer_profiles (user_id, company_name) VALUES (?, 'Pending Setup')");
                $prof->execute([$_SESSION['user_id']]);
                $pdo->commit();
                header("Location: employer_setup.php?step=1");
            } else {
                $prof = $pdo->prepare("INSERT IGNORE INTO job_seeker_profiles (user_id) VALUES (?)");
                $prof->execute([$_SESSION['user_id']]);
                $pdo->commit();
                header("Location: seeker_setup.php?step=1");
            }
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Could not save role selection. Please try again.";
        }
    } else {
        $error = "Invalid role selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Choose Your Path</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="#" class="navbar-brand">HireSwipe</a>
        <div class="nav-links">
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="center-page-wrapper">
        <div class="card" style="max-width: 480px; width: 100%; text-align: center;">
            <h2 style="font-size: 1.65rem; margin-bottom: 0.5rem;">How will you use HireSwipe?</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.75rem;">You can switch between Employer and Job Seeker modes anytime from your profile.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 1rem;">
                <button type="submit" name="role" value="employer" class="btn btn-outline" style="padding: 1.15rem; font-size: 1.05rem;">
                    🏢 Looking for an Employee
                </button>
                <button type="submit" name="role" value="job_seeker" class="btn" style="padding: 1.15rem; font-size: 1.05rem;">
                    💼 Looking for Work
                </button>
            </form>
        </div>
    </div>
</body>
</html>