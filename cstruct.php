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

// Function to get course data from database
function getCourseData($pdo, $year, $semester) {
    try {
        // Debug: Check if table exists and has data
        $debug_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM course");
        $debug_stmt->execute();
        $total_courses = $debug_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Debug: Check what years and semesters exist
        $years_stmt = $pdo->prepare("SELECT DISTINCT year, semester FROM course ORDER BY year, semester");
        $years_stmt->execute();
        $available = $years_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Debug: Total courses in database: " . $total_courses);
        error_log("Debug: Available year/semester combinations: " . json_encode($available));
        error_log("Debug: Searching for year=$year, semester=$semester");
        
        $stmt = $pdo->prepare("SELECT code, title, L, T, P, SL, C FROM course WHERE year = ? AND semester = ? ORDER BY id");
        $stmt->execute([$year, $semester]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Debug: Found " . count($courses) . " courses for year $year, semester $semester");
        
        // Convert to the expected format
        $formattedCourses = [];
        foreach ($courses as $course) {
            $formattedCourses[] = [
                'code' => $course['code'],
                'name' => $course['title'],
                'l' => (int)$course['L'],
                't' => (int)$course['T'],
                'p' => (int)$course['P'],
                'sl' => (int)$course['SL'],
                'c' => (int)$course['C']
            ];
        }
        
        return $formattedCourses;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Handle AJAX request for course data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'getCourseData') {
    header('Content-Type: application/json');
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;
    
    error_log("Debug: Received year=$year, semester=$semester");
    
    if ($year > 0 && $semester > 0) {
        $courses = getCourseData($pdo, $year, $semester);
        if (!empty($courses)) {
            echo json_encode(['success' => true, 'data' => $courses]);
        } else {
            // Check if the table exists and has data
            try {
                $check_stmt = $pdo->prepare("SELECT DISTINCT CONCAT('Year ', year, ' Semester ', semester) as available FROM course ORDER BY year, semester");
                $check_stmt->execute();
                $available = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
                $available_text = !empty($available) ? implode(', ', $available) : 'None';
                
                echo json_encode([
                    'success' => false, 
                    'message' => "No courses found for Year $year, Semester $semester. Available combinations: $available_text"
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid year or semester.']);
    }
    exit();
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

// Debug: Check database connection and data (remove this in production)
try {
    $test_stmt = $pdo->prepare("SHOW TABLES LIKE 'course'");
    $test_stmt->execute();
    $table_exists = $test_stmt->rowCount() > 0;
    
    if ($table_exists) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM course");
        $count_stmt->execute();
        $total_courses = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $sample_stmt = $pdo->prepare("SELECT year, semester, COUNT(*) as count FROM course GROUP BY year, semester ORDER BY year, semester LIMIT 5");
        $sample_stmt->execute();
        $sample_data = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Uncomment the next line to see debug info (remove in production)
        // echo "<!-- Debug: Course table exists. Total courses: $total_courses. Sample: " . json_encode($sample_data) . " -->";
    } else {
        // echo "<!-- Debug: Course table does not exist -->";
    }
} catch (PDOException $e) {
    // echo "<!-- Debug error: " . $e->getMessage() . " -->";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
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
    <title>Course Structure - Teacher Dashboard</title>
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
                <a class="sidebar-link flex items-center px-4 py-3 text-white bg-primary rounded-lg shadow-md" href="cstruct.php">
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
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Course Structure</h1>
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
                    
                    <div class="flex items-end">
                        <button id="loadStructure" class="btn-primary px-6 py-3 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-search mr-2"></i>
                            Load Structure
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
                                </tr>
                            </thead>    
                            <tbody id="courseTableBody">
                                <!-- Course rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="mt-6 flex justify-between items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex gap-8">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Hours</span>
                                <span id="totalHours" class="ml-2 text-lg font-bold text-gray-900 dark:text-white">0</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Credits</span>
                                <span id="totalCredits" class="ml-2 text-lg font-bold text-gray-900 dark:text-white">0</span>
                            </div>
                        </div>
                        
                        <button id="downloadBtn" class="btn-primary flex items-center px-4 py-2 text-sm font-medium text-white rounded-lg">
                            <i class="fas fa-file-pdf mr-2"></i>
                            Download PDF
                        </button>
                    </div>
                </div>

                <!-- Placeholder message -->
                <div id="placeholderMessage" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No course structure selected</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select a year and semester to view the course structure.</p>
                </div>
            </div>
        </main>
    </div>

    <script>
        // DOM elements
        const yearSelect = document.getElementById('yearSelect');
        const semesterSelect = document.getElementById('semesterSelect');
        const loadButton = document.getElementById('loadStructure');
        const courseTable = document.getElementById('courseTable');
        const courseTableBody = document.getElementById('courseTableBody');
        const placeholderMessage = document.getElementById('placeholderMessage');
        const totalHours = document.getElementById('totalHours');
        const totalCredits = document.getElementById('totalCredits');
        const downloadBtn = document.getElementById('downloadBtn');

        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Enable/disable load button based on selection
        function updateLoadButton() {
            const yearSelected = yearSelect.value !== '';
            const semesterSelected = semesterSelect.value !== '';
            loadButton.disabled = !(yearSelected && semesterSelected);
        }

        yearSelect.addEventListener('change', updateLoadButton);
        semesterSelect.addEventListener('change', updateLoadButton);

        // Load course structure
        loadButton.addEventListener('click', function() {
            const year = yearSelect.value;
            const semester = semesterSelect.value;
            
            // Show loading state
            loadButton.disabled = true;
            loadButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            
            // Make AJAX request to get course data
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
                updateLoadButton();
            });
        });

        function displayCourseStructure(courses, year, semester) {
            // Clear existing table body
            courseTableBody.innerHTML = '';
            
            let totalHoursSum = 0;
            let totalCreditsSum = 0;
            
            // Add course rows
            courses.forEach((course, index) => {
                const row = document.createElement('tr');
                row.className = `bg-white ${index < courses.length - 1 ? 'border-b' : ''} dark:bg-background-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors`;
                
                row.innerHTML = `
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">${course.code}</td>
                    <td class="px-6 py-4">${course.name}</td>
                    <td class="px-6 py-4 text-center">${course.l}</td>
                    <td class="px-6 py-4 text-center">${course.t}</td>
                    <td class="px-6 py-4 text-center">${course.p}</td>
                    <td class="px-6 py-4 text-center">${course.sl}</td>
                    <td class="px-6 py-4 text-center font-medium">${course.c}</td>
                `;
                
                courseTableBody.appendChild(row);
                
                // Calculate totals
                totalHoursSum += course.l + course.t + course.p + course.sl;
                totalCreditsSum += course.c;
            });
            
            // Update totals
            totalHours.textContent = totalHoursSum;
            totalCredits.textContent = totalCreditsSum;
            
            // Show table and hide placeholder
            courseTable.classList.remove('hidden');
            placeholderMessage.classList.add('hidden');
        }

        // Download functionality - Generate PDF
        downloadBtn.addEventListener('click', function() {
            const year = yearSelect.value;
            const semester = semesterSelect.value;
            
            if (!year || !semester) {
                showNotification('Please select year and semester first.', 'error');
                return;
            }
            
            // Show loading state
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating PDF...';
            
            // Load logo image and generate PDF
            const logoUrl = 'https://48237376.fs1.hubspotusercontent-na1.net/hubfs/48237376/manabuki/Universities%20Logos/Vignan%20Logo.png';
            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            img.onload = function() {
                generatePDF(img, year, semester);
            };
            
            img.onerror = function() {
                // If logo fails to load, generate PDF without logo
                console.warn('Logo failed to load, generating PDF without logo');
                generatePDF(null, year, semester);
            };
            
            img.src = logoUrl;
        });
        
        function generatePDF(logoImg, year, semester) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            let yPosition = 20;
            
            // Add logo if available
            if (logoImg) {
                try {
                    // Since this is now a PNG image, we can add it directly
                    const logoWidth = 120;
                    const logoHeight = 40;
                    const logoX = (doc.internal.pageSize.width - logoWidth) / 2;
                    
                    doc.addImage(logoImg, 'PNG', logoX, yPosition, logoWidth, logoHeight);
                    yPosition += logoHeight + 15;
                } catch (e) {
                    console.warn('Error adding logo:', e);
                    // Add text header instead
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'bold');
                    doc.text('VIGNAN UNIVERSITY', doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
                    yPosition += 20;
                }
            } else {
                // Add text header if no logo
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('VIGNAN UNIVERSITY', doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
                yPosition += 20;
            }
            
            // Add title
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text(`Course Structure - Year ${year}, Semester ${semester}`, doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
            yPosition += 15;
            
            // Add date
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            const currentDate = new Date().toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            doc.text(`Generated on: ${currentDate}`, doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
            yPosition += 20;
            
            // Prepare table data
            const tableData = [];
            const rows = courseTableBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    tableData.push(rowData);
                }
            });
            
            // Add table
            doc.autoTable({
                head: [['Course Code', 'Course Name', 'L', 'T', 'P', 'SL', 'C']],
                body: tableData,
                startY: yPosition,
                styles: {
                    fontSize: 9,
                    cellPadding: 3,
                },
                headStyles: {
                    fillColor: [79, 70, 229], // Primary color
                    textColor: 255,
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [249, 250, 251]
                },
                columnStyles: {
                    0: { cellWidth: 25 }, // Course Code
                    1: { cellWidth: 80 }, // Course Name
                    2: { cellWidth: 15, halign: 'center' }, // L
                    3: { cellWidth: 15, halign: 'center' }, // T
                    4: { cellWidth: 15, halign: 'center' }, // P
                    5: { cellWidth: 15, halign: 'center' }, // SL
                    6: { cellWidth: 15, halign: 'center' }  // C
                },
                margin: { left: 15, right: 15 }
            });
            
            // Add summary
            const finalY = doc.lastAutoTable.finalY + 15;
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            
            // Summary box
            doc.setDrawColor(200);
            doc.setFillColor(249, 250, 251);
            doc.rect(15, finalY, doc.internal.pageSize.width - 30, 25, 'FD');
            
            doc.text(`Total Hours: ${totalHours.textContent}`, 25, finalY + 10);
            doc.text(`Total Credits: ${totalCredits.textContent}`, 25, finalY + 20);
            
            // Add footer
            doc.setFontSize(8);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(128);
            doc.text('This document was generated automatically from the Course Structure System.', 
                    doc.internal.pageSize.width / 2, 
                    doc.internal.pageSize.height - 15, 
                    { align: 'center' });
            
            // Save the PDF
            doc.save(`Course_Structure_Year_${year}_Semester_${semester}.pdf`);
            
            // Reset button state
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="fas fa-file-pdf mr-2"></i>Download PDF';
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
    </script>
</body>
</html>