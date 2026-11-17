<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit;
}

// Ensure baseline row exists
$pdo->prepare("INSERT IGNORE INTO employer_profiles (user_id, company_name) VALUES (?, 'Pending Setup')")->execute([$_SESSION['user_id']]);

$industries = $pdo->query("SELECT id, industry_name FROM ref_industries ORDER BY id ASC")->fetchAll();
$step = isset($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $_SESSION['emp_setup']['company_name'] = trim($_POST['company_name'] ?? '');
        $_SESSION['emp_setup']['location'] = trim($_POST['location'] ?? '');
        $_SESSION['emp_setup']['lat'] = trim($_POST['lat'] ?? '23.0225');
        $_SESSION['emp_setup']['lng'] = trim($_POST['lng'] ?? '72.5714');

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/logos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileExt = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $targetPath = $uploadDir . uniqid('logo_') . '.' . $fileExt;
                move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath);
                $_SESSION['emp_setup']['logo_path'] = $targetPath;
            } else {
                $error = "Invalid logo format. Allowed: JPG, PNG, WebP.";
            }
        }

        if (empty($error)) {
            header("Location: employer_setup.php?step=2");
            exit;
        }
    } elseif ($step === 2) {
        $_SESSION['emp_setup']['industry_id'] = (int)$_POST['industry_id'];
        $_SESSION['emp_setup']['position'] = trim($_POST['position'] ?? '');
        $_SESSION['emp_setup']['employment_type'] = $_POST['employment_type'] ?? 'Full-time';
        $_SESSION['emp_setup']['job_detail'] = trim($_POST['job_detail'] ?? '');

        header("Location: employer_setup.php?step=3");
        exit;
    } elseif ($step === 3) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE employer_profiles 
                SET company_name = ?, industry_id = ?, location = ?, latitude = ?, longitude = ?, company_logo = ?, is_setup = 1 
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_SESSION['emp_setup']['company_name'],
                $_SESSION['emp_setup']['industry_id'],
                $_SESSION['emp_setup']['location'],
                $_SESSION['emp_setup']['lat'],
                $_SESSION['emp_setup']['lng'],
                $_SESSION['emp_setup']['logo_path'] ?? null,
                $_SESSION['user_id']
            ]);

            $empStmt = $pdo->prepare("SELECT id FROM employer_profiles WHERE user_id = ?");
            $empStmt->execute([$_SESSION['user_id']]);
            $employerId = $empStmt->fetchColumn();

            $jobStmt = $pdo->prepare("
                INSERT INTO jobs (employer_id, title, description, employment_type, location) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $jobStmt->execute([
                $employerId,
                $_SESSION['emp_setup']['position'],
                $_SESSION['emp_setup']['job_detail'],
                $_SESSION['emp_setup']['employment_type'],
                $_SESSION['emp_setup']['location']
            ]);

            $pdo->commit();
            unset($_SESSION['emp_setup']);
            header("Location: employer_dashboard.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Setup error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Employer Setup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="#" class="navbar-brand">HireSwipe <span class="role-pill">Employer Setup</span></a>
        <div class="nav-links">
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="center-page-wrapper">
        <div class="card narrow-container">
            <div class="stepper">
                <div class="step-pill <?= $step >= 1 ? 'active' : '' ?>">1. Company Info</div>
                <div class="step-pill <?= $step >= 2 ? 'active' : '' ?>">2. First Job Role</div>
                <div class="step-pill <?= $step >= 3 ? 'active' : '' ?>">3. Review & Launch</div>
            </div>

            <h2 style="margin-bottom: 1.25rem;">Employer Onboarding</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="employer_setup.php?step=<?= $step ?>" enctype="multipart/form-data">
                <?php if ($step === 1): ?>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($_SESSION['emp_setup']['company_name'] ?? '') ?>" placeholder="Acme Corp" required>
                    </div>
                    <div class="form-group">
                        <label>Headquarters / Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($_SESSION['emp_setup']['location'] ?? '') ?>" placeholder="Ahmedabad, Gujarat" required>
                        <input type="hidden" name="lat" value="23.0225">
                        <input type="hidden" name="lng" value="72.5714">
                    </div>
                    <div class="form-group">
                        <label>Company Logo (JPG, PNG, WebP)</label>
                        <input type="file" name="logo" accept="image/*" <?= isset($_SESSION['emp_setup']['logo_path']) ? '' : 'required' ?>>
                    </div>
                    <button type="submit" class="btn">Continue to Step 2 →</button>

                <?php elseif ($step === 2): ?>
                    <div class="form-group">
                        <label>Industry</label>
                        <select name="industry_id" required>
                            <?php foreach ($industries as $ind): ?>
                                <option value="<?= $ind['id'] ?>" <?= (($_SESSION['emp_setup']['industry_id'] ?? '') == $ind['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ind['industry_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Initial Job Position / Title</label>
                            <input type="text" name="position" value="<?= htmlspecialchars($_SESSION['emp_setup']['position'] ?? '') ?>" placeholder="SOC Analyst Tier 1" required>
                        </div>
                        <div class="form-group">
                            <label>Employment Type</label>
                            <select name="employment_type" required>
                                <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $type): ?>
                                    <option value="<?= $type ?>" <?= (($_SESSION['emp_setup']['employment_type'] ?? '') === $type) ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Job Description & Requirements</label>
                        <textarea name="job_detail" rows="4" placeholder="Describe key responsibilities and skills..." required><?= htmlspecialchars($_SESSION['emp_setup']['job_detail'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <a href="employer_setup.php?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn">Review Setup →</button>
                    </div>

                <?php elseif ($step === 3): ?>
                    <div style="background: var(--bg-body); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1.5rem;">
                        <p style="margin-bottom: 8px;"><strong>Company:</strong> <?= htmlspecialchars($_SESSION['emp_setup']['company_name'] ?? '') ?></p>
                        <p style="margin-bottom: 8px;"><strong>Location:</strong> <?= htmlspecialchars($_SESSION['emp_setup']['location'] ?? '') ?> (Pin: <?= htmlspecialchars($_SESSION['emp_setup']['lat'] ?? '') ?>, <?= htmlspecialchars($_SESSION['emp_setup']['lng'] ?? '') ?>)</p>
                        <p style="margin-bottom: 8px;"><strong>First Role:</strong> <?= htmlspecialchars($_SESSION['emp_setup']['position'] ?? '') ?> (<?= htmlspecialchars($_SESSION['emp_setup']['employment_type'] ?? '') ?>)</p>
                        <p><strong>Details:</strong> <?= nl2br(htmlspecialchars($_SESSION['emp_setup']['job_detail'] ?? '')) ?></p>
                    </div>
                    <div class="form-row">
                        <a href="employer_setup.php?step=2" class="btn btn-secondary">← Edit</a>
                        <button type="submit" class="btn">Confirm & Launch →</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>