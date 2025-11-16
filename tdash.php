<?php
session_start();

// Database configuration
 $host = 'localhost';
 $dbname = 'credit';
 $username = 'root';
 $password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle password change - WORKS WITH BOTH HASHED AND PLAIN TEXT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $employee_id = $_SESSION['employee_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Fetch current password from database
    $stmt = $pdo->prepare("SELECT password FROM credentials WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stored_password = $user['password'];
        $current_password_valid = false;
        
        // Check if stored password is hashed (starts with $2y$) or plain text
        if (substr($stored_password, 0, 4) === '$2y$') {
            // Password is hashed, use password_verify
            $current_password_valid = password_verify($current_password, $stored_password);
        } else {
            // Password is plain text, compare directly
            $current_password_valid = (trim($current_password) === trim($stored_password));
        }
        
        // Verify current password
        if ($current_password_valid) {
            // Check if new passwords match
            if ($new_password === $confirm_password) {
                // Check password strength
                if (strlen($new_password) >= 6) {
                    // Check if new password is different from current
                    if ($new_password !== $current_password) {
                        try {
                            // Hash the new password for security
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            
                            // Store hashed password in database
                            $stmt = $pdo->prepare("UPDATE credentials SET password = ? WHERE employee_id = ?");
                            $stmt->execute([$hashed_password, $employee_id]);
                            
                            $_SESSION['success_message'] = "Password changed successfully!";
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit();
                        } catch(PDOException $e) {
                            $_SESSION['error_message'] = "Error updating password: " . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error_message'] = "New password must be different from current password.";
                    }
                } else {
                    $_SESSION['error_message'] = "Password must be at least 6 characters long.";
                }
            } else {
                $_SESSION['error_message'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['error_message'] = "Current password is incorrect.";
        }
    } else {
        $_SESSION['error_message'] = "User not found.";
    }
}

// Handle profile update - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $employee_id = $_SESSION['employee_id'];
    $role = $_POST['role'];
    $sub_name = $_POST['sub_name'];
    $profile_image = null;
    
    // Handle file upload with better error handling
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $_SESSION['error_message'] = "Failed to create upload directory.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
        
        // Check if directory is writable
        if (!is_writable($upload_dir)) {
            $_SESSION['error_message'] = "Upload directory is not writable.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            $_SESSION['error_message'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // Validate file size (2MB max)
        if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $_SESSION['error_message'] = "File size must be less than 2MB.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // Generate unique filename
        $filename = $employee_id . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
            $profile_image = $upload_path;
            
            // Delete old profile image if exists
            $stmt = $pdo->prepare("SELECT profile_image FROM credentials WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_data && $old_data['profile_image'] && file_exists($old_data['profile_image'])) {
                unlink($old_data['profile_image']);
            }
        } else {
            $_SESSION['error_message'] = "Failed to upload file. Please try again.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    
    // Update database with better error handling
    try {
        if ($profile_image) {
            $stmt = $pdo->prepare("UPDATE credentials SET role = ?, sub_name = ?, profile_image = ? WHERE employee_id = ?");
            $result = $stmt->execute([$role, $sub_name, $profile_image, $employee_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE credentials SET role = ?, sub_name = ? WHERE employee_id = ?");
            $result = $stmt->execute([$role, $sub_name, $employee_id]);
        }
        
        if ($result) {
            $_SESSION['success_message'] = "Profile updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update profile in database.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        // Log the error (in production, don't show detailed error to user)
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating profile: Database error occurred.";
    }
}

// Handle adding important dates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_important_date'])) {
    $employee_id = $_SESSION['employee_id'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $event_name = $_POST['event_name'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO important_dates (employee_id, event_date, event_time, event_name) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$employee_id, $event_date, $event_time, $event_name]);
        
        if ($result) {
            $_SESSION['success_message'] = "Important date added successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to add important date.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error adding important date: Database error occurred.";
    }
}

// Handle To-Do List operations
// Add new to-do item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_todo'])) {
    $employee_id = $_SESSION['employee_id'];
    $task_text = $_POST['task_text'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO todo_items (employee_id, task_text) VALUES (?, ?)");
        $result = $stmt->execute([$employee_id, $task_text]);
        
        if ($result) {
            $_SESSION['success_message'] = "Task added successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to add task.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error adding task: Database error occurred.";
    }
}

// Update to-do item status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_todo'])) {
    $todo_id = $_POST['todo_id'];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE todo_items SET is_completed = ? WHERE id = ?");
        $result = $stmt->execute([$is_completed, $todo_id]);
        
        if ($result) {
            $_SESSION['success_message'] = "Task updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update task.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating task: Database error occurred.";
    }
}

// Delete to-do item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_todo'])) {
    $todo_id = $_POST['todo_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM todo_items WHERE id = ?");
        $result = $stmt->execute([$todo_id]);
        
        if ($result) {
            $_SESSION['success_message'] = "Task deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete task.";
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting task: Database error occurred.";
    }
}

// Fetch employee data
 $stmt = $pdo->prepare("SELECT * FROM credentials WHERE employee_id = ?");
 $stmt->execute([$_SESSION['employee_id']]);
 $employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found");
}

// Fetch class schedule for today - FIXED QUERY
 $today = date('l'); // Gets the day name (e.g., Monday)
 $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? AND day = ? ORDER BY start_time ASC");
 $stmt->execute([$_SESSION['employee_id'], $today]);
 $today_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no classes today, try to get classes for the week
if (empty($today_classes)) {
    $week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $placeholders = implode(',', array_fill(0, count($week_days), '?'));
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? AND day IN ($placeholders) ORDER BY day, start_time ASC");
    $params = array_merge([$_SESSION['employee_id']], $week_days);
    $stmt->execute($params);
    $week_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch important dates
 $stmt = $pdo->prepare("SELECT * FROM important_dates WHERE employee_id = ? ORDER BY event_date ASC, event_time ASC");
 $stmt->execute([$_SESSION['employee_id']]);
 $important_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process important dates to check completion status
 $processed_dates = [];
 $current_datetime = new DateTime();

foreach ($important_dates as $date) {
    $event_datetime = new DateTime($date['event_date']);
    if ($date['event_time']) {
        // Add time to date
        $time_parts = explode(':', $date['event_time']);
        $event_datetime->setTime((int)$time_parts[0], (int)$time_parts[1]);
    } else {
        // If no time, set to end of day (23:59)
        $event_datetime->setTime(23, 59, 59);
    }
    
    // Check if event is completed
    $is_completed = $event_datetime < $current_datetime;
    
    // Add completion status to the date array
    $date['is_completed'] = $is_completed;
    $processed_dates[] = $date;
}

// Fetch to-do items
 $stmt = $pdo->prepare("SELECT * FROM todo_items WHERE employee_id = ? ORDER BY created_at DESC");
 $stmt->execute([$_SESSION['employee_id']]);
 $todo_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count tasks
 $total_tasks = count($todo_items);
 $completed_tasks = 0;
foreach ($todo_items as $task) {
    if ($task['is_completed']) {
        $completed_tasks++;
    }
}

// Fetch meetings from database - FIXED QUERY
try {
    // Get today's date
    $today_date = date('Y-m-d');
    
    // Check if meetings table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'meetings'");
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        // Fetch meetings for today and upcoming meetings (next 7 days)
        $stmt = $pdo->prepare("
            SELECT * FROM meetings 
            WHERE (date = ? OR (date > ? AND date <= DATE_ADD(?, INTERVAL 7 DAY)))
            AND (created_by = ? OR department = ?)
            ORDER BY date ASC, time ASC
        ");
        $stmt->execute([$today_date, $today_date, $today_date, $_SESSION['employee_id'], $employee['department']]);
        $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process meetings to check status
        $processed_meetings = [];
        $current_time = date('H:i:s');
        
        foreach ($meetings as $meeting) {
            $meeting_date = $meeting['date'];
            $meeting_time = $meeting['time'];
            
            // Calculate end time based on duration
            $duration_minutes = $meeting['duration'];
            $end_time = date('H:i:s', strtotime($meeting_time) + ($duration_minutes * 60));
            
            // Determine meeting status
            $status = '';
            $status_class = '';
            
            if ($meeting_date < $today_date) {
                $status = 'Completed';
                $status_class = 'completed-meeting-badge';
            } elseif ($meeting_date == $today_date) {
                if ($current_time < $meeting_time) {
                    $status = 'Upcoming';
                    $status_class = 'upcoming-meeting-badge';
                } elseif ($current_time >= $meeting_time && $current_time <= $end_time) {
                    $status = 'Ongoing';
                    $status_class = 'ongoing-meeting-badge';
                } else {
                    $status = 'Completed';
                    $status_class = 'completed-meeting-badge';
                }
            } else {
                $status = 'Upcoming';
                $status_class = 'upcoming-meeting-badge';
            }
            
            // Add status to meeting array
            $meeting['status'] = $status;
            $meeting['status_class'] = $status_class;
            $meeting['end_time'] = $end_time;
            
            // Format date for display
            $date_obj = new DateTime($meeting_date);
            $meeting['formatted_date'] = $date_obj->format('F j, Y');
            
            // Format time for display
            $time_obj = new DateTime($meeting_time);
            $meeting['formatted_time'] = $time_obj->format('g:i A');
            
            $end_time_obj = new DateTime($end_time);
            $meeting['formatted_end_time'] = $end_time_obj->format('g:i A');
            
            $processed_meetings[] = $meeting;
        }
    } else {
        // Create a sample meeting for demonstration
        $processed_meetings = [
            [
                'id' => 1,
                'meeting_id' => 'DEPT001',
                'purpose' => 'Department Meeting',
                'date' => $today_date,
                'time' => '14:00:00',
                'duration' => 60,
                'location' => 'Conference Room',
                'status' => 'Upcoming',
                'status_class' => 'upcoming-meeting-badge',
                'end_time' => '15:00:00',
                'formatted_date' => date('F j, Y'),
                'formatted_time' => '2:00 PM',
                'formatted_end_time' => '3:00 PM'
            ]
        ];
    }
} catch(PDOException $e) {
    // If meetings table doesn't exist or there's an error, create empty array
    $processed_meetings = [];
    error_log("Error fetching meetings: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: tlogin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script id="tailwind-config">
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
                        display: ["Inter"],
                    },
                    borderRadius: { 
                        DEFAULT: "0.5rem", 
                        lg: "0.75rem", 
                        xl: "1rem", 
                        full: "9999px" 
                    },
                    boxShadow: {
                        'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                        'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                        'modal': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.9)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                },
            },
        };
    </script>
    <title>Teacher Dashboard - <?php echo htmlspecialchars($employee['emp_name']); ?></title>
    <link href="data:image/x-icon;base64," rel="icon" type="image/x-icon"/>
    <style>
        /* Custom scrollbar */
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
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .dark ::-webkit-scrollbar-track {
            background: #1e293b;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }
        .dark ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        /* Gradient backgrounds */
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .gradient-success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }
        .gradient-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .gradient-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        /* Card hover effects */
        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Sidebar link effects */
        .sidebar-link {
            position: relative;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .sidebar-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: #4f46e5;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar-link:hover::before {
            transform: translateX(0);
        }
        
        /* Button effects */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: -1;
        }
        .btn-primary:hover::before {
            transform: translateX(0);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }
        
        /* Profile image animation */
        .profile-img {
            transition: all 0.5s ease;
        }
        .profile-img:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Modal animations */
        .modal-backdrop {
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        .modal-content {
            animation: bounceIn 0.6s ease-out;
        }
        
        /* Table styles */
        .custom-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .custom-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .dark .custom-table th {
            background-color: #1e293b;
            border-bottom: 2px solid #475569;
        }
        .custom-table td {
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .custom-table td {
            border-bottom: 1px solid #475569;
        }
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Toast notification styles */
        .toast {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Input focus styles */
        .custom-input:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        /* Checkbox custom styles */
        .custom-checkbox {
            appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #cbd5e1;
            border-radius: 0.25rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .custom-checkbox:checked {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        .custom-checkbox:checked::before {
            content: '✓';
            display: block;
            text-align: center;
            color: white;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        /* Completed event styles */
        .completed-event {
            opacity: 0.6;
            text-decoration: line-through;
        }
        
        .completed-badge {
            background-color: #6b7280;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .upcoming-badge {
            background-color: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        /* Class status badge */
        .ongoing-badge {
            background-color: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .upcoming-class-badge {
            background-color: #3b82f6;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .completed-class-badge {
            background-color: #6b7280;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        /* Meeting status badges */
        .ongoing-meeting-badge {
            background-color: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .upcoming-meeting-badge {
            background-color: #3b82f6;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .completed-meeting-badge {
            background-color: #6b7280;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-gray-800 dark:text-gray-200">
    <!-- Welcome Toast - Show only on fresh login -->
    <?php if (!isset($_SESSION['welcome_shown'])): ?>
      <div id="welcome-toast" class="toast fixed bottom-6 right-6 z-50 gradient-primary text-white px-6 py-4 rounded-xl shadow-lg transform translate-y-full opacity-0 transition-all duration-500 ease-in-out max-w-md w-80">
    <div class="flex items-center space-x-3">
        <div class="flex-shrink-0">
            <i class="fas fa-user-check text-2xl"></i>
        </div>
        <div class="flex-1">
            <h4 class="text-sm font-semibold">Welcome back, <?php echo htmlspecialchars($employee['emp_name']); ?>! 👋</h4>
            <p id="toast-time" class="text-xs opacity-90 mt-1">Loading time...</p>
        </div>
    </div>
</div>
        <?php $_SESSION['welcome_shown'] = true; ?>
    <?php endif; ?>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="success-alert" class="fixed top-4 right-4 z-50 bg-success text-white px-6 py-3 rounded-lg shadow-lg animate-fade-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="error-alert" class="fixed top-4 right-4 z-50 bg-danger text-white px-6 py-3 rounded-lg shadow-lg animate-fade-in">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white dark:bg-background-dark rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-key mr-2 text-primary"></i>
                        Change Password
                    </h2>
                    <button id="closePasswordModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form method="POST" class="space-y-6" id="changePasswordForm">
                    <!-- Current Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Current Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="current_password" 
                                id="current_password"
                                required 
                                placeholder="Enter your current password"
                                class="custom-input w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-all"
                            >
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="new_password" 
                                id="new_password"
                                required 
                                minlength="6"
                                placeholder="Enter new password (min. 6 characters)"
                                class="custom-input w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-all"
                            >
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-strength" class="mt-2 text-xs">
                            <div class="flex space-x-1">
                                <div id="strength-bar-1" class="h-1.5 flex-1 bg-gray-300 rounded-full transition-all"></div>
                                <div id="strength-bar-2" class="h-1.5 flex-1 bg-gray-300 rounded-full transition-all"></div>
                                <div id="strength-bar-3" class="h-1.5 flex-1 bg-gray-300 rounded-full transition-all"></div>
                                <div id="strength-bar-4" class="h-1.5 flex-1 bg-gray-300 rounded-full transition-all"></div>
                            </div>
                            <p id="strength-text" class="mt-1 text-gray-500 dark:text-gray-400">Password strength will be shown here</p>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirm_password"
                                required 
                                minlength="6"
                                placeholder="Confirm new password"
                                class="custom-input w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white transition-all"
                            >
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="password-match" class="mt-2 text-xs hidden">
                            <p id="match-text"></p>
                        </div>
                    </div>

                    <!-- Password Requirements -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-primary"></i>
                            Password Requirements:
                        </h4>
                        <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2 text-gray-400"></i>
                                At least 6 characters long
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2 text-gray-400"></i>
                                Mix of uppercase and lowercase letters (recommended)
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check mr-2 text-gray-400"></i>
                                Include numbers and special characters (recommended)
                            </li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <button 
                            type="button" 
                            id="cancelPasswordBtn"
                            class="px-5 py-2.5 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            name="change_password"
                            id="changePasswordSubmit"
                            class="btn-primary px-5 py-2.5 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <i class="fas fa-save mr-2"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white dark:bg-background-dark rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-user-edit mr-2 text-primary"></i>
                        Edit Profile
                    </h2>
                    <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Current Profile Image -->
                    <div class="text-center">
                        <div class="relative inline-block">
                            <?php if (isset($employee['profile_image']) && $employee['profile_image']): ?>
                                <img id="current-avatar" src="<?php echo htmlspecialchars($employee['profile_image']); ?>" alt="Profile" class="profile-img rounded-full w-24 h-24 object-cover border-4 border-primary">
                            <?php else: ?>
                                <div id="current-avatar" class="profile-img rounded-full w-24 h-24 gradient-primary flex items-center justify-center text-white text-xl font-bold border-4 border-primary">
                                    <?php 
                                    $initials = '';
                                    $nameParts = explode(' ', $employee['emp_name']);
                                    foreach($nameParts as $part) {
                                        if(!empty($part) && !in_array(strtolower($part), ['mr.', 'mrs.', 'dr.', 'miss.'])) {
                                            $initials .= strtoupper($part[0]);
                                            if(strlen($initials) >= 2) break;
                                        }
                                    }
                                    echo $initials;
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Image Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Profile Image
                        </label>
                        <div class="flex items-center justify-center w-full">
                            <label for="profile_image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-bray-800 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600 transition-all">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">JPG, JPEG, PNG, or GIF (MAX. 2MB)</p>
                                </div>
                                <input id="profile_image" name="profile_image" type="file" class="hidden" accept="image/jpeg,image/jpg,image/png,image/gif" />
                            </label>
                        </div>
                    </div>

                    <!-- Employee Name (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Employee Name
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                value="<?php echo htmlspecialchars($employee['emp_name']); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 text-gray-500 dark:text-gray-400 pl-10" 
                                readonly
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Employee ID (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Employee ID
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                value="<?php echo htmlspecialchars($employee['employee_id']); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 text-gray-500 dark:text-gray-400 pl-10" 
                                readonly
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-badge text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Department (Read-only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Department
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                value="<?php echo htmlspecialchars($employee['department']); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 dark:bg-gray-700 dark:border-gray-600 text-gray-500 dark:text-gray-400 pl-10" 
                                readonly
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-building text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Role (Editable) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select 
                                name="role" 
                                required 
                                class="custom-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white appearance-none pl-10"
                            >
                                <option value="">Select Role</option>
                                <option value="Professor" <?php echo ($employee['role'] === 'Professor') ? 'selected' : ''; ?>>Professor</option>
                                <option value="Associate Professor" <?php echo ($employee['role'] === 'Associate Professor') ? 'selected' : ''; ?>>Associate Professor</option>
                                <option value="Assistant Professor" <?php echo ($employee['role'] === 'Assistant Professor') ? 'selected' : ''; ?>>Assistant Professor</option>
                                <option value="Lecturer" <?php echo ($employee['role'] === 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                <option value="Senior Lecturer" <?php echo ($employee['role'] === 'Senior Lecturer') ? 'selected' : ''; ?>>Senior Lecturer</option>
                                <option value="Teaching Assistant" <?php echo ($employee['role'] === 'Teaching Assistant') ? 'selected' : ''; ?>>Teaching Assistant</option>
                            </select>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-briefcase text-gray-400"></i>
                            </div>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Subject (Editable) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="sub_name" 
                                value="<?php echo htmlspecialchars($employee['sub_name'] ?? ''); ?>" 
                                required 
                                placeholder="Enter subject name"
                                class="custom-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white pl-10"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-book text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <button 
                            type="button" 
                            id="cancelBtn"
                            class="px-5 py-2.5 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            name="update_profile"
                            id="updateProfileSubmit"
                            class="btn-primary px-5 py-2.5 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <i class="fas fa-save mr-2"></i>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Important Date Modal -->
    <div id="addImportantDateModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="modal-content bg-white dark:bg-background-dark rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-calendar-plus mr-2 text-primary"></i>
                        Add Important Date
                    </h2>
                    <button id="closeImportantDateModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form method="POST" class="space-y-6" id="addImportantDateForm">
                    <!-- Event Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Event Date <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="date" 
                                name="event_date" 
                                id="event_date"
                                required 
                                class="custom-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white pl-10"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Event Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Event Time
                        </label>
                        <div class="relative">
                            <input 
                                type="time" 
                                name="event_time" 
                                id="event_time"
                                class="custom-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white pl-10"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Event Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Event Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="event_name" 
                                id="event_name"
                                required 
                                placeholder="Enter event name"
                                class="custom-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white pl-10"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-tag text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <button 
                            type="button" 
                            id="cancelImportantDateBtn"
                            class="px-5 py-2.5 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            name="add_important_date"
                            id="addImportantDateSubmit"
                            class="btn-primary px-5 py-2.5 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <i class="fas fa-save mr-2"></i>
                            Add Date
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="flex h-screen">
        <!-- Fixed Sidebar -->
        <aside class="fixed left-0 top-0 w-80 h-full bg-sidebar-light dark:bg-sidebar-dark border-r border-gray-200 dark:border-gray-700 flex flex-col p-6 overflow-y-auto z-10 shadow-lg">
            <div class="flex flex-col items-center text-center space-y-4 mb-8 animate-fade-in">
                <div class="relative">
                    <?php if (isset($employee['profile_image']) && $employee['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($employee['profile_image']); ?>" alt="Profile" class="profile-img rounded-full w-32 h-32 object-cover border-4 border-white shadow-lg">
                    <?php else: ?>
                        <!-- Default avatar image -->
                        <div class="profile-img rounded-full w-32 h-32 gradient-primary flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-lg">
                            <?php 
                            $initials = '';
                            $nameParts = explode(' ', $employee['emp_name']);
                            foreach($nameParts as $part) {
                                if(!empty($part) && !in_array(strtolower($part), ['mr.', 'mrs.', 'dr.', 'miss.'])) {
                                    $initials .= strtoupper($part[0]);
                                    if(strlen($initials) >= 2) break;
                                }
                            }
                            echo $initials;
                            ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 bg-success rounded-full p-1 border-2 border-white">
                        <i class="fas fa-check text-white text-xs"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($employee['emp_name']); ?></h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Employee ID: <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Department: <?php echo htmlspecialchars($employee['department']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Role: <?php echo htmlspecialchars($employee['role']); ?></p>
                    <?php if ($employee['sub_name']): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Subject: <?php echo htmlspecialchars($employee['sub_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <button id="editProfileBtn" class="mb-4 w-full flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all shadow-md hover:shadow-lg">
                <i class="fas fa-user-edit mr-2"></i>
                Edit Profile
            </button>
            
            <button id="changePasswordBtn" class="mb-8 w-full flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-warning rounded-lg hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-warning transition-all shadow-md hover:shadow-lg">
                <i class="fas fa-key mr-2"></i>
                Change Password
            </button>
            
            <nav class="flex-1 space-y-2">
                <a class="sidebar-link flex items-center px-4 py-3 text-white bg-primary rounded-lg shadow-md" href="tdash.php">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="cstruct.php">
                    <i class="fas fa-sitemap mr-3"></i>
                    Course Structure
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="cupdate.php">
                    <i class="fas fa-edit mr-3"></i>
                    Course Update
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="all.php">
                    <i class="fas fa-list mr-3"></i>
                    All Structures
                </a>
            </nav>
            
            <div class="mt-auto">
                <!-- Separator line -->
                <div class="border-t border-gray-200 dark:border-gray-700 mb-4"></div>
                
                <a class="flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="?logout=1">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 ml-80 h-screen overflow-y-auto p-8 bg-gray-50 dark:bg-gray-900">
            <div class="flex justify-between items-center mb-8 animate-fade-in">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Welcome, <span class="text-primary"><?php echo explode(' ', $employee['emp_name'])[count(explode(' ', $employee['emp_name']))-1]; ?></span>
                </h1>
                <div class="text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>
            
<!-- Full-width Class Schedule -->
<div class="bg-white dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
            <i class="fas fa-calendar-week mr-2 text-primary"></i>
            Class Schedule
        </h2>
        <span class="text-xs bg-primary text-white px-2 py-1 rounded-full">Week View</span>
    </div>
    <div class="overflow-x-auto">
        <table class="custom-table w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th class="px-6 py-3" scope="col">Day</th>
                    <th class="px-6 py-3" scope="col">Time</th>
                    <th class="px-6 py-3" scope="col">Course</th>
                    <th class="px-6 py-3" scope="col">Room</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Fetch all classes for the week
                $week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                $placeholders = implode(',', array_fill(0, count($week_days), '?'));
                $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? AND day IN ($placeholders) ORDER BY day, start_time ASC");
                $params = array_merge([$_SESSION['employee_id']], $week_days);
                $stmt->execute($params);
                $all_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $current_time = date('H:i:s');
                $today = date('l');
                
                if (count($all_classes) > 0): 
                    foreach ($all_classes as $class): 
                        $start_time = $class['start_time'];
                        $end_time = $class['end_time'];
                        $day = $class['day'];
                        
                        // Determine status based on day and time
                        $status = '';
                        $status_class = '';
                        
                        if ($day === $today) {
                            if ($current_time < $start_time) {
                                $status = 'Upcoming';
                                $status_class = 'upcoming-class-badge';
                            } elseif ($current_time >= $start_time && $current_time <= $end_time) {
                                $status = 'Ongoing';
                                $status_class = 'ongoing-badge';
                            } else {
                                $status = 'Completed';
                                $status_class = 'completed-class-badge';
                            }
                        } else {
                            // For other days, just show the day name without status
                            $status = '';
                            $status_class = '';
                        }
                ?>
                    <tr class="bg-white <?php echo $class !== end($all_classes) ? 'border-b' : ''; ?> dark:bg-card-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="px-6 py-4 font-medium">
                            <?php echo htmlspecialchars($day); ?>
                            <?php if ($day === $today): ?>
                                <span class="ml-2 text-xs bg-primary text-white px-2 py-1 rounded-full">Today</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 font-medium">
                            <?php 
                            $start_formatted = date('h:i A', strtotime($start_time));
                            $end_formatted = date('h:i A', strtotime($end_time));
                            echo $start_formatted . ' - ' . $end_formatted;
                            ?>
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($class['course_name']); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($class['course_code']); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Year <?php echo htmlspecialchars($class['year']); ?>, Sem <?php echo htmlspecialchars($class['semester']); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Section <?php echo htmlspecialchars($class['section']); ?></p>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php echo htmlspecialchars($class['room']); ?>
                            <?php if ($status): ?>
                                <div class="mt-2">
                                    <span class="<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php else: ?>
                    <tr class="bg-white dark:bg-card-dark">
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-calendar-times text-3xl mb-2"></i>
                            <p>No classes scheduled for this week.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pb-8">
                <!-- Important Dates -->
                <div class="bg-white dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                            Important Dates
                        </h2>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs bg-danger text-white px-2 py-1 rounded-full">Upcoming</span>
                            <button id="addImportantDateBtn" class="text-base bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors shadow-sm hover:shadow-md">
                                <i class="fas fa-plus mr-2"></i>
                                Add
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="custom-table w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3" scope="col">Date</th>
                                    <th class="px-6 py-3" scope="col">Time</th>
                                    <th class="px-6 py-3" scope="col">Event</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Filter out completed events (optional - comment out to show all events)
                                $upcoming_dates = array_filter($processed_dates, function($date) {
                                    return !$date['is_completed'];
                                });
                                
                                if (count($upcoming_dates) > 0): 
                                    foreach ($upcoming_dates as $index => $date): ?>
                                        <tr class="bg-white <?php echo $index < count($upcoming_dates)-1 ? 'border-b' : ''; ?> dark:bg-card-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <td class="px-6 py-4 font-medium">
                                                <?php 
                                                $dateObj = new DateTime($date['event_date']);
                                                echo $dateObj->format('F j, Y');
                                                ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                if ($date['event_time']) {
                                                    $timeObj = new DateTime($date['event_time']);
                                                    echo $timeObj->format('g:i A');
                                                } else {
                                                    echo '<span class="text-gray-400 dark:text-gray-500">All Day</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-between">
                                                    <span><?php echo htmlspecialchars($date['event_name']); ?></span>
                                                    <span class="upcoming-badge">Upcoming</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="bg-white dark:bg-card-dark">
                                        <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No upcoming important dates. Click the "Add" button to add one.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- To-Do List -->
                <div class="bg-white dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-tasks mr-2 text-primary"></i>
                            To-Do List
                        </h2>
                        <span class="text-xs bg-warning text-white px-2 py-1 rounded-full"><?php echo $total_tasks; ?> Tasks</span>
                    </div>
                    
                    <!-- Add new task form -->
                    <form method="POST" class="mb-4 flex">
                        <input 
                            type="text" 
                            name="task_text" 
                            id="new_task"
                            placeholder="Add a new task..." 
                            class="custom-input flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            required
                        >
                        <button 
                            type="submit" 
                            name="add_todo"
                            class="px-4 py-2 bg-primary text-white rounded-r-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary transition-all"
                        >
                            <i class="fas fa-plus"></i>
                        </button>
                    </form>
                    
                    <div class="space-y-4 max-h-64 overflow-y-auto">
                        <?php if (count($todo_items) > 0): ?>
                            <?php foreach ($todo_items as $task): ?>
                                <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <form method="POST" class="flex items-center w-full">
                                        <input type="hidden" name="todo_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="update_todo" value="1">
                                        <input 
                                            type="checkbox" 
                                            name="is_completed" 
                                            value="1"
                                            class="custom-checkbox mr-3" 
                                            id="todo_<?php echo $task['id']; ?>"
                                            <?php echo $task['is_completed'] ? 'checked' : ''; ?>
                                            onchange="this.form.submit()"
                                        >
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 flex-1 <?php echo $task['is_completed'] ? 'line-through opacity-60' : ''; ?>" for="todo_<?php echo $task['id']; ?>">
                                            <?php echo htmlspecialchars($task['task_text']); ?>
                                        </label>
                                        <button 
                                            type="button" 
                                            class="ml-2 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                            onclick="deleteTask(<?php echo $task['id']; ?>)"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                                <i class="fas fa-clipboard-list text-3xl mb-2"></i>
                                <p>No tasks yet. Add your first task above!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_tasks > 0): ?>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                <span>Completed: <?php echo $completed_tasks; ?></span>
                                <span>Remaining: <?php echo $total_tasks - $completed_tasks; ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mt-2">
                                <div class="bg-primary h-2 rounded-full" style="width: <?php echo $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Meetings - Full Width (outside the grid) -->
            </div>
            
            <div class="bg-white dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up mb-8" style="animation-delay: 0.3s">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-users mr-2 text-primary"></i>
                        Meetings
                    </h2>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs bg-success text-white px-2 py-1 rounded-full">Today & Upcoming</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="custom-table w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3" scope="col">Date</th>
                                <th class="px-6 py-3" scope="col">Time</th>
                                <th class="px-6 py-3" scope="col">Purpose</th>
                                <th class="px-6 py-3" scope="col">Location</th>
                                <th class="px-6 py-3" scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($processed_meetings) > 0): ?>
                                <?php foreach ($processed_meetings as $index => $meeting): ?>
                                    <tr class="bg-white <?php echo $index < count($processed_meetings)-1 ? 'border-b' : ''; ?> dark:bg-card-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4 font-medium">
                                            <?php 
                                            if ($meeting['date'] == date('Y-m-d')) {
                                                echo 'Today';
                                            } else {
                                                echo $meeting['formatted_date'];
                                            }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php echo $meeting['formatted_time'] . ' - ' . $meeting['formatted_end_time']; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($meeting['purpose']); ?></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">ID: <?php echo htmlspecialchars($meeting['meeting_id']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php echo htmlspecialchars($meeting['location']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="<?php echo $meeting['status_class']; ?>"><?php echo $meeting['status']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="bg-white dark:bg-card-dark">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-users-slash text-3xl mb-2"></i>
                                        <p>No meetings scheduled.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden form for deleting tasks -->
    <form id="deleteTaskForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_todo" value="1">
        <input type="hidden" name="todo_id" id="delete_todo_id">
    </form>

    <script>
        // Password visibility toggle
        window.togglePassword = function(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        };

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const bars = [
                document.getElementById('strength-bar-1'),
                document.getElementById('strength-bar-2'),
                document.getElementById('strength-bar-3'),
                document.getElementById('strength-bar-4')
            ];
            const strengthText = document.getElementById('strength-text');

            // Reset bars
            bars.forEach(bar => {
                bar.className = 'h-1.5 flex-1 bg-gray-300 rounded-full transition-all';
            });

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;

            const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const strengthColors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
            const strengthTextColors = ['text-red-600 dark:text-red-400', 'text-orange-600 dark:text-orange-400', 'text-yellow-600 dark:text-yellow-400', 'text-green-600 dark:text-green-400'];

            for (let i = 0; i < strength; i++) {
                bars[i].className = `h-1.5 flex-1 ${strengthColors[i]} rounded-full transition-all`;
            }

            strengthText.textContent = strengthLevels[strength];
            strengthText.className = `mt-1 text-xs ${strengthTextColors[strength-1] || 'text-red-600 dark:text-red-400'}`;
        }

        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            const matchText = document.getElementById('match-text');

            if (confirmPassword.length > 0) {
                matchDiv.classList.remove('hidden');
                if (newPassword === confirmPassword) {
                    matchText.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Passwords match';
                    matchText.className = 'text-green-600 dark:text-green-400';
                } else {
                    matchText.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Passwords do not match';
                    matchText.className = 'text-red-600 dark:text-red-400';
                }
            } else {
                matchDiv.classList.add('hidden');
            }
        }

        // Change Password Modal functionality
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const changePasswordModal = document.getElementById('changePasswordModal');
        const closePasswordModal = document.getElementById('closePasswordModal');
        const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
        const changePasswordForm = document.getElementById('changePasswordForm');
        
        // Open change password modal
        changePasswordBtn.addEventListener('click', () => {
            changePasswordModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
        
        // Close change password modal functions
        const closePasswordModalFunc = () => {
            changePasswordModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            // Reset form
            changePasswordForm.reset();
            document.getElementById('password-match').classList.add('hidden');
            checkPasswordStrength('');
        };
        
        closePasswordModal.addEventListener('click', closePasswordModalFunc);
        cancelPasswordBtn.addEventListener('click', closePasswordModalFunc);
        
        // Close modal when clicking outside
        changePasswordModal.addEventListener('click', (e) => {
            if (e.target === changePasswordModal) {
                closePasswordModalFunc();
            }
        });
        
        // Password strength and match checking
        document.getElementById('new_password').addEventListener('input', (e) => {
            checkPasswordStrength(e.target.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Form validation - completely separate for each form
        
        // Edit Profile Form validation ONLY
        const editProfileForm = document.querySelector('#editProfileModal form');
        if (editProfileForm) {
            editProfileForm.addEventListener('submit', function(e) {
                const role = this.querySelector('select[name="role"]').value;
                const subName = this.querySelector('input[name="sub_name"]').value;
                
                if (!role.trim()) {
                    e.preventDefault();
                    showNotification('Please select a role.', 'error');
                    return;
                }
                
                if (!subName.trim()) {
                    e.preventDefault();
                    showNotification('Please enter a subject name.', 'error');
                    return;
                }
            });
        }
        
        // Change Password Form validation ONLY
        const changePasswordFormElement = document.querySelector('#changePasswordModal form');
        if (changePasswordFormElement) {
            changePasswordFormElement.addEventListener('submit', function(e) {
                const currentPassword = this.querySelector('#current_password').value;
                const newPassword = this.querySelector('#new_password').value;
                const confirmPassword = this.querySelector('#confirm_password').value;
                
                if (!currentPassword.trim()) {
                    e.preventDefault();
                    showNotification('Please enter your current password.', 'error');
                    return;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    showNotification('New password must be at least 6 characters long.', 'error');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showNotification('New passwords do not match.', 'error');
                    return;
                }
                
                if (newPassword === currentPassword) {
                    e.preventDefault();
                    showNotification('New password must be different from current password.', 'error');
                    return;
                }
            });
        }

        // Add Important Date Modal functionality
        const addImportantDateBtn = document.getElementById('addImportantDateBtn');
        const addImportantDateModal = document.getElementById('addImportantDateModal');
        const closeImportantDateModal = document.getElementById('closeImportantDateModal');
        const cancelImportantDateBtn = document.getElementById('cancelImportantDateBtn');
        const addImportantDateForm = document.getElementById('addImportantDateForm');
        
        // Open add important date modal
        addImportantDateBtn.addEventListener('click', () => {
            addImportantDateModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
        
        // Close add important date modal functions
        const closeImportantDateModalFunc = () => {
            addImportantDateModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            // Reset form
            addImportantDateForm.reset();
        };
        
        closeImportantDateModal.addEventListener('click', closeImportantDateModalFunc);
        cancelImportantDateBtn.addEventListener('click', closeImportantDateModalFunc);
        
        // Close modal when clicking outside
        addImportantDateModal.addEventListener('click', (e) => {
            if (e.target === addImportantDateModal) {
                closeImportantDateModalFunc();
            }
        });
        
        // Add Important Date Form validation
        const addImportantDateFormElement = document.querySelector('#addImportantDateModal form');
        if (addImportantDateFormElement) {
            addImportantDateFormElement.addEventListener('submit', function(e) {
                const eventDate = this.querySelector('#event_date').value;
                const eventName = this.querySelector('#event_name').value;
                
                if (!eventDate.trim()) {
                    e.preventDefault();
                    showNotification('Please select an event date.', 'error');
                    return;
                }
                
                if (!eventName.trim()) {
                    e.preventDefault();
                    showNotification('Please enter an event name.', 'error');
                    return;
                }
            });
        }

        // Update the welcome toast time with user's local time
        function updateToastTime() {
            const toastTime = document.getElementById('toast-time');
            if (toastTime) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
                toastTime.textContent = timeString;
            }
        }

        // Update time immediately when page loads
        updateToastTime();

        // Welcome toast functionality (updated)
        const welcomeToast = document.getElementById('welcome-toast');

        if (welcomeToast) {
            // Update time before showing toast
            updateToastTime();
            
            // Show toast with animation after a brief delay
            setTimeout(() => {
                welcomeToast.classList.remove('translate-y-full', 'opacity-0');
                welcomeToast.classList.add('translate-y-0', 'opacity-100');
            }, 500);
            
            // Hide toast after 5 seconds
            setTimeout(() => {
                welcomeToast.classList.remove('translate-y-0', 'opacity-100');
                welcomeToast.classList.add('translate-y-full', 'opacity-0');
                
                // Remove from DOM after animation completes
                setTimeout(() => {
                    if (welcomeToast.parentNode) {
                        welcomeToast.remove();
                    }
                }, 500);
            }, 5500); // 5 seconds + 0.5s initial delay
        }
            
        // Edit Profile Modal functionality
        const editProfileBtn = document.getElementById('editProfileBtn');
        const editProfileModal = document.getElementById('editProfileModal');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        
        // Open modal
        editProfileBtn.addEventListener('click', () => {
            editProfileModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent body scroll
        });
        
        // Close modal functions
        const closeModalFunc = () => {
            editProfileModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Re-enable body scroll
        };
        
        closeModal.addEventListener('click', closeModalFunc);
        cancelBtn.addEventListener('click', closeModalFunc);
        
        // Close modal when clicking outside
        editProfileModal.addEventListener('click', (e) => {
            if (e.target === editProfileModal) {
                closeModalFunc();
            }
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (!editProfileModal.classList.contains('hidden')) {
                    closeModalFunc();
                }
                if (!changePasswordModal.classList.contains('hidden')) {
                    closePasswordModalFunc();
                }
                if (!addImportantDateModal.classList.contains('hidden')) {
                    closeImportantDateModalFunc();
                }
            }
        });
        
        // Image preview functionality
        const profileImageInput = document.getElementById('profile_image');
        const currentAvatar = document.getElementById('current-avatar');
        
        if (profileImageInput && currentAvatar) {
            profileImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        showNotification('Please select a valid image file (JPEG, JPG, PNG, or GIF).', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file size (2MB = 2 * 1024 * 1024 bytes)
                    if (file.size > 2 * 1024 * 1024) {
                        showNotification('File size must be less than 2MB.', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        currentAvatar.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="profile-img rounded-full w-24 h-24 object-cover border-4 border-primary">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Auto-hide alerts
        const successAlert = document.getElementById('success-alert');
        const errorAlert = document.getElementById('error-alert');
        
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.remove();
                }, 300);
            }, 5000);
        }
        
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                setTimeout(() => {
                    errorAlert.remove();
                }, 300);
            }, 5000);
        }
        
        // Custom notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg animate-fade-in ${
                type === 'success' ? 'bg-success' : 'bg-danger'
            } text-white`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }
        
        // Dark mode toggle (if needed)
        function toggleDarkMode() {
            document.body.classList.toggle('dark');
            localStorage.setItem('darkMode', document.body.classList.contains('dark'));
        }
        
        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark');
        }
        
        // Function to delete task
        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                document.getElementById('delete_todo_id').value = taskId;
                document.getElementById('deleteTaskForm').submit();
            }
        }
    </script>
</body>
</html>