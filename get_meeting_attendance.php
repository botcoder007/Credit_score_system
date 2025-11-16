<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    exit();
}

 $host = '127.0.0.1';
 $dbname = 'credit';
 $username = 'root';
 $password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

 $meeting_id = $_GET['meeting_id'] ?? '';
 $details = $_GET['details'] ?? '';

if ($details) {
    // Get detailed attendance with faculty info
    $sql = "SELECT ma.*, c.emp_name, c.email 
            FROM meeting_attendance ma 
            LEFT JOIN credentials c ON ma.faculty_id = c.employee_id 
            WHERE ma.meeting_id = :meeting_id 
            ORDER BY c.emp_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':meeting_id' => $meeting_id]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="space-y-3">';
    foreach ($attendance as $att) {
        echo '<div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">';
        echo '<div class="flex-1">';
        echo '<h4 class="font-semibold text-gray-800 dark:text-white">' . htmlspecialchars($att['emp_name']) . '</h4>';
        echo '<p class="text-sm text-gray-600 dark:text-gray-400">' . htmlspecialchars($att['faculty_id']) . '</p>';
        echo '<p class="text-sm text-gray-600 dark:text-gray-400">' . htmlspecialchars($att['email']) . '</p>';
        if ($att['notes']) {
            echo '<p class="text-sm text-gray-500 mt-1">Notes: ' . htmlspecialchars($att['notes']) . '</p>';
        }
        echo '</div>';
        echo '<div class="flex items-center gap-2">';
        echo '<select onchange="updateAttendance(\'' . $meeting_id . '\', \'' . $att['faculty_id'] . '\', this.value)" class="attendance-badge attendance-' . $att['status'] . ' px-3 py-1 rounded">';
        echo '<option value="invited" ' . ($att['status'] === 'invited' ? 'selected' : '') . '>Invited</option>';
        echo '<option value="attended" ' . ($att['status'] === 'attended' ? 'selected' : '') . '>Attended</option>';
        echo '<option value="absent" ' . ($att['status'] === 'absent' ? 'selected' : '') . '>Absent</option>';
        echo '<option value="excused" ' . ($att['status'] === 'excused' ? 'selected' : '') . '>Excused</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    // Get just the faculty IDs for editing
    $sql = "SELECT faculty_id FROM meeting_attendance WHERE meeting_id = :meeting_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':meeting_id' => $meeting_id]);
    $attendance = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($attendance);
}
?>