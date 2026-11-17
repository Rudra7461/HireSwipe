<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
    header("Location: login.php");
    exit;
}

// Ensure baseline row exists
$pdo->prepare("INSERT IGNORE INTO job_seeker_profiles (user_id) VALUES (?)")->execute([$_SESSION['user_id']]);

$userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$currentName = $userStmt->fetchColumn();

$step = isset($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $_SESSION['seek_setup']['full_name'] = trim($_POST['full_name'] ?? $currentName);
        $_SESSION['seek_setup']['age'] = (int)$_POST['age'];
        $_SESSION['seek_setup']['location'] = trim($_POST['location'] ?? '');
        $_SESSION['seek_setup']['nationality'] = trim($_POST['nationality'] ?? '');

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/pictures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileExt = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $targetPath = $uploadDir . uniqid('pic_') . '.' . $fileExt;
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath);
                $_SESSION['seek_setup']['profile_picture'] = $targetPath;
            } else {
                $error = "Invalid profile picture format. Use JPG, PNG, or WebP.";
            }
        }

        if (empty($error)) {
            header("Location: seeker_setup.php?step=2");
            exit;
        }
    } elseif ($step === 2) {
        $experience = (int)$_POST['experience_years'];
        $age = (int)($_SESSION['seek_setup']['age'] ?? 18);

        if ($experience >= $age) {
            $error = "Validation Failed: Experience ($experience yrs) must be strictly less than your Age ($age yrs).";
        } else {
            $_SESSION['seek_setup']['qualification'] = trim($_POST['qualification'] ?? '');
            $_SESSION['seek_setup']['tickets'] = trim($_POST['tickets'] ?? '');
            $_SESSION['seek_setup']['experience_years'] = $experience;
            $_SESSION['seek_setup']['expected_salary'] = (float)$_POST['expected_salary'];
            $_SESSION['seek_setup']['employment_type'] = $_POST['employment_type'] ?? 'Full-time';
            $_SESSION['seek_setup']['industries'] = trim($_POST['industries'] ?? '');

            if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/cvs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $fileExt = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
                $mime = mime_content_type($_FILES['cv']['tmp_name']);
                if ($fileExt === 'pdf' && $mime === 'application/pdf') {
                    $targetPath = $uploadDir . uniqid('cv_') . '.pdf';
                    move_uploaded_file($_FILES['cv']['tmp_name'], $targetPath);
                    $_SESSION['seek_setup']['resume_path'] = $targetPath;
                } else {
                    $error = "Invalid CV file. Please upload a valid PDF document.";
                }
            }

            if (empty($error)) {
                header("Location: seeker_setup.php?step=3");
                exit;
            }
        }
    } elseif ($step === 3) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")->execute([
                $_SESSION['seek_setup']['full_name'],
                $_SESSION['user_id']
            ]);

            $stmt = $pdo->prepare("
                UPDATE job_seeker_profiles SET 
                    age = ?, location = ?, nationality = ?, qualification = ?, tickets = ?, 
                    experience_years = ?, expected_salary = ?, preferred_employment_type = ?, 
                    preferred_industries = ?, profile_picture = ?, resume_path = ?, is_setup = 1 
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_SESSION['seek_setup']['age'],
                $_SESSION['seek_setup']['location'],
                $_SESSION['seek_setup']['nationality'],
                $_SESSION['seek_setup']['qualification'],
                $_SESSION['seek_setup']['tickets'],
                $_SESSION['seek_setup']['experience_years'],
                $_SESSION['seek_setup']['expected_salary'],
                $_SESSION['seek_setup']['employment_type'],
                $_SESSION['seek_setup']['industries'],
                $_SESSION['seek_setup']['profile_picture'] ?? null,
                $_SESSION['seek_setup']['resume_path'] ?? null,
                $_SESSION['user_id']
            ]);

            $pdo->commit();
            unset($_SESSION['seek_setup']);
            header("Location: jobseeker_dashboard.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Could not save profile: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Job Seeker Setup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="#" class="navbar-brand">HireSwipe <span class="role-pill">Candidate Setup</span></a>
        <div class="nav-links">
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="center-page-wrapper">
        <div class="card narrow-container">
            <div class="stepper">
                <div class="step-pill <?= $step >= 1 ? 'active' : '' ?>">1. Personal Info</div>
                <div class="step-pill <?= $step >= 2 ? 'active' : '' ?>">2. Career & CV</div>
                <div class="step-pill <?= $step >= 3 ? 'active' : '' ?>">3. Finalize</div>
            </div>

            <h2 style="margin-bottom: 1.25rem;">Build Your Candidate Profile</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="seeker_setup.php?step=<?= $step ?>" enctype="multipart/form-data">
                <?php if ($step === 1): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($_SESSION['seek_setup']['full_name'] ?? $currentName) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" min="16" max="90" value="<?= htmlspecialchars($_SESSION['seek_setup']['age'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?= htmlspecialchars($_SESSION['seek_setup']['location'] ?? '') ?>" placeholder="Ahmedabad, India" required>
                        </div>
                        <div class="form-group">
                            <label>Nationality</label>
                            <input type="text" name="nationality" value="<?= htmlspecialchars($_SESSION['seek_setup']['nationality'] ?? 'Indian') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Profile Picture (JPG, PNG, WebP)</label>
                        <input type="file" name="profile_pic" accept="image/*" <?= isset($_SESSION['seek_setup']['profile_picture']) ? '' : 'required' ?>>
                    </div>
                    <button type="submit" class="btn">Continue to Step 2 →</button>

                <?php elseif ($step === 2): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Experience (Years)</label>
                            <input type="number" name="experience_years" min="0" value="<?= htmlspecialchars($_SESSION['seek_setup']['experience_years'] ?? '0') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Expected Annual Salary (₹)</label>
                            <input type="number" name="expected_salary" min="0" step="1000" value="<?= htmlspecialchars($_SESSION['seek_setup']['expected_salary'] ?? '600000') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Highest Qualification</label>
                        <input type="text" name="qualification" value="<?= htmlspecialchars($_SESSION['seek_setup']['qualification'] ?? '') ?>" placeholder="MCA in Cyber Security" required>
                    </div>
                    <div class="form-group">
                        <label>Tickets & Certifications</label>
                        <input type="text" name="tickets" value="<?= htmlspecialchars($_SESSION['seek_setup']['tickets'] ?? '') ?>" placeholder="SOC Analyst, CompTIA Security+, CEH">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Preferred Employment Type</label>
                            <select name="employment_type" required>
                                <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $type): ?>
                                    <option value="<?= $type ?>" <?= (($_SESSION['seek_setup']['employment_type'] ?? '') === $type) ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Target Industries</label>
                            <input type="text" name="industries" value="<?= htmlspecialchars($_SESSION['seek_setup']['industries'] ?? '') ?>" placeholder="Cybersecurity, FinTech, IT" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Upload Resume / CV (PDF Only)</label>
                        <input type="file" name="cv" accept="application/pdf" <?= isset($_SESSION['seek_setup']['resume_path']) ? '' : 'required' ?>>
                    </div>
                    <div class="form-row">
                        <a href="seeker_setup.php?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn">Review Profile →</button>
                    </div>

                <?php elseif ($step === 3): ?>
                    <div style="background: var(--bg-body); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1.5rem;">
                        <p style="margin-bottom: 6px;"><strong>Name & Age:</strong> <?= htmlspecialchars($_SESSION['seek_setup']['full_name'] ?? '') ?> (<?= (int)($_SESSION['seek_setup']['age'] ?? 0) ?> yrs)</p>
                        <p style="margin-bottom: 6px;"><strong>Location & Nationality:</strong> <?= htmlspecialchars($_SESSION['seek_setup']['location'] ?? '') ?> • <?= htmlspecialchars($_SESSION['seek_setup']['nationality'] ?? '') ?></p>
                        <p style="margin-bottom: 6px;"><strong>Qualification:</strong> <?= htmlspecialchars($_SESSION['seek_setup']['qualification'] ?? '') ?></p>
                        <p style="margin-bottom: 6px;"><strong>Certifications/Tickets:</strong> <?= htmlspecialchars($_SESSION['seek_setup']['tickets'] ?: 'None listed') ?></p>
                        <p style="margin-bottom: 6px;"><strong>Experience:</strong> <?= (int)($_SESSION['seek_setup']['experience_years'] ?? 0) ?> Years</p>
                        <p><strong>Target Role:</strong> <?= htmlspecialchars($_SESSION['seek_setup']['employment_type'] ?? '') ?> in <?= htmlspecialchars($_SESSION['seek_setup']['industries'] ?? '') ?></p>
                    </div>
                    <div class="form-row">
                        <a href="seeker_setup.php?step=2" class="btn btn-secondary">← Edit</a>
                        <button type="submit" class="btn">Complete Profile →</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>