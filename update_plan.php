<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$role = $_SESSION['role'] ?? 'job_seeker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSwipe - Upgrade Plan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <a href="<?= $role === 'employer' ? 'employer_dashboard.php' : 'jobseeker_dashboard.php' ?>" class="navbar-brand">
            HireSwipe <span class="role-pill">Billing</span>
        </a>
        <div class="nav-links">
            <a href="<?= $role === 'employer' ? 'employer_dashboard.php' : 'jobseeker_dashboard.php' ?>">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="alert alert-error" style="text-align: center; font-size: 1rem; font-weight: 700; padding: 1.1rem;">
            ⚠️ Your 14-day free trial expired. Choose a subscription plan below to unlock unlimited swipes and instant video interviews.
        </div>

        <div style="text-align: center; margin: 2rem 0;">
            <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">Simple, Transparent Pricing</h1>
            <p style="color: var(--text-muted);">Scale your hiring or accelerate your job search with HireSwipe Pro.</p>
        </div>

        <div class="cards-grid" style="align-items: stretch;">
            <!-- Monthly Plan -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <span class="badge" style="margin-bottom: 1rem; display: inline-block;">MONTHLY</span>
                    <h2 style="font-size: 2.25rem; font-weight: 800; margin-bottom: 1.25rem;">₹999<span style="font-size: 0.95rem; color: var(--text-muted); font-weight: 500;"> / month</span></h2>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 2rem; font-size: 0.94rem;">
                        <li>✓ Unlimited Daily Swipes</li>
                        <li>✓ Direct Candidate / Job Invites</li>
                        <li>✓ Up to 5 Active Job Listings</li>
                        <li>✓ Built-in Jitsi Video Interviews</li>
                    </ul>
                </div>
                <button class="btn btn-outline" onclick="alert('Selected Monthly Plan (₹999/mo).')">Choose Monthly</button>
            </div>

            <!-- Quarterly Plan -->
            <div class="card" style="border: 2px solid var(--primary); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                <div>
                    <span class="role-pill" style="margin-bottom: 1rem; display: inline-block;">QUARTERLY • MOST POPULAR</span>
                    <h2 style="font-size: 2.25rem; font-weight: 800; margin-bottom: 1.25rem;">₹2,499<span style="font-size: 0.95rem; color: var(--text-muted); font-weight: 500;"> / 3 months</span></h2>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 2rem; font-size: 0.94rem;">
                        <li>✓ Everything in Monthly</li>
                        <li>✓ Priority Deck Placement</li>
                        <li>✓ Up to 15 Active Job Listings</li>
                        <li>✓ Automated Jitsi Room Scheduling</li>
                    </ul>
                </div>
                <button class="btn" onclick="alert('Selected Quarterly Plan (₹2,499/3mo).')">Choose Quarterly</button>
            </div>

            <!-- Yearly Plan -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <span class="badge" style="margin-bottom: 1rem; display: inline-block;">YEARLY • SAVE 33%</span>
                    <h2 style="font-size: 2.25rem; font-weight: 800; margin-bottom: 1.25rem;">₹7,999<span style="font-size: 0.95rem; color: var(--text-muted); font-weight: 500;"> / year</span></h2>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 2rem; font-size: 0.94rem;">
                        <li>✓ Everything in Quarterly</li>
                        <li>✓ Unlimited Active Job Listings</li>
                        <li>✓ Featured Employer / Candidate Badge</li>
                        <li>✓ 24/7 Priority Account Support</li>
                    </ul>
                </div>
                <button class="btn btn-outline" onclick="alert('Selected Yearly Plan (₹7,999/yr).')">Choose Yearly</button>
            </div>
        </div>
    </div>
</body>
</html>