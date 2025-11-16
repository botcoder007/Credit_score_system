<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'credit';
$username = 'root';
$password = '';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: tlogin.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: tlogin.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM credentials WHERE employee_id = ?");
$stmt->execute([$_SESSION['employee_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get course data
    if (isset($_POST['action']) && $_POST['action'] === 'getCourseData') {
        $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
        $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;
        
        if ($year > 0 && $semester > 0) {
            try {
                $stmt = $pdo->prepare("SELECT id, code, title, L, T, P, SL, C FROM course WHERE year = ? AND semester = ? ORDER BY id");
                $stmt->execute([$year, $semester]);
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $formattedCourses = [];
                foreach ($courses as $course) {
                    $formattedCourses[] = [
                        'id' => $course['id'],
                        'code' => $course['code'],
                        'name' => $course['title'],
                        'l' => (int)$course['L'],
                        't' => (int)$course['T'],
                        'p' => (int)$course['P'],
                        'sl' => (int)$course['SL'],
                        'c' => (int)$course['C']
                    ];
                }
                
                echo json_encode(['success' => true, 'data' => $formattedCourses]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid year or semester.']);
        }
        exit();
    }
    
    // Update course - FIXED to prevent NULL values
    if (isset($_POST['action']) && $_POST['action'] === 'updateCourse') {
        $id = (int)$_POST['id'];
        $l = isset($_POST['l']) && $_POST['l'] !== '' ? (int)$_POST['l'] : 0;
        $t = isset($_POST['t']) && $_POST['t'] !== '' ? (int)$_POST['t'] : 0;
        $p = isset($_POST['p']) && $_POST['p'] !== '' ? (int)$_POST['p'] : 0;
        $sl = isset($_POST['sl']) && $_POST['sl'] !== '' ? (int)$_POST['sl'] : 0;
        $c = isset($_POST['c']) && $_POST['c'] !== '' ? (int)$_POST['c'] : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE course SET L = ?, T = ?, P = ?, SL = ?, C = ? WHERE id = ?");
            $stmt->execute([$l, $t, $p, $sl, $c, $id]);
            echo json_encode(['success' => true, 'message' => 'Course updated successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Delete course
    if (isset($_POST['action']) && $_POST['action'] === 'deleteCourse') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM course WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Course deleted successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Add new course with credit validation
    if (isset($_POST['action']) && $_POST['action'] === 'addCourse') {
        $code = trim($_POST['code']);
        $title = trim($_POST['title']);
        $l = isset($_POST['l']) && $_POST['l'] !== '' ? (int)$_POST['l'] : 0;
        $t = isset($_POST['t']) && $_POST['t'] !== '' ? (int)$_POST['t'] : 0;
        $p = isset($_POST['p']) && $_POST['p'] !== '' ? (int)$_POST['p'] : 0;
        $sl = isset($_POST['sl']) && $_POST['sl'] !== '' ? (int)$_POST['sl'] : 0;
        $c = isset($_POST['c']) && $_POST['c'] !== '' ? (int)$_POST['c'] : 0;
        $year = (int)$_POST['year'];
        $semester = (int)$_POST['semester'];
        
        if (empty($code) || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Course code and title are required.']);
            exit();
        }
        
        try {
            // Check current total credits for the year and semester
            $totalStmt = $pdo->prepare("SELECT SUM(C) as total_credits FROM course WHERE year = ? AND semester = ?");
            $totalStmt->execute([$year, $semester]);
            $currentTotal = $totalStmt->fetchColumn() ?? 0;
            
            // Check if adding this course would exceed the limit
            if (($currentTotal + $c) > 25) {
                echo json_encode(['success' => false, 'message' => "Adding this course would exceed the maximum credit limit of 25. Current total: {$currentTotal}, Attempted addition: {$c}"]);
                exit();
            }
            
            // Check if course code already exists for the same year and semester
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM course WHERE code = ? AND year = ? AND semester = ?");
            $checkStmt->execute([$code, $year, $semester]);
            
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Course code already exists for this year and semester.']);
                exit();
            }
            
            $stmt = $pdo->prepare("INSERT INTO course (code, title, L, T, P, SL, C, year, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $title, $l, $t, $p, $sl, $c, $year, $semester]);
            echo json_encode(['success' => true, 'message' => 'Course added successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Save all changes with credit validation
    if (isset($_POST['action']) && $_POST['action'] === 'saveAllChanges') {
        $changes = json_decode($_POST['changes'], true);
        
        try {
            $pdo->beginTransaction();
            
            // First, calculate the total credits that would result from these changes
            $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
            $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;
            
            // Get current courses for this year/semester
            $stmt = $pdo->prepare("SELECT id, C FROM course WHERE year = ? AND semester = ?");
            $stmt->execute([$year, $semester]);
            $currentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalCredits = 0;
            foreach ($currentCourses as $course) {
                $courseId = $course['id'];
                $currentCredits = $course['C'];
                
                // Check if this course has changes
                $hasChanges = false;
                foreach ($changes as $change) {
                    if ($change['id'] == $courseId) {
                        $totalCredits += isset($change['c']) ? (int)$change['c'] : $currentCredits;
                        $hasChanges = true;
                        break;
                    }
                }
                
                // If no changes for this course, use current credits
                if (!$hasChanges) {
                    $totalCredits += $currentCredits;
                }
            }
            
            // Validate total credits
            if ($totalCredits < 20 || $totalCredits > 25) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "Cannot save: Total credits ({$totalCredits}) must be between 20 and 25."]);
                exit();
            }
            
            foreach ($changes as $change) {
                // Ensure values are never NULL - use 0 as default
                $l = isset($change['l']) && $change['l'] !== null ? (int)$change['l'] : 0;
                $t = isset($change['t']) && $change['t'] !== null ? (int)$change['t'] : 0;
                $p = isset($change['p']) && $change['p'] !== null ? (int)$change['p'] : 0;
                $sl = isset($change['sl']) && $change['sl'] !== null ? (int)$change['sl'] : 0;
                $c = isset($change['c']) && $change['c'] !== null ? (int)$change['c'] : 0;
                
                $stmt = $pdo->prepare("UPDATE course SET L = ?, T = ?, P = ?, SL = ?, C = ? WHERE id = ?");
                $stmt->execute([$l, $t, $p, $sl, $c, $change['id']]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'All changes saved successfully.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Get user initials for avatar
$nameParts = explode(' ', $user['emp_name']);
$initials = '';
foreach ($nameParts as $part) {
    if (!empty($part) && ctype_alpha($part[0])) {
        $initials .= strtoupper($part[0]);
        if (strlen($initials) >= 2) break;
    }
}
if (strlen($initials) < 2) {
    $initials = strtoupper(substr($user['emp_name'], 0, 2));
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
    <title>Course Update - Teacher Dashboard</title>
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
        
        /* Modal animations */
        .modal-backdrop {
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        .modal-content {
            animation: bounceIn 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-gray-800 dark:text-gray-200">
    <div class="flex h-screen">
        <!-- Fixed Sidebar -->
        <aside class="fixed left-0 top-0 w-80 h-full bg-sidebar-light dark:bg-sidebar-dark border-r border-gray-200 dark:border-gray-700 flex flex-col p-6 overflow-y-auto z-10 shadow-lg">
            <div class="flex flex-col items-center text-center space-y-4 mb-8 animate-fade-in">
                <div class="relative">
                    <?php if (isset($user['profile_image']) && $user['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="profile-img rounded-full w-32 h-32 object-cover border-4 border-white shadow-lg">
                    <?php else: ?>
                        <!-- Dynamic avatar with user initials -->
                        <div class="profile-img rounded-full w-32 h-32 gradient-primary flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-lg">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                    <?php endif; ?>
                    <div class="absolute bottom-0 right-0 bg-success rounded-full p-1 border-2 border-white">
                        <i class="fas fa-check text-white text-xs"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['emp_name']); ?></h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Department: <?php echo htmlspecialchars($user['department']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Role: <?php echo htmlspecialchars($user['role']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Subject: <?php echo htmlspecialchars($user['sub_name']); ?></p>
                </div>
            </div>
            
            <nav class="flex-1 space-y-2">
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="tdash.php">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="cstruct.php">
                    <i class="fas fa-sitemap mr-3"></i>
                    Course Structure
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-white bg-primary rounded-lg shadow-md" href="cupdate.php">
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
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Course Update</h1>
                <div class="text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <span id="currentDate"></span>
                </div>
            </div>
            
            <div class="bg-white dark:bg-card-dark p-8 rounded-xl shadow-card hover-card animate-slide-up">
                <!-- Year and Semester Selection -->
                <div class="flex gap-6 mb-8">
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                        <select id="yearSelect" class="custom-input w-40 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                            <option value="">Select Year</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Semester</label>
                        <select id="semesterSelect" class="custom-input w-40 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                            <option value="">Select Semester</option>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end gap-3">
                        <button id="loadStructure" class="btn-primary px-6 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-search mr-2"></i>
                            Load Structure
                        </button>
                        
                        <button id="addCourseBtn" class="btn-primary px-6 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-plus mr-2"></i>
                            Add Course
                        </button>
                    </div>
                </div>

                <!-- Course Structure Table -->
                <div id="courseTable" class="hidden">
                    <div class="overflow-x-auto">
                        <table class="custom-table w-full text-sm text-left">
                            <thead class="text-xs text-gray-800 dark:text-gray-200 uppercase bg-gray-100 dark:bg-gray-600 font-bold">
                                <tr>
                                    <th class="px-6 py-4 text-left font-bold">Course Code</th>
                                    <th class="px-6 py-4 text-left font-bold">Course Name</th>
                                    <th class="px-6 py-4 text-center font-bold">L</th>
                                    <th class="px-6 py-4 text-center font-bold">T</th>
                                    <th class="px-6 py-4 text-center font-bold">P</th>
                                    <th class="px-6 py-4 text-center font-bold">SL</th>
                                    <th class="px-6 py-4 text-center font-bold">C</th>
                                    <th class="px-6 py-4 text-center font-bold">Actions</th>
                                </tr>
                            </thead>    
                            <tbody id="courseTableBody">
                                <!-- Course rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Enhanced Summary Section with Credit Range Validation -->
                    <div class="mt-6 flex justify-between items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex gap-8">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Hours</span>
                                <span id="totalHours" class="ml-2 text-lg font-bold text-gray-900 dark:text-white">0</span>
                            </div>
                            <div class="relative">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Credits</span>
                                <span id="totalCredits" class="ml-2 text-lg font-bold text-gray-900 dark:text-white">0</span>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Required range: 20-25 credits
                                </div>
                                <!-- Credit Status Indicator -->
                                <div id="creditStatus" class="flex items-center mt-1 text-xs">
                                    <div id="creditIndicator" class="w-2 h-2 rounded-full mr-2 bg-gray-400"></div>
                                    <span id="creditStatusText" class="text-gray-500">Select courses to validate</span>
                                </div>
                            </div>
                        </div>
                        
                        <button id="saveAllBtn" class="btn-primary flex items-center px-6 py-3 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-save mr-2"></i>
                            Save All Changes
                        </button>
                    </div>
                </div>

                <!-- Placeholder message -->
                <div id="placeholderMessage" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No course structure selected</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select a year and semester to view and update the course structure.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Course Modal -->
    <div id="addCourseModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-content bg-white dark:bg-background-dark rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-modal">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-plus-circle mr-2 text-primary"></i>
                        Add New Course
                    </h2>
                    <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="addCourseForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Course Code</label>
                        <input type="text" id="courseCode" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Course Title</label>
                        <input type="text" id="courseTitle" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all" required>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3">
                         <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">L (0,1,2,3)</label>
                            <select id="courseLecture" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">T (0,2,4)</label>
                            <select id="courseTutorial" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="0">0</option>
                                <option value="2">2</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">P (0,2,4)</label>
                            <select id="coursePractical" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="0">0</option>
                                <option value="2">2</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SL (0,2,4)</label>
                            <select id="courseSelfLearning" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="0">0</option>
                                <option value="2">2</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">C</label>
                            <input type="number" id="courseCredits" min="0" value="0" readonly class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white bg-gray-100 dark:bg-gray-600">
                        </div>
                        
                        <div></div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year (1-4)</label>
                            <select id="courseYear" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Semester (1-2)</label>
                            <select id="courseSemester" class="custom-input w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Credit validation warning -->
                    <div id="creditWarning" class="hidden p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700">
                        <div class="flex">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-800 dark:text-yellow-200" id="creditWarningText"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" id="closeModalBtn" class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary px-5 py-2.5 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <i class="fas fa-save mr-2"></i>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // DOM elements
        const yearSelect = document.getElementById('yearSelect');
        const semesterSelect = document.getElementById('semesterSelect');
        const loadButton = document.getElementById('loadStructure');
        const addCourseBtn = document.getElementById('addCourseBtn');
        const courseTable = document.getElementById('courseTable');
        const courseTableBody = document.getElementById('courseTableBody');
        const placeholderMessage = document.getElementById('placeholderMessage');
        const totalHours = document.getElementById('totalHours');
        const totalCredits = document.getElementById('totalCredits');
        const creditIndicator = document.getElementById('creditIndicator');
        const creditStatusText = document.getElementById('creditStatusText');
        const saveAllBtn = document.getElementById('saveAllBtn');
        const addCourseModal = document.getElementById('addCourseModal');
        const addCourseForm = document.getElementById('addCourseForm');
        const closeModal = document.getElementById('closeModal');
        const closeModalBtn = document.getElementById('closeModalBtn');

        // Track changes - preserves all original values
        let originalData = {};
        let changes = {};

        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Enhanced credit validation with visual feedback
        function validateTotalCredits() {
            const totalCreditsValue = parseInt(totalCredits.textContent) || 0;
            const creditDisplay = document.getElementById('totalCredits');
            const saveButton = document.getElementById('saveAllBtn');
            
            // Remove existing validation classes
            creditDisplay.classList.remove('text-red-600', 'text-green-600', 'text-yellow-600');
            creditIndicator.classList.remove('bg-red-500', 'bg-green-500', 'bg-yellow-500', 'bg-gray-400');
            
            if (totalCreditsValue === 0) {
                creditIndicator.classList.add('bg-gray-400');
                creditStatusText.textContent = 'Select courses to validate';
                creditStatusText.className = 'text-gray-500';
                saveButton.disabled = true;
                return false;
            } else if (totalCreditsValue < 20) {
                creditDisplay.classList.add('text-red-600');
                creditIndicator.classList.add('bg-red-500');
                creditStatusText.textContent = `${20 - totalCreditsValue} more credits needed`;
                creditStatusText.className = 'text-red-600';
                saveButton.disabled = true;
                return false;
            } else if (totalCreditsValue > 25) {
                creditDisplay.classList.add('text-red-600');
                creditIndicator.classList.add('bg-red-500');
                creditStatusText.textContent = `${totalCreditsValue - 25} credits over limit`;
                creditStatusText.className = 'text-red-600';
                saveButton.disabled = true;
                return false;
            } else {
                creditDisplay.classList.add('text-green-600');
                creditIndicator.classList.add('bg-green-500');
                creditStatusText.textContent = 'Valid credit range';
                creditStatusText.className = 'text-green-600';
                saveButton.disabled = Object.keys(changes).length === 0;
                return true;
            }
        }

        // Enable/disable buttons based on selection
        function updateButtons() {
            const yearSelected = yearSelect.value !== '';
            const semesterSelected = semesterSelect.value !== '';
            const bothSelected = yearSelected && semesterSelected;
            loadButton.disabled = !bothSelected;
            addCourseBtn.disabled = !bothSelected;
        }

        yearSelect.addEventListener('change', updateButtons);
        semesterSelect.addEventListener('change', updateButtons);

        // Load course structure
        loadButton.addEventListener('click', function() {
            const year = yearSelect.value;
            const semester = semesterSelect.value;
            
            loadButton.disabled = true;
            loadButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=getCourseData&year=${year}&semester=${semester}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayCourseStructure(data.data, year, semester);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while loading course structure.', 'error');
            })
            .finally(() => {
                loadButton.disabled = false;
                loadButton.innerHTML = '<i class="fas fa-search mr-2"></i>Load Structure';
                updateButtons();
            });
        });

        // Display course structure with proper data tracking
        function displayCourseStructure(courses, year, semester) {
            courseTableBody.innerHTML = '';
            
            let totalHoursSum = 0;
            let totalCreditsSum = 0;
            
            // Clear and rebuild original data tracking
            originalData = {};
            changes = {};
            
            courses.forEach((course, index) => {
                // Store complete original data for each course
                originalData[course.id] = {
                    id: course.id,
                    l: course.l,
                    t: course.t,
                    p: course.p,
                    sl: course.sl,
                    c: course.c
                };
                
                const row = document.createElement('tr');
                row.className = `bg-white ${index < courses.length - 1 ? 'border-b' : ''} dark:bg-background-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors`;
                
                row.innerHTML = `
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">${course.code}</td>
                    <td class="px-6 py-4">${course.name}</td>
                    <td class="px-6 py-4 text-center">
                        <select class="custom-input w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                                data-field="l" data-id="${course.id}">
                            <option value="0" ${course.l === 0 ? 'selected' : ''}>0</option>
                            <option value="1" ${course.l === 1 ? 'selected' : ''}>1</option>
                            <option value="2" ${course.l === 2 ? 'selected' : ''}>2</option>
                            <option value="3" ${course.l === 3 ? 'selected' : ''}>3</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <select class="custom-input w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                                data-field="t" data-id="${course.id}">
                            <option value="0" ${course.t === 0 ? 'selected' : ''}>0</option>
                            <option value="2" ${course.t === 2 ? 'selected' : ''}>2</option>
                            <option value="4" ${course.t === 4 ? 'selected' : ''}>4</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <select class="custom-input w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                                data-field="p" data-id="${course.id}">
                            <option value="0" ${course.p === 0 ? 'selected' : ''}>0</option>
                            <option value="2" ${course.p === 2 ? 'selected' : ''}>2</option>
                            <option value="4" ${course.p === 4 ? 'selected' : ''}>4</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <select class="custom-input w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                                data-field="sl" data-id="${course.id}">
                            <option value="0" ${course.sl === 0 ? 'selected' : ''}>0</option>
                            <option value="2" ${course.sl === 2 ? 'selected' : ''}>2</option>
                            <option value="4" ${course.sl === 4 ? 'selected' : ''}>4</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <input type="number" min="0" value="${course.c}" 
                               class="custom-input w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:text-white"
                               data-field="c" data-id="${course.id}">
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button onclick="deleteCourse(${course.id})" 
                                class="px-3 py-1 text-sm text-white bg-danger rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all">
                            <i class="fas fa-trash-alt mr-1"></i>
                            Delete
                        </button>
                    </td>
                `;
                
                courseTableBody.appendChild(row);
                
                totalHoursSum += course.l + course.t + course.p + course.sl;
                totalCreditsSum += course.c;
            });
            
            totalHours.textContent = totalHoursSum;
            totalCredits.textContent = totalCreditsSum;
            
            courseTable.classList.remove('hidden');
            placeholderMessage.classList.add('hidden');
            
            // Add change listeners and validate
            addChangeListeners();
            validateTotalCredits();
        }

        // Enhanced change listeners with credit range validation
        function addChangeListeners() {
            const inputs = courseTableBody.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    const id = parseInt(this.dataset.id);
                    const field = this.dataset.field;
                    const value = parseInt(this.value) || 0;
                    
                    // Initialize changes object for this course if it doesn't exist
                    if (!changes[id]) {
                        changes[id] = { 
                            id: id,
                            l: originalData[id] ? originalData[id].l : 0,
                            t: originalData[id] ? originalData[id].t : 0,
                            p: originalData[id] ? originalData[id].p : 0,
                            sl: originalData[id] ? originalData[id].sl : 0,
                            c: originalData[id] ? originalData[id].c : 0
                        };
                    }
                    
                    // Store old value for potential reversion
                    const oldValue = changes[id][field];
                    changes[id][field] = value;
                    
                    // Handle credit calculations for L, T, P changes
                    if (field === 'l' || field === 't' || field === 'p') {
                        const row = this.closest('tr');
                        const L = parseInt(row.querySelector('[data-field="l"]').value) || 0;
                        const T = parseInt(row.querySelector('[data-field="t"]').value) || 0;
                        const P = parseInt(row.querySelector('[data-field="p"]').value) || 0;
                        let C = calculateCredits(L, T, P);
                        
                        // If individual course credits exceed 4, revert the current field
                        if (C > 4) {
                            const originalValue = originalData[id] ? originalData[id][field] : 0;
                            this.value = originalValue;
                            changes[id][field] = originalValue;
                            showNotification(`Credit value cannot exceed 4. Reverting ${field.toUpperCase()} to original value: ${originalValue}`, 'warning');
                            
                            // Recalculate with reverted value
                            const newL = parseInt(row.querySelector('[data-field="l"]').value) || 0;
                            const newT = parseInt(row.querySelector('[data-field="t"]').value) || 0;
                            const newP = parseInt(row.querySelector('[data-field="p"]').value) || 0;
                            C = calculateCredits(newL, newT, newP);
                        }
                        
                        // Update the Credit (C) field and track the change
                        row.querySelector('[data-field="c"]').value = C;
                        changes[id]['c'] = C;
                        
                        // Update changes for L, T, P with current values
                        changes[id]['l'] = parseInt(row.querySelector('[data-field="l"]').value) || 0;
                        changes[id]['t'] = parseInt(row.querySelector('[data-field="t"]').value) || 0;
                        changes[id]['p'] = parseInt(row.querySelector('[data-field="p"]').value) || 0;
                    }
                    
                    // Calculate new totals
                    calculateTotals();
                    
                    // Check if total credits are within range
                    const newTotalCredits = parseInt(totalCredits.textContent) || 0;
                    
                    // If total credits go outside 20-25 range, revert the change
                    if (newTotalCredits < 20 || newTotalCredits > 25) {
                        // Show appropriate warning message
                        let message = '';
                        if (newTotalCredits < 20) {
                            message = `This change would reduce total credits to ${newTotalCredits}, below the minimum requirement of 20. Change reverted.`;
                        } else {
                            message = `This change would increase total credits to ${newTotalCredits}, exceeding the maximum limit of 25. Change reverted.`;
                        }
                        
                        // Revert the change
                        changes[id][field] = oldValue;
                        this.value = oldValue;
                        
                        // If it was an L, T, P change, recalculate and revert credits
                        if (field === 'l' || field === 't' || field === 'p') {
                            const row = this.closest('tr');
                            const L = parseInt(row.querySelector('[data-field="l"]').value) || 0;
                            const T = parseInt(row.querySelector('[data-field="t"]').value) || 0;
                            const P = parseInt(row.querySelector('[data-field="p"]').value) || 0;
                            const C = calculateCredits(L, T, P);
                            
                            row.querySelector('[data-field="c"]').value = C;
                            changes[id]['c'] = C;
                            changes[id]['l'] = L;
                            changes[id]['t'] = T;
                            changes[id]['p'] = P;
                        }
                        
                        // Recalculate totals with reverted values
                        calculateTotals();
                        
                        showNotification(message, 'warning');
                        return;
                    }
                    
                    // Mark as changed if the change is valid
                    this.classList.add('bg-yellow-100', 'dark:bg-yellow-900');
                });
            });
        }

        // Credit calculation functions
        function calculateCredits(L, T, P) {
            return L + (T / 2) + (P / 2);
        }

        // Enhanced calculateTotals with validation
        function calculateTotals() {
            let totalHoursSum = 0;
            let totalCreditsSum = 0;
            
            const rows = courseTableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const l = parseInt(row.querySelector('[data-field="l"]').value) || 0;
                const t = parseInt(row.querySelector('[data-field="t"]').value) || 0;
                const p = parseInt(row.querySelector('[data-field="p"]').value) || 0;
                const sl = parseInt(row.querySelector('[data-field="sl"]').value) || 0;
                const c = parseInt(row.querySelector('[data-field="c"]').value) || 0;
                
                totalHoursSum += l + t + p + sl;
                totalCreditsSum += c;
            });
            
            totalHours.textContent = totalHoursSum;
            totalCredits.textContent = totalCreditsSum;
            
            // Validate total credits
            validateTotalCredits();
        }

        // Delete course function
        function deleteCourse(id) {
            if (!confirm('Are you sure you want to delete this course?')) {
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=deleteCourse&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from UI
                    const row = courseTableBody.querySelector(`[data-id="${id}"]`).closest('tr');
                    if (row) {
                        row.remove();
                        calculateTotals();
                    }
                    
                    // Remove from changes if it exists
                    delete changes[id];
                    delete originalData[id];
                    
                    showNotification('Course deleted successfully.', 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while deleting the course.', 'error');
            });
        }

        // Enhanced save all changes with final validation
        saveAllBtn.addEventListener('click', function() {
            if (Object.keys(changes).length === 0) {
                showNotification('No changes to save.', 'warning');
                return;
            }
            
            // Final validation before saving
            const finalTotalCredits = parseInt(totalCredits.textContent) || 0;
            if (finalTotalCredits < 20 || finalTotalCredits > 25) {
                showNotification(`Cannot save: Total credits (${finalTotalCredits}) must be between 20 and 25.`, 'error');
                return;
            }
            
            saveAllBtn.disabled = true;
            saveAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=saveAllChanges&changes=${JSON.stringify(Object.values(changes))}&year=${yearSelect.value}&semester=${semesterSelect.value}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('All changes saved successfully.', 'success');
                    changes = {};
                    // Remove highlight from changed fields
                    const changedInputs = courseTableBody.querySelectorAll('.bg-yellow-100, .dark\\:bg-yellow-900');
                    changedInputs.forEach(input => {
                        input.classList.remove('bg-yellow-100', 'dark:bg-yellow-900');
                    });
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while saving changes.', 'error');
            })
            .finally(() => {
                saveAllBtn.disabled = false;
                saveAllBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save All Changes';
                
                // Re-validate after save
                validateTotalCredits();
            });
        });

        // Modal functionality
        addCourseBtn.addEventListener('click', function() {
            // Set current year and semester in modal
            document.getElementById('courseYear').value = yearSelect.value;
            document.getElementById('courseSemester').value = semesterSelect.value;
            addCourseModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent body scroll
        });

        closeModal.addEventListener('click', closeModalFunction);
        closeModalBtn.addEventListener('click', closeModalFunction);

        function closeModalFunction() {
            addCourseModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Re-enable body scroll
            addCourseForm.reset();
            document.getElementById('creditWarning').classList.add('hidden');
        }

        // Close modal when clicking outside
        addCourseModal.addEventListener('click', function(event) {
            if (event.target === addCourseModal) {
                closeModalFunction();
            }
        });

        // Enhanced add course form with credit validation
        const addFormInputs = ['courseLecture', 'courseTutorial', 'coursePractical'];
        addFormInputs.forEach(inputId => {
            document.getElementById(inputId).addEventListener('change', function() {
                const L = parseInt(document.getElementById('courseLecture').value) || 0;
                const T = parseInt(document.getElementById('courseTutorial').value) || 0;
                const P = parseInt(document.getElementById('coursePractical').value) || 0;
                let C = calculateCredits(L, T, P);
                
                // Check current total credits before adding
                const currentTotalCredits = parseInt(totalCredits.textContent) || 0;
                const projectedTotal = currentTotalCredits + C;
                
                const creditWarning = document.getElementById('creditWarning');
                const creditWarningText = document.getElementById('creditWarningText');
                
                // If individual course Credit (C) exceeds 4, reset values and show alert
                if (C > 4) {
                    showNotification("Individual course credit value cannot exceed 4.", 'warning');
                    document.getElementById('courseLecture').value = 0;
                    document.getElementById('courseTutorial').value = 0;
                    document.getElementById('coursePractical').value = 0;
                    C = 0;
                    creditWarning.classList.add('hidden');
                }
                // Check if adding this course would exceed total limit
                else if (projectedTotal > 25) {
                    creditWarningText.textContent = `Adding this course (${C} credits) would exceed the maximum total of 25. Current total: ${currentTotalCredits}, Projected: ${projectedTotal}`;
                    creditWarning.classList.remove('hidden');
                    creditWarning.classList.remove('bg-green-50', 'border-green-200');
                    creditWarning.classList.add('bg-yellow-50', 'border-yellow-200');
                    creditWarning.querySelector('svg').classList.remove('text-green-400');
                    creditWarning.querySelector('svg').classList.add('text-yellow-400');
                    creditWarningText.classList.remove('text-green-800');
                    creditWarningText.classList.add('text-yellow-800');
                } else if (currentTotalCredits > 0 && projectedTotal >= 20 && projectedTotal <= 25) {
                    creditWarningText.textContent = `This course will bring total credits to ${projectedTotal} (within valid range)`;
                    creditWarning.classList.remove('hidden');
                    creditWarning.classList.remove('bg-yellow-50', 'border-yellow-200');
                    creditWarning.classList.add('bg-green-50', 'border-green-200');
                    creditWarning.querySelector('svg').classList.remove('text-yellow-400');
                    creditWarning.querySelector('svg').classList.add('text-green-400');
                    creditWarningText.classList.remove('text-yellow-800');
                    creditWarningText.classList.add('text-green-800');
                } else {
                    creditWarning.classList.add('hidden');
                    // Reset warning colors
                    creditWarning.classList.remove('bg-green-50', 'border-green-200');
                    creditWarning.classList.add('bg-yellow-50', 'border-yellow-200');
                    creditWarning.querySelector('svg').classList.remove('text-green-400');
                    creditWarning.querySelector('svg').classList.add('text-yellow-400');
                    creditWarningText.classList.remove('text-green-800');
                    creditWarningText.classList.add('text-yellow-800');
                }
                
                // Update the Credit field
                document.getElementById('courseCredits').value = C;
            });
        });

        // Enhanced Add Course form submission with credit validation
        addCourseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get current total credits
            const currentTotalCredits = parseInt(totalCredits.textContent) || 0;
            const newCourseCredits = parseInt(document.getElementById('courseCredits').value) || 0;
            const projectedTotal = currentTotalCredits + newCourseCredits;
            
            // Check if adding this course would exceed the limit
            if (projectedTotal > 25) {
                showNotification(`Adding this course (${newCourseCredits} credits) would exceed the maximum total credits limit of 25. Current total: ${currentTotalCredits}, Projected total: ${projectedTotal}`, 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'addCourse');
            formData.append('code', document.getElementById('courseCode').value);
            formData.append('title', document.getElementById('courseTitle').value);
            formData.append('l', document.getElementById('courseLecture').value);
            formData.append('t', document.getElementById('courseTutorial').value);
            formData.append('p', document.getElementById('coursePractical').value);
            formData.append('sl', document.getElementById('courseSelfLearning').value);
            formData.append('c', document.getElementById('courseCredits').value);
            formData.append('year', document.getElementById('courseYear').value);
            formData.append('semester', document.getElementById('courseSemester').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Course added successfully.', 'success');
                    closeModalFunction();
                    // Reload the table
                    loadButton.click();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while adding the course.', 'error');
            });
        });
        
        // Custom notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg animate-fade-in ${
                type === 'success' ? 'bg-success' : 
                type === 'warning' ? 'bg-warning' : 'bg-danger'
            } text-white`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'
                    } mr-2"></i>
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
    </script>
</body>
</html>