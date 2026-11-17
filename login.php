<?php
session_start();
require 'db.php';

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created! Sign in with your username and password.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both your username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'pending') {
                header("Location: role_selection.php");
            } elseif ($user['role'] === 'employer') {
                header("Location: employer_dashboard.php");
            } else {
                header("Location: jobseeker_dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Sign In</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-hero">
            <span class="auth-hero-badge">HIRESWIPE TALENT CLOUD</span>
            <h1>Where Top Talent Meets Instant Opportunity.</h1>
            <p>Sign in to review candidate matches, swipe on curated roles, and launch instant video interviews.</p>
        </div>

        <div class="auth-form-side">
            <div class="auth-box">
                <h2>Welcome Back</h2>
                <p class="subtitle">Enter your credentials to access your workspace.</p>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Enter your username" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 0.5rem;">Sign In</button>
                </form>

                <p style="margin-top: 1.5rem; font-size: 0.92rem; color: var(--text-muted); text-align: center;">
                    New to HireSwipe? <a href="register.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Create an account</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>