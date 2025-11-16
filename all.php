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
        $stmt = $pdo->prepare("SELECT code, title, L, T, P, SL, C FROM course WHERE year = ? AND semester = ? ORDER BY id");
        $stmt->execute([$year, $semester]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
    
    if ($year > 0 && $semester > 0) {
        $courses = getCourseData($pdo, $year, $semester);
        if (!empty($courses)) {
            echo json_encode(['success' => true, 'data' => $courses]);
        } else {
            echo json_encode(['success' => false, 'message' => "No courses found for Year $year, Semester $semester"]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid year or semester.']);
    }
    exit();
}

// Get all course structures
$allStructures = [];
for ($year = 1; $year <= 4; $year++) {
    for ($semester = 1; $semester <= 2; $semester++) {
        $courses = getCourseData($pdo, $year, $semester);
        $allStructures[$year][$semester] = $courses;
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
    <title>All Structures - Teacher Dashboard</title>
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
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="cstruct.php">
                    <i class="fas fa-sitemap mr-3"></i>
                    Course Structure
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-all" href="cupdate.php">
                    <i class="fas fa-edit mr-3"></i>
                    Course Update
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 text-white bg-primary rounded-lg shadow-md" href="all.php">
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
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">All Course Structures</h1>
                <div class="flex items-center gap-4">
                    <button id="downloadAllBtn" class="btn-primary flex items-center px-6 py-3 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Download All Structures
                    </button>
                    <div class="text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <span id="currentDate"></span>
                    </div>
                </div>
            </div>

            <!-- Course Structure Tables -->
            <div class="space-y-8">
                <?php for ($year = 1; $year <= 4; $year++): ?>
                    <?php for ($semester = 1; $semester <= 2; $semester++): ?>
                        <div class="bg-white dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up" style="animation-delay: <?php echo (($year-1)*2 + $semester) * 0.1; ?>s">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                                    <i class="fas fa-graduation-cap mr-2 text-primary"></i>
                                    Year <?php echo $year; ?> - Semester <?php echo $semester; ?>
                                </h2>
                                <button class="download-single-btn flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all"
                                        data-year="<?php echo $year; ?>" data-semester="<?php echo $semester; ?>">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    Download PDF
                                </button>
                            </div>

                            <?php if (!empty($allStructures[$year][$semester])): ?>
                                <div class="overflow-x-auto">
                                    <table class="custom-table w-full text-sm text-left" id="table-<?php echo $year; ?>-<?php echo $semester; ?>">
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
                                        <tbody>
                                            <?php 
                                            $totalHours = 0;
                                            $totalCredits = 0;
                                            foreach ($allStructures[$year][$semester] as $index => $course): 
                                                $totalHours += $course['l'] + $course['t'] + $course['p'] + $course['sl'];
                                                $totalCredits += $course['c'];
                                            ?>
                                                <tr class="bg-white <?php echo ($index < count($allStructures[$year][$semester]) - 1) ? 'border-b' : ''; ?> dark:bg-card-dark dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($course['code']); ?></td>
                                                    <td class="px-6 py-4 text-gray-900 dark:text-white"><?php echo htmlspecialchars($course['name']); ?></td>
                                                    <td class="px-6 py-4 text-center text-gray-900 dark:text-white"><?php echo $course['l']; ?></td>
                                                    <td class="px-6 py-4 text-center text-gray-900 dark:text-white"><?php echo $course['t']; ?></td>
                                                    <td class="px-6 py-4 text-center text-gray-900 dark:text-white"><?php echo $course['p']; ?></td>
                                                    <td class="px-6 py-4 text-center text-gray-900 dark:text-white"><?php echo $course['sl']; ?></td>
                                                    <td class="px-6 py-4 text-center font-medium text-gray-900 dark:text-white"><?php echo $course['c']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary Section -->
                                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex gap-8">
                                        <div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Hours</span>
                                            <span class="ml-2 text-lg font-bold text-gray-900 dark:text-white"><?php echo $totalHours; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Credits</span>
                                            <span class="ml-2 text-lg font-bold text-gray-900 dark:text-white"><?php echo $totalCredits; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No courses found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No courses are available for this semester.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </main>
    </div>

    <script>
        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Download single structure
        document.querySelectorAll('.download-single-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const year = this.dataset.year;
                const semester = this.dataset.semester;
                
                // Show loading state
                const originalContent = this.innerHTML;
                this.disabled = true;
                this.innerHTML = `
                    <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Generating...
                `;
                
                // Load logo and generate PDF
                const logoUrl = 'https://48237376.fs1.hubspotusercontent-na1.net/hubfs/48237376/manabuki/Universities%20Logos/Vignan%20Logo.png';
                const img = new Image();
                img.crossOrigin = 'anonymous';
                
                const self = this;
                img.onload = function() {
                    generateSinglePDF(img, year, semester);
                    self.disabled = false;
                    self.innerHTML = originalContent;
                };
                
                img.onerror = function() {
                    generateSinglePDF(null, year, semester);
                    self.disabled = false;
                    self.innerHTML = originalContent;
                };
                
                img.src = logoUrl;
            });
        });

        // Download all structures
        document.getElementById('downloadAllBtn').addEventListener('click', function() {
            // Show loading state
            const originalContent = this.innerHTML;
            this.disabled = true;
            this.innerHTML = `
                <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Generating All PDFs...
            `;
            
            // Load logo and generate all PDFs
            const logoUrl = 'https://48237376.fs1.hubspotusercontent-na1.net/hubfs/48237376/manabuki/Universities%20Logos/Vignan%20Logo.png';
            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            const self = this;
            img.onload = function() {
                generateAllPDF(img);
                self.disabled = false;
                self.innerHTML = originalContent;
            };
            
            img.onerror = function() {
                generateAllPDF(null);
                self.disabled = false;
                self.innerHTML = originalContent;
            };
            
            img.src = logoUrl;
        });

        function generateSinglePDF(logoImg, year, semester) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            let yPosition = 20;
            
            // Add logo if available
            if (logoImg) {
                try {
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
            
            // Get table data
            const table = document.getElementById(`table-${year}-${semester}`);
            const tableData = [];
            const rows = table.querySelectorAll('tbody tr');
            
            let totalHours = 0;
            let totalCredits = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    tableData.push(rowData);
                    
                    // Calculate totals
                    const l = parseInt(rowData[2]) || 0;
                    const t = parseInt(rowData[3]) || 0;
                    const p = parseInt(rowData[4]) || 0;
                    const sl = parseInt(rowData[5]) || 0;
                    const c = parseInt(rowData[6]) || 0;
                    
                    totalHours += l + t + p + sl;
                    totalCredits += c;
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
            
            doc.text(`Total Hours: ${totalHours}`, 25, finalY + 10);
            doc.text(`Total Credits: ${totalCredits}`, 25, finalY + 20);
            
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
            
            // Show success notification
            showNotification(`PDF for Year ${year}, Semester ${semester} downloaded successfully.`, 'success');
        }

        function generateAllPDF(logoImg) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            let isFirstPage = true;
            
            for (let year = 1; year <= 4; year++) {
                for (let semester = 1; semester <= 2; semester++) {
                    const table = document.getElementById(`table-${year}-${semester}`);
                    if (!table) continue;
                    
                    // Add new page for each table (except the first one)
                    if (!isFirstPage) {
                        doc.addPage();
                    }
                    isFirstPage = false;
                    
                    let yPosition = 20;
                    
                    // Add logo if available
                    if (logoImg) {
                        try {
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
                    
                    // Get table data
                    const tableData = [];
                    const rows = table.querySelectorAll('tbody tr');
                    
                    let totalHours = 0;
                    let totalCredits = 0;
                    
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length > 0) {
                            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                            tableData.push(rowData);
                            
                            // Calculate totals
                            const l = parseInt(rowData[2]) || 0;
                            const t = parseInt(rowData[3]) || 0;
                            const p = parseInt(rowData[4]) || 0;
                            const sl = parseInt(rowData[5]) || 0;
                            const c = parseInt(rowData[6]) || 0;
                            
                            totalHours += l + t + p + sl;
                            totalCredits += c;
                        }
                    });
                    
                    // Add table if there's data
                    if (tableData.length > 0) {
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
                        
                        doc.text(`Total Hours: ${totalHours}`, 25, finalY + 10);
                        doc.text(`Total Credits: ${totalCredits}`, 25, finalY + 20);
                    } else {
                        // Add "No courses found" message
                        doc.setFontSize(12);
                        doc.setFont(undefined, 'normal');
                        doc.text('No courses found for this semester.', doc.internal.pageSize.width / 2, yPosition + 50, { align: 'center' });
                    }
                    
                    // Add footer
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(128);
                    doc.text('This document was generated automatically from the Course Structure System.', 
                            doc.internal.pageSize.width / 2, 
                            doc.internal.pageSize.height - 15, 
                            { align: 'center' });
                }
            }
            
            // Save the PDF
            doc.save('All_Course_Structures.pdf');
            
            // Show success notification
            showNotification('All course structures downloaded successfully.', 'success');
        }
        
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