<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If not logged in, redirect to login page
    header('Location: alogin.php');
    exit;
}

// Database connection
 $servername = "localhost";
 $username = "root";
 $password = "";
 $dbname = "credit";

 $conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin details - using prepared statement for security
 $admin_id = $_SESSION['admin_id'];
 $stmt = $conn->prepare("SELECT admin_id, email, branch FROM adminc WHERE admin_id = ?");
 $stmt->bind_param("s", $admin_id);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
} else {
    // If admin not found, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: alogin.php?error=notfound');
    exit;
}

 $stmt->close();
 $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Admin Dashboard</title>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght@400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        "card-light": "#ffffff",
                        "card-dark": "#334155",
                        "accent": "#818cf8",
                        "success": "#10b981",
                        "warning": "#f59e0b",
                        "danger": "#ef4444",
                    },
                    fontFamily: {
                        "display": ["Inter"]
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
                        'modal-fade-in': 'modalFadeIn 0.3s ease-out',
                        'modal-scale': 'modalScale 0.3s ease-out',
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
                        },
                        modalFadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        modalScale: {
                            '0%': { transform: 'scale(0.95)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                },
            },
        }
    </script>
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
        
        /* Card hover effects */
        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        
        /* Input focus styles */
        .custom-input:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        /* Background pattern */
        .bg-pattern {
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .dark .bg-pattern {
            background-color: #1e293b;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        /* Table styles */
        .table-row-hover:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }
        .dark .table-row-hover:hover {
            background-color: rgba(79, 70, 229, 0.1);
        }
        
        /* Active nav link */
        .nav-active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #4f46e5;
            border-radius: 0.5rem;
        }
        
        /* Dark mode toggle */
        .dark-mode-toggle {
            position: relative;
            width: 50px;
            height: 24px;
            background-color: #cbd5e1;
            border-radius: 9999px;
            transition: background-color 0.3s;
        }
        .dark-mode-toggle.active {
            background-color: #4f46e5;
        }
        .dark-mode-toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background-color: white;
            border-radius: 9999px;
            transition: transform 0.3s;
        }
        .dark-mode-toggle.active .dark-mode-toggle-slider {
            transform: translateX(26px);
        }
        
        /* Logout button styles */
        .logout-btn {
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            color: #ef4444;
            border-radius: 0.5rem;
        }
        
        /* Admin info card */
        .admin-info-card {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-left: 4px solid #4f46e5;
        }
    </style>
</head>
<body class="antialiased relative bg-background-light dark:bg-background-dark font-display">
    <div class="absolute inset-0 -z-10 bg-pattern"></div>
    <div class="min-h-screen">
        <!-- Header with Centered Navigation -->
        <header class="bg-card-light dark:bg-card-dark border-b border-gray-200 dark:border-gray-700 shadow-card animate-fade-in">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between py-6">
                    <!-- Left: Logo and Title -->
                    <div class="flex items-center">
                        <div class="gradient-primary p-2 rounded-lg mr-3">
                            <i class="fas fa-shield-alt text-white text-xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">CreditUpdate</h1>
                    </div>
                    
                    <!-- Center: Navigation Links -->
                    <nav class="hidden md:flex space-x-1">
                        <a class="flex items-center gap-2 px-4 py-2 rounded-lg nav-active transition-all" href="adash.php">
                            <i class="fas fa-tachometer-alt text-lg"></i>
                            <span class="font-medium">Dashboard</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all" href="teacher.php">
                            <i class="fas fa-chalkboard-teacher text-lg"></i>
                            <span class="font-medium">Teachers</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all" href="classes.php">
                            <i class="fas fa-school text-lg"></i>
                            <span class="font-medium">Classes</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all" href="#">
                            <i class="fas fa-cog text-lg"></i>
                            <span class="font-medium">Settings</span>
                        </a>
                    </nav>
                    
                    <!-- Right: Dark Mode Toggle and Logout -->
                    <div class="flex items-center space-x-4">
                        <button id="dark-mode-toggle" class="dark-mode-toggle">
                            <div class="dark-mode-toggle-slider"></div>
                        </button>
                        <a href="adminlogin.php" class="logout-btn flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 transition-all">
                            <i class="fas fa-sign-out-alt text-lg"></i>
                            <span class="font-medium">Logout</span>
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Navigation -->
                <div class="md:hidden py-4 border-t border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-1 overflow-x-auto pb-2">
                        <a class="flex items-center gap-2 px-4 py-2 rounded-lg nav-active transition-all whitespace-nowrap" href="adash.php">
                            <i class="fas fa-tachometer-alt text-lg"></i>
                            <span class="font-medium">Dashboard</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all whitespace-nowrap" href="teacher.php">
                            <i class="fas fa-chalkboard-teacher text-lg"></i>
                            <span class="font-medium">Teachers</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all whitespace-nowrap" href="classes.php">
                            <i class="fas fa-school text-lg"></i>
                            <span class="font-medium">Classes</span>
                        </a>
                        <a class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 dark:hover:bg-primary/10 transition-all whitespace-nowrap" href="#">
                            <i class="fas fa-cog text-lg"></i>
                            <span class="font-medium">Settings</span>
                        </a>
                        <a href="adminlogin.php" class="logout-btn flex items-center gap-2 px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 transition-all whitespace-nowrap">
                            <i class="fas fa-sign-out-alt text-lg"></i>
                            <span class="font-medium">Logout</span>
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-auto">
            <div class="max-w-7xl mx-auto">
                <!-- Header Section -->
                <header class="mb-8 animate-slide-up">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Admin Dashboard</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Efficiently manage teachers, classes, and schedules.</p>
                    </div>
                </header>

                <!-- Admin Info Card -->
                <section class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-card admin-info-card mb-8 animate-slide-up">
                    <div class="flex flex-col md:flex-row items-center md:items-start">
                        <div class="gradient-primary p-5 rounded-lg mr-6 mb-6 md:mb-0">
                            <i class="fas fa-user-shield text-white text-4xl"></i>
                        </div>
                        <div class="text-center md:text-left flex-1">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">Admin Information</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Admin ID</p>
                                    <p class="text-xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($admin['admin_id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Email</p>
                                    <p class="text-xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($admin['email']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Branch</p>
                                    <p class="text-xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($admin['branch']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Welcome Card -->
                <section class="bg-card-light dark:bg-card-dark p-8 rounded-xl shadow-card hover-card animate-slide-up">
                    <div class="flex flex-col md:flex-row items-center md:items-start">
                        <div class="gradient-primary p-5 rounded-lg mr-6 mb-6 md:mb-0">
                            <i class="fas fa-user-shield text-white text-4xl"></i>
                        </div>
                        <div class="text-center md:text-left">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">Welcome to CreditUpdate Admin Panel</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-6">
                                This is your centralized dashboard for managing all aspects of the credit system. 
                                Use the navigation links above to access different sections of the admin panel.
                            </p>
                            <div class="flex flex-wrap justify-center md:justify-start gap-4">
                                <a href="teacher.php" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2 transition-all">
                                    <i class="fas fa-users"></i>
                                    Manage Teachers
                                </a>
                                <a href="classes.php" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2 transition-all">
                                    <i class="fas fa-calendar-alt"></i>
                                    Manage Classes
                                </a>
                                <a href="#" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2 transition-all">
                                    <i class="fas fa-cog"></i>
                                    System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Stats Overview -->
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                    <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Total Teachers</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2">24</p>
                            </div>
                            <div class="bg-accent/20 p-3 rounded-lg">
                                <i class="fas fa-chalkboard-teacher text-accent text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Active Classes</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2">48</p>
                            </div>
                            <div class="bg-success/20 p-3 rounded-lg">
                                <i class="fas fa-school text-success text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-card hover-card animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Total Credits</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2">1,245</p>
                            </div>
                            <div class="bg-primary/20 p-3 rounded-lg">
                                <i class="fas fa-award text-primary text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const htmlElement = document.documentElement;
        
        // Check for saved dark mode preference
        const isDarkMode = htmlElement.classList.contains('dark');
        if (isDarkMode) {
            darkModeToggle.classList.add('active');
        }
        
        darkModeToggle.addEventListener('click', function() {
            htmlElement.classList.toggle('dark');
            darkModeToggle.classList.toggle('active');
        });
        
        // Add animations on scroll
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);
        
        // Observe all sections
        document.querySelectorAll('section').forEach(section => {
            observer.observe(section);
        });
        
        // Logout confirmation
        document.addEventListener('DOMContentLoaded', function() {
            // Only attach logout confirmation to elements with the logout-btn class
            const logoutButtons = document.querySelectorAll('.logout-btn');
            
            logoutButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You want to logout?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#4f46e5',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, logout!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'adminlogin.php';
                        }
                    });
                });
            });
            
            // Welcome toast
            const Toast = Swal.mixin({
                toast: true,
                position: 'bottom-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            Toast.fire({
                icon: 'success',
                title: 'Welcome, <?php echo htmlspecialchars($admin['admin_id']); ?>!'
            });
        });
    </script>
</body>
</html>