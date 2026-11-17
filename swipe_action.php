<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$jobId = (int)($data['job_id'] ?? 0);
$seekerId = (int)($data['job_seeker_id'] ?? 0);
$direction = in_array($data['direction'] ?? '', ['interested', 'rejected'], true) ? $data['direction'] : 'rejected';
$initiator = in_array($data['initiator'] ?? '', ['employer', 'job_seeker'], true) ? $data['initiator'] : 'job_seeker';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT IGNORE INTO swipes (job_id, job_seeker_id, direction, initiator) VALUES (?, ?, ?, ?)");
    $stmt->execute([$jobId, $seekerId, $direction, $initiator]);

    $isMatch = false;

    if ($direction === 'interested') {
        $checkMatch = $pdo->prepare("
            SELECT COUNT(*) FROM swipes 
            WHERE job_id = ? AND job_seeker_id = ? AND direction = 'interested'
        ");
        $checkMatch->execute([$jobId, $seekerId]);

        if ((int)$checkMatch->fetchColumn() >= 2) {
            $isMatch = true;

            // Fetch employer_id for the job and create an accepted invitation so interviews.php schedules it
            $empLookup = $pdo->prepare("SELECT employer_id FROM jobs WHERE id = ?");
            $empLookup->execute([$jobId]);
            $employerId = $empLookup->fetchColumn();

            if ($employerId) {
                $invStmt = $pdo->prepare("
                    INSERT INTO invitations (employer_id, job_seeker_id, job_id, status) 
                    VALUES (?, ?, ?, 'accepted')
                    ON DUPLICATE KEY UPDATE status = 'accepted'
                ");
                $invStmt->execute([$employerId, $seekerId, $jobId]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'match' => $isMatch]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>