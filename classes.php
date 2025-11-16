<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['branch'])) {
    header("Location: admin.php");
    exit();
}

// Get admin's branch from session
 $admin_branch = $_SESSION['branch'];

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $class_id = $_POST['class_id'];
        $course_id = $_POST['course_id'];
        $teacher_id = $_POST['teacher_id'];
        $room = $_POST['room'];
        $day = $_POST['day'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $section = $_POST['section'];
        $year = $_POST['year'];
        $semester = $_POST['semester'];
        $academic_year = $_POST['academic_year'];
        
        // Get course details
        $course_sql = "SELECT title, code FROM course WHERE id = :course_id";
        $course_stmt = $pdo->prepare($course_sql);
        $course_stmt->execute([':course_id' => $course_id]);
        $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify that the teacher belongs to admin's branch
        $check_sql = "SELECT department FROM credentials WHERE employee_id = :teacher_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':teacher_id' => $teacher_id]);
        $teacher = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher || $teacher['department'] !== $admin_branch) {
            $_SESSION['message'] = 'You can only assign classes to teachers in your department: ' . $admin_branch;
            $_SESSION['messageType'] = 'danger';
        } else {
            if ($action === 'add') {
                // Check if class already exists
                $check_sql = "SELECT class_id FROM classes WHERE class_id = :class_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':class_id' => $class_id]);
                $existing_class = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_class) {
                    $_SESSION['message'] = 'Class with this ID already exists!';
                    $_SESSION['messageType'] = 'danger';
                } else {
                    $sql = "INSERT INTO classes (class_id, course_name, course_code, course_id, teacher_id, room, day, start_time, end_time, section, year, semester, academic_year, department) 
                            VALUES (:class_id, :course_name, :course_code, :course_id, :teacher_id, :room, :day, :start_time, :end_time, :section, :year, :semester, :academic_year, :department)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':class_id' => $class_id,
                        ':course_name' => $course['title'],
                        ':course_code' => $course['code'],
                        ':course_id' => $course_id,
                        ':teacher_id' => $teacher_id,
                        ':room' => $room,
                        ':day' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':section' => $section,
                        ':year' => $year,
                        ':semester' => $semester,
                        ':academic_year' => $academic_year,
                        ':department' => $admin_branch
                    ]);
                    $_SESSION['message'] = 'Class added successfully!';
                    $_SESSION['messageType'] = 'success';
                }
            } else {
                // Verify the class belongs to admin's department before editing
                $check_sql = "SELECT department FROM classes WHERE class_id = :class_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':class_id' => $class_id]);
                $existing_class = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_class && $existing_class['department'] !== $admin_branch) {
                    $_SESSION['message'] = 'You can only edit classes in your department!';
                    $_SESSION['messageType'] = 'danger';
                } else {
                    $sql = "UPDATE classes SET 
                            course_name = :course_name, 
                            course_code = :course_code,
                            course_id = :course_id,
                            teacher_id = :teacher_id, 
                            room = :room, 
                            day = :day, 
                            start_time = :start_time, 
                            end_time = :end_time,
                            section = :section,
                            year = :year,
                            semester = :semester, 
                            academic_year = :academic_year
                            WHERE class_id = :class_id";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':class_id' => $class_id,
                        ':course_name' => $course['title'],
                        ':course_code' => $course['code'],
                        ':course_id' => $course_id,
                        ':teacher_id' => $teacher_id,
                        ':room' => $room,
                        ':day' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':section' => $section,
                        ':year' => $year,
                        ':semester' => $semester,
                        ':academic_year' => $academic_year
                    ]);
                    
                    $_SESSION['message'] = 'Class updated successfully!';
                    $_SESSION['messageType'] = 'success';
                }
            }
        }
    } elseif ($action === 'delete') {
        $class_id = $_POST['class_id'];
        
        // Verify the class belongs to admin's department before deleting
        $check_sql = "SELECT department FROM classes WHERE class_id = :class_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':class_id' => $class_id]);
        $class = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($class && $class['department'] !== $admin_branch) {
            $_SESSION['message'] = 'You can only delete classes in your department!';
            $_SESSION['messageType'] = 'danger';
        } else {
            $sql = "DELETE FROM classes WHERE class_id = :class_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':class_id' => $class_id]);
            $_SESSION['message'] = 'Class deleted successfully!';
            $_SESSION['messageType'] = 'danger';
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: classes.php");
    exit();
}

// Get message from session if exists
 $message = $_SESSION['message'] ?? '';
 $messageType = $_SESSION['messageType'] ?? '';

// Clear message from session
unset($_SESSION['message']);
unset($_SESSION['messageType']);

// Fetch teachers from admin's branch for dropdown
 $teachers_sql = "SELECT employee_id, emp_name FROM credentials WHERE department = :admin_branch ORDER BY emp_name";
 $teachers_stmt = $pdo->prepare($teachers_sql);
 $teachers_stmt->execute([':admin_branch' => $admin_branch]);
 $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses from database
 $courses_sql = "SELECT id, code, title, year FROM course ORDER BY year, code";
 $courses_stmt = $pdo->prepare($courses_sql);
 $courses_stmt->execute();
 $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch classes with filters - ONLY from admin's branch
 $search = $_GET['search'] ?? '';
 $day_filter = $_GET['day'] ?? '';
 $semester_filter = $_GET['semester'] ?? '';
 $year_filter = $_GET['year'] ?? '';

 $sql = "SELECT c.*, t.emp_name as teacher_name FROM classes c 
        LEFT JOIN credentials t ON c.teacher_id = t.employee_id 
        WHERE c.department = :admin_branch";
 $params = [':admin_branch' => $admin_branch];

if ($search) {
    $sql .= " AND (c.course_name LIKE :search OR c.course_code LIKE :search OR c.room LIKE :search OR c.section LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($day_filter) {
    $sql .= " AND c.day = :day";
    $params[':day'] = $day_filter;
}

if ($semester_filter) {
    $sql .= " AND c.semester = :semester";
    $params[':semester'] = $semester_filter;
}

if ($year_filter) {
    $sql .= " AND c.year = :year";
    $params[':year'] = $year_filter;
}

 $sql .= " ORDER BY c.year, c.day, c.start_time";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Class Management</title>
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
        .time-slot {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .year-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
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
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary/10 text-primary border-l-3 border-primary transition-all" href="classes.php">
                    <i class="fas fa-school text-lg"></i>
                    <span class="font-medium">Classes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 transition-all" href="meetings.php">
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
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Class Management</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage classes in <span class="font-semibold text-primary"><?php echo htmlspecialchars($admin_branch); ?></span> department.</p>
                    </div>
                    <button onclick="openModal()" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i>
                        Add New Class
                    </button>
                </header>

                <?php if ($message): ?>
                <div class="mb-4 px-6 py-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-lg mb-8">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by course, code, room, or section..." class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Day</label>
                            <select name="day" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2">
                                <option value="">All Days</option>
                                <option value="Monday" <?php echo $day_filter === 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                <option value="Tuesday" <?php echo $day_filter === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                <option value="Wednesday" <?php echo $day_filter === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                <option value="Thursday" <?php echo $day_filter === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                <option value="Friday" <?php echo $day_filter === 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                <option value="Saturday" <?php echo $day_filter === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                <option value="Sunday" <?php echo $day_filter === 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                            <select name="year" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2">
                                <option value="">All Years</option>
                                <option value="1" <?php echo $year_filter === '1' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo $year_filter === '2' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo $year_filter === '3' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo $year_filter === '4' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Semester</label>
                            <select name="semester" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2">
                                <option value="">All Semesters</option>
                                <option value="1" <?php echo $semester_filter === '1' ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo $semester_filter === '2' ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2 md:col-span-4">
                            <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary-dark transition-all font-medium">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <a href="classes.php" class="px-4 py-2 rounded-lg border-2 border-primary text-primary hover:bg-primary hover:text-white transition-all font-medium">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Classes Table -->
                <section>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">All Classes</h2>
                        <span class="text-gray-600 dark:text-gray-400">Total: <span class="font-bold text-primary"><?php echo count($classes); ?></span> Classes</span>
                    </div>
                    <div class="overflow-x-auto bg-card-light dark:bg-card-dark rounded-xl shadow-lg">
                        <table class="w-full text-left">
                            <thead class="border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Class ID</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Course</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Teacher</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Section</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Room</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Time</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-primary/5 transition-all">
                                    <td class="p-4 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($class['class_id']); ?></td>
                                    <td class="p-4">
                                        <div>
                                            <p class="text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($class['course_name']); ?></p>
                                            <p class="text-gray-500 dark:text-gray-400 text-sm"><?php echo htmlspecialchars($class['course_code']); ?></p>
                                            <div class="year-badge mt-1 inline-block">
                                                Year <?php echo htmlspecialchars($class['year']); ?>, Sem <?php echo htmlspecialchars($class['semester']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-800 dark:text-white"><?php echo htmlspecialchars($class['teacher_name']); ?></td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars($class['section']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($class['room']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <p class="text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($class['day']); ?></p>
                                            <div class="time-slot mt-1">
                                                <?php echo htmlspecialchars($class['start_time']); ?> - <?php echo htmlspecialchars($class['end_time']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <button onclick='editClass(<?php echo json_encode($class); ?>)' class="text-primary hover:text-primary-dark transition-colors mr-3" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                            <button type="submit" class="text-danger hover:text-red-700 transition-colors" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Add/Edit Class Modal -->
    <div id="class-modal" class="modal">
        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-lg p-8 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white" id="modal-title">Add New Class</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" id="form-action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Class ID *</label>
                        <input type="text" name="class_id" id="class-id" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Course *</label>
                        <select name="course_id" id="course-id" required class="block w-full rounded-lg border-gray-300 px-4 py-2" onchange="updateCourseInfo()">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" data-code="<?php echo htmlspecialchars($course['code']); ?>" data-title="<?php echo htmlspecialchars($course['title']); ?>" data-year="<?php echo htmlspecialchars($course['year']); ?>">
                                <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['title']); ?> (Year <?php echo htmlspecialchars($course['year']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Teacher *</label>
                        <select name="teacher_id" id="teacher-id" required class="block w-full rounded-lg border-gray-300 px-4 py-2">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['employee_id']; ?>"><?php echo htmlspecialchars($teacher['emp_name']); ?> (<?php echo htmlspecialchars($teacher['employee_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Section *</label>
                        <input type="text" name="section" id="section" placeholder="e.g., A, B, C" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Room *</label>
                        <input type="text" name="room" id="room" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Day *</label>
                        <select name="day" id="day" required class="block w-full rounded-lg border-gray-300 px-4 py-2">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Time *</label>
                        <input type="time" name="start_time" id="start-time" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">End Time *</label>
                        <input type="time" name="end_time" id="end-time" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year *</label>
                        <select name="year" id="year" required class="block w-full rounded-lg border-gray-300 px-4 py-2">
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Semester *</label>
                        <select name="semester" id="semester" required class="block w-full rounded-lg border-gray-300 px-4 py-2">
                            <option value="">Select Semester</option>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Academic Year *</label>
                        <input type="text" name="academic_year" id="academic-year" placeholder="e.g., 2023-2024" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="submit" class="btn-primary flex-1 px-6 py-3 rounded-lg text-white font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Class
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-100 transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('class-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Add New Class';
            document.getElementById('form-action').value = 'add';
            document.getElementById('class-id').removeAttribute('readonly');
        }

        function closeModal() {
            document.getElementById('class-modal').classList.remove('active');
            document.querySelector('form').reset();
        }

        function editClass(classData) {
            document.getElementById('class-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Edit Class';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('class-id').value = classData.class_id;
            document.getElementById('class-id').setAttribute('readonly', 'readonly');
            document.getElementById('course-id').value = classData.course_id;
            document.getElementById('teacher-id').value = classData.teacher_id;
            document.getElementById('section').value = classData.section;
            document.getElementById('room').value = classData.room;
            document.getElementById('day').value = classData.day;
            document.getElementById('start-time').value = classData.start_time;
            document.getElementById('end-time').value = classData.end_time;
            document.getElementById('year').value = classData.year;
            document.getElementById('semester').value = classData.semester;
            document.getElementById('academic-year').value = classData.academic_year;
        }

        function updateCourseInfo() {
            const courseSelect = document.getElementById('course-id');
            const selectedOption = courseSelect.options[courseSelect.selectedIndex];
            
            if (selectedOption.value) {
                const year = selectedOption.getAttribute('data-year');
                document.getElementById('year').value = year;
            }
        }
    </script>
</body>
</html>