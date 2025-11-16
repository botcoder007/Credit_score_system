<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['branch'])) {
    header("Location: admin.php");
    exit();
}

// Get admin's branch from session
 $admin_branch = $_SESSION['branch'];
 $admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Database configuration
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

// Function to send meeting invitation email using PHPMailer
function sendMeetingInvitationEmail($to_email, $faculty_name, $meeting_title, $meeting_purpose, $meeting_date, $meeting_time, $meeting_duration, $meeting_location, $meeting_id, $admin_name, $admin_branch) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'creditupdatevfstr@gmail.com';
        $mail->Password   = 'osufcsdflntokogp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Additional settings for reliability
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('creditupdatevfstr@gmail.com', 'Credit Update System');
        $mail->addAddress($to_email, $faculty_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Meeting Invitation: ' . $meeting_title;
        
        // Enhanced HTML email template
        $mail->Body = "
        <html>
        <head>
            <title>Meeting Invitation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .meeting-details { background: white; padding: 25px; border-radius: 8px; border-left: 4px solid #4f46e5; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 20px; }
                .info { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                table { width: 100%; border-collapse: collapse; }
                td { padding: 8px 0; }
                .label { font-weight: bold; color: #555; }
                .value { color: #333; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 28px;'>📅 Meeting Invitation</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Credit Update System</p>
                </div>
                <div class='content'>
                    <h2 style='color: #333; margin-top: 0;'>Hello $faculty_name,</h2>
                    <p>You are invited to attend the following meeting:</p>
                    
                    <div class='meeting-details'>
                        <h3 style='color: #4f46e5; margin-top: 0;'>📋 Meeting Details:</h3>
                        <table>
                            <tr>
                                <td class='label'>Title:</td>
                                <td class='value'>$meeting_title</td>
                            </tr>
                            <tr>
                                <td class='label'>Purpose:</td>
                                <td class='value'>$meeting_purpose</td>
                            </tr>
                            <tr>
                                <td class='label'>Date:</td>
                                <td class='value'>" . date('F j, Y', strtotime($meeting_date)) . "</td>
                            </tr>
                            <tr>
                                <td class='label'>Time:</td>
                                <td class='value'>" . date('g:i A', strtotime($meeting_time)) . "</td>
                            </tr>
                            <tr>
                                <td class='label'>Duration:</td>
                                <td class='value'>$meeting_duration minutes</td>
                            </tr>
                            <tr>
                                <td class='label'>Location:</td>
                                <td class='value'>$meeting_location</td>
                            </tr>
                            <tr>
                                <td class='label'>Meeting ID:</td>
                                <td class='value'>$meeting_id</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class='info'>
                        <h4 style='margin-top: 0; color: #17a2b8;'>📍 Important Information:</h4>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Please make sure to attend on time</li>
                            <li>If you cannot attend, please inform the organizer in advance</li>
                            <li>Meeting materials will be shared after the session</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions or need additional information, please contact the meeting organizer.</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br><strong>$admin_name</strong><br>Department: $admin_branch</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>© " . date('Y') . " Credit Update System. All rights reserved.</p>
                    <p style='font-size: 11px; color: #999; margin-top: 10px;'>Email sent on " . date('F j, Y \a\t g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>";

        // Plain text version for email clients that don't support HTML
        $mail->AltBody = "Meeting Invitation - Credit Update System\n\n"
                       . "Hello $faculty_name,\n\n"
                       . "You are invited to attend the following meeting:\n\n"
                       . "Title: $meeting_title\n"
                       . "Purpose: $meeting_purpose\n"
                       . "Date: " . date('F j, Y', strtotime($meeting_date)) . "\n"
                       . "Time: " . date('g:i A', strtotime($meeting_time)) . "\n"
                       . "Location: $meeting_location\n"
                       . "Please make sure to attend on time.\n\n"
                       . "Best regards,\n$admin_name\nDepartment: $admin_branch";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Create meetings table if it doesn't exist
 $create_table_sql = "CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    location VARCHAR(255),
    department VARCHAR(100) NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled'
)";

 $create_attendance_table = "CREATE TABLE IF NOT EXISTS meeting_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id VARCHAR(50) NOT NULL,
    faculty_id VARCHAR(50) NOT NULL,
    status ENUM('invited', 'attended', 'absent', 'excused') DEFAULT 'invited',
    notes TEXT,
    FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE
)";

try {
    $pdo->exec($create_table_sql);
    $pdo->exec($create_attendance_table);
} catch(PDOException $e) {
    // Tables might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $meeting_id = $_POST['meeting_id'];
        $title = $_POST['title'];
        $purpose = $_POST['purpose'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $duration = $_POST['duration'];
        $location = $_POST['location'];
        $selected_faculty = $_POST['faculty'] ?? [];
        
        if ($action === 'add') {
            // Check if meeting already exists
            $check_sql = "SELECT meeting_id FROM meetings WHERE meeting_id = :meeting_id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':meeting_id' => $meeting_id]);
            $existing_meeting = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_meeting) {
                $_SESSION['message'] = 'Meeting with this ID already exists!';
                $_SESSION['messageType'] = 'danger';
            } else {
                // Insert meeting
                $sql = "INSERT INTO meetings (meeting_id, title, purpose, date, time, duration, location, department, created_by) 
                        VALUES (:meeting_id, :title, :purpose, :date, :time, :duration, :location, :department, :created_by)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':meeting_id' => $meeting_id,
                    ':title' => $title,
                    ':purpose' => $purpose,
                    ':date' => $date,
                    ':time' => $time,
                    ':duration' => $duration,
                    ':location' => $location,
                    ':department' => $admin_branch,
                    ':created_by' => $admin_name
                ]);
                
                // Add faculty to attendance
                foreach ($selected_faculty as $faculty_id) {
                    $attendance_sql = "INSERT INTO meeting_attendance (meeting_id, faculty_id) VALUES (:meeting_id, :faculty_id)";
                    $attendance_stmt = $pdo->prepare($attendance_sql);
                    $attendance_stmt->execute([
                        ':meeting_id' => $meeting_id,
                        ':faculty_id' => $faculty_id
                    ]);
                }
                
                // Send emails to faculty using PHPMailer
                $email_success_count = 0;
                $email_failure_count = 0;
                
                if (!empty($selected_faculty)) {
                    $faculty_sql = "SELECT employee_id, emp_name, email FROM credentials WHERE employee_id IN ('" . implode("','", $selected_faculty) . "')";
                    $faculty_stmt = $pdo->query($faculty_sql);
                    $faculty_list = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($faculty_list as $faculty) {
                        if (sendMeetingInvitationEmail(
                            $faculty['email'], 
                            $faculty['emp_name'], 
                            $title, 
                            $purpose, 
                            $date, 
                            $time, 
                            $duration, 
                            $location, 
                            $meeting_id, 
                            $admin_name, 
                            $admin_branch
                        )) {
                            $email_success_count++;
                        } else {
                            $email_failure_count++;
                        }
                    }
                }
                
                // Set appropriate message based on email sending results
                if ($email_failure_count === 0) {
                    $_SESSION['message'] = 'Meeting created successfully and emails sent to all faculty!';
                } else if ($email_success_count > 0) {
                    $_SESSION['message'] = "Meeting created successfully. Emails sent to $email_success_count faculty, but failed to send to $email_failure_count faculty.";
                } else {
                    $_SESSION['message'] = 'Meeting created successfully, but failed to send emails to faculty.';
                }
                $_SESSION['messageType'] = 'success';
            }
        } else {
            // Update meeting
            $sql = "UPDATE meetings SET 
                    title = :title, 
                    purpose = :purpose,
                    date = :date,
                    time = :time,
                    duration = :duration,
                    location = :location
                    WHERE meeting_id = :meeting_id AND department = :department";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':meeting_id' => $meeting_id,
                ':title' => $title,
                ':purpose' => $purpose,
                ':date' => $date,
                ':time' => $time,
                ':duration' => $duration,
                ':location' => $location,
                ':department' => $admin_branch
            ]);
            
            // Update attendance
            $delete_sql = "DELETE FROM meeting_attendance WHERE meeting_id = :meeting_id";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([':meeting_id' => $meeting_id]);
            
            foreach ($selected_faculty as $faculty_id) {
                $attendance_sql = "INSERT INTO meeting_attendance (meeting_id, faculty_id) VALUES (:meeting_id, :faculty_id)";
                $attendance_stmt = $pdo->prepare($attendance_sql);
                $attendance_stmt->execute([
                    ':meeting_id' => $meeting_id,
                    ':faculty_id' => $faculty_id
                ]);
            }
            
            $_SESSION['message'] = 'Meeting updated successfully!';
            $_SESSION['messageType'] = 'success';
        }
    } elseif ($action === 'delete') {
        $meeting_id = $_POST['meeting_id'];
        
        // Verify the meeting belongs to admin's department
        $check_sql = "SELECT department FROM meetings WHERE meeting_id = :meeting_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':meeting_id' => $meeting_id]);
        $meeting = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($meeting && $meeting['department'] !== $admin_branch) {
            $_SESSION['message'] = 'You can only delete meetings in your department!';
            $_SESSION['messageType'] = 'danger';
        } else {
            $sql = "DELETE FROM meetings WHERE meeting_id = :meeting_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':meeting_id' => $meeting_id]);
            $_SESSION['message'] = 'Meeting deleted successfully!';
            $_SESSION['messageType'] = 'danger';
        }
    } elseif ($action === 'update_attendance') {
        $meeting_id = $_POST['meeting_id'];
        $faculty_id = $_POST['faculty_id'];
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $sql = "UPDATE meeting_attendance SET status = :status, notes = :notes 
                WHERE meeting_id = :meeting_id AND faculty_id = :faculty_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':meeting_id' => $meeting_id,
            ':faculty_id' => $faculty_id,
            ':status' => $status,
            ':notes' => $notes
        ]);
        
        $_SESSION['message'] = 'Attendance updated successfully!';
        $_SESSION['messageType'] = 'success';
    }
    
    // Redirect to prevent form resubmission
    header("Location: meetings.php");
    exit();
}

// Get message from session if exists
 $message = $_SESSION['message'] ?? '';
 $messageType = $_SESSION['messageType'] ?? '';

// Clear message from session
unset($_SESSION['message']);
unset($_SESSION['messageType']);

// Fetch faculty from admin's branch
 $faculty_sql = "SELECT employee_id, emp_name, email FROM credentials WHERE department = :admin_branch ORDER BY emp_name";
 $faculty_stmt = $pdo->prepare($faculty_sql);
 $faculty_stmt->execute([':admin_branch' => $admin_branch]);
 $faculty_list = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch meetings
 $sql = "SELECT m.*, 
               COUNT(ma.faculty_id) as total_attendees,
               SUM(CASE WHEN ma.status = 'attended' THEN 1 ELSE 0 END) as attended_count
        FROM meetings m 
        LEFT JOIN meeting_attendance ma ON m.meeting_id = ma.meeting_id 
        WHERE m.department = :admin_branch 
        GROUP BY m.id 
        ORDER BY m.date DESC, m.time DESC";
 $stmt = $pdo->prepare($sql);
 $stmt->execute([':admin_branch' => $admin_branch]);
 $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Meeting Management</title>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght@400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#4f46e5",
                        "primary-dark": "#4338ca",
                        "background-light": "#f8fafc",
                        "background-dark": "#1e293b",
                        "sidebar-light": "#ffffff",
                        "sidebar-dark": "#334155",
                        "card-light": "#ffffff",
                        "card-dark": "#334155",
                        "accent": "#818cf8",
                        "success": "#10b981",
                        "warning": "#f59e0b",
                        "danger": "#ef4444",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .dark ::-webkit-scrollbar-track {
            background: #1e293b;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-scheduled {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .status-completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .status-cancelled {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .attendance-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .attendance-invited {
            background: #e0e7ff;
            color: #4338ca;
        }
        .attendance-attended {
            background: #d1fae5;
            color: #065f46;
        }
        .attendance-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        .attendance-excused {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body class="antialiased bg-background-light dark:bg-background-dark font-display">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-card-light dark:bg-card-dark border-r border-gray-200 dark:border-gray-700 flex flex-col fixed left-0 top-0 h-screen z-50">
            <div class="p-6 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="bg-primary p-2 rounded-lg mr-3">
                        <i class="fas fa-shield-alt text-white text-xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">CreditUpdate</h1>
                </div>
            </div>
            <nav class="flex flex-col p-4 space-y-2 mt-4 flex-1 overflow-y-auto">
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 transition-all" href="adash.php">
                    <i class="fas fa-tachometer-alt text-lg"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 transition-all" href="teacher.php">
                    <i class="fas fa-chalkboard-teacher text-lg"></i>
                    <span class="font-medium">Teachers</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 transition-all" href="classes.php">
                    <i class="fas fa-school text-lg"></i>
                    <span class="font-medium">Classes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary/10 text-primary border-l-3 border-primary transition-all" href="meetings.php">
                    <i class="fas fa-users text-lg"></i>
                    <span class="font-medium">Meetings</span>
                </a>
            </nav>
            
            <div class="mt-auto p-4 border-t border-gray-200 dark:border-gray-700">
                <div class="mb-3 px-4 py-2 bg-primary/10 rounded-lg">
                    <p class="text-xs text-gray-600 dark:text-gray-400">Department</p>
                    <p class="text-sm font-semibold text-primary"><?php echo htmlspecialchars($admin_branch); ?></p>
                </div>
                <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-red-100 hover:text-danger transition-all">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-auto ml-64">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <header class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Meeting Management</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Schedule and manage meetings for <span class="font-semibold text-primary"><?php echo htmlspecialchars($admin_branch); ?></span> department.</p>
                    </div>
                    <button onclick="openModal()" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2">
                        <i class="fas fa-calendar-plus"></i>
                        Schedule New Meeting
                    </button>
                </header>

                <?php if ($message): ?>
                <div class="mb-4 px-6 py-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Meetings List -->
                <section>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">All Meetings</h2>
                        <span class="text-gray-600 dark:text-gray-400">Total: <span class="font-bold text-primary"><?php echo count($meetings); ?></span> Meetings</span>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($meetings as $meeting): ?>
                        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-lg p-6 hover:shadow-xl transition-all">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($meeting['title']); ?></h3>
                                        <span class="status-badge status-<?php echo $meeting['status']; ?>">
                                            <?php echo ucfirst($meeting['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-600 dark:text-gray-400 mb-3"><?php echo htmlspecialchars($meeting['purpose']); ?></p>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-calendar text-primary"></i>
                                            <span class="text-gray-700 dark:text-gray-300"><?php echo date('M j, Y', strtotime($meeting['date'])); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-primary"></i>
                                            <span class="text-gray-700 dark:text-gray-300"><?php echo date('g:i A', strtotime($meeting['time'])); ?> (<?php echo $meeting['duration']; ?> min)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                            <span class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($meeting['location']); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-users text-primary"></i>
                                            <span class="text-gray-700 dark:text-gray-300">
                                                <?php echo $meeting['attended_count']; ?>/<?php echo $meeting['total_attendees']; ?> Attended
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-id-badge text-primary"></i>
                                            <span class="text-gray-700 dark:text-gray-300">ID: <?php echo htmlspecialchars($meeting['meeting_id']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-4">
                                    <button onclick='viewAttendance("<?php echo $meeting['meeting_id']; ?>")' class="text-primary hover:text-primary-dark transition-colors" title="View Attendance">
                                        <i class="fas fa-user-check text-xl"></i>
                                    </button>
                                    <button onclick='editMeeting(<?php echo json_encode($meeting); ?>)' class="text-warning hover:text-yellow-600 transition-colors" title="Edit">
                                        <i class="fas fa-edit text-xl"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this meeting?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                                        <button type="submit" class="text-danger hover:text-red-700 transition-colors" title="Delete">
                                            <i class="fas fa-trash-alt text-xl"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Add/Edit Meeting Modal -->
    <div id="meeting-modal" class="modal">
        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-lg p-8 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white" id="modal-title">Schedule New Meeting</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" id="form-action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Meeting ID *</label>
                        <input type="text" name="meeting_id" id="meeting-id" required class="block w-full rounded-lg border-gray-300 px-4 py-2" placeholder="e.g., MTG-2024-001"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title *</label>
                        <input type="text" name="title" id="title" required class="block w-full rounded-lg border-gray-300 px-4 py-2" placeholder="e.g., Department Review Meeting"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Purpose *</label>
                        <textarea name="purpose" id="purpose" rows="3" required class="block w-full rounded-lg border-gray-300 px-4 py-2" placeholder="Describe the purpose of this meeting..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date *</label>
                        <input type="date" name="date" id="date" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time *</label>
                        <input type="time" name="time" id="time" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Duration (minutes) *</label>
                        <input type="number" name="duration" id="duration" min="15" step="15" required class="block w-full rounded-lg border-gray-300 px-4 py-2" placeholder="60"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Location *</label>
                        <input type="text" name="location" id="location" required class="block w-full rounded-lg border-gray-300 px-4 py-2" placeholder="e.g., Conference Room A"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Invite Faculty *</label>
                        <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-300 rounded-lg p-3">
                            <?php foreach ($faculty_list as $faculty): ?>
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
                                <input type="checkbox" name="faculty[]" value="<?php echo $faculty['employee_id']; ?>" class="rounded text-primary">
                                <span class="text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($faculty['emp_name']); ?> (<?php echo htmlspecialchars($faculty['employee_id']); ?>)
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="submit" class="btn-primary flex-1 px-6 py-3 rounded-lg text-white font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Meeting
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-100 transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div id="attendance-modal" class="modal">
        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Meeting Attendance</h2>
                <button onclick="closeAttendanceModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="attendance-content">
                <!-- Attendance content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('meeting-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Schedule New Meeting';
            document.getElementById('form-action').value = 'add';
            document.getElementById('meeting-id').removeAttribute('readonly');
        }

        function closeModal() {
            document.getElementById('meeting-modal').classList.remove('active');
            document.querySelector('#meeting-modal form').reset();
        }

        function editMeeting(meetingData) {
            document.getElementById('meeting-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Edit Meeting';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('meeting-id').value = meetingData.meeting_id;
            document.getElementById('meeting-id').setAttribute('readonly', 'readonly');
            document.getElementById('title').value = meetingData.title;
            document.getElementById('purpose').value = meetingData.purpose;
            document.getElementById('date').value = meetingData.date;
            document.getElementById('time').value = meetingData.time;
            document.getElementById('duration').value = meetingData.duration;
            document.getElementById('location').value = meetingData.location;
            
            // Load selected faculty
            fetch('get_meeting_attendance.php?meeting_id=' + meetingData.meeting_id)
                .then(response => response.json())
                .then(data => {
                    const checkboxes = document.querySelectorAll('input[name="faculty[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = data.includes(checkbox.value);
                    });
                });
        }

        function viewAttendance(meetingId) {
            document.getElementById('attendance-modal').classList.add('active');
            
            fetch('get_meeting_attendance.php?meeting_id=' + meetingId + '&details=1')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('attendance-content').innerHTML = html;
                });
        }

        function closeAttendanceModal() {
            document.getElementById('attendance-modal').classList.remove('active');
        }

        function updateAttendance(meetingId, facultyId, status) {
            const notes = document.getElementById('notes-' + facultyId)?.value || '';
            
            fetch('meetings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_attendance',
                    meeting_id: meetingId,
                    faculty_id: facultyId,
                    status: status,
                    notes: notes
                })
            })
            .then(response => response.text())
            .then(() => {
                viewAttendance(meetingId);
            });
        }

        // Set minimum date to today
        document.getElementById('date')?.setAttribute('min', new Date().toISOString().split('T')[0]);
    </script>
</body>
</html>