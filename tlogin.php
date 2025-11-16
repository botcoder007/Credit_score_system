<?php
session_start();

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Database configuration - MAKE SURE THIS MATCHES YOUR ACTUAL DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "credit"; // Changed from "employeedb" to match your dashboard code

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";
$success_message = "";
$forgot_password_mode = false;
$show_recovery_in_modal = false;

// Function to send email using PHPMailer
function sendPasswordEmail($to_email, $employee_name, $employee_id, $password) {
    $mail = new PHPMailer(true);

    try {
        // Server settings - Use your working configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'creditupdatevfstr@gmail.com';  // Your Gmail address
        $mail->Password   = 'osufcsdflntokogp';              // Your App Password
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
        $mail->addAddress($to_email, $employee_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Recovery - Credit Update System';
        
        // Enhanced HTML email template
        $mail->Body = "
        <html>
        <head>
            <title>Password Recovery</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .credentials { background: white; padding: 25px; border-radius: 8px; border-left: 4px solid #4f46e5; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 20px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 15px 0; }
                .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .login-info { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 28px;'>🔐 Password Recovery</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Credit Update System</p>
                </div>
                <div class='content'>
                    <h2 style='color: #333; margin-top: 0;'>Hello $employee_name,</h2>
                    <p>You have requested a password recovery for your teacher account. Here are your login credentials:</p>
                    
                    <div class='credentials'>
                        <h3 style='color: #4f46e5; margin-top: 0;'>📋 Your Login Details:</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; color: #555;'>Teacher ID:</td>
                                <td style='padding: 8px 0; color: #333; font-size: 16px;'>$employee_id</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; font-weight: bold; color: #555;'>Password:</td>
                                <td style='padding: 8px 0; color: #333; font-size: 16px; font-family: monospace; background: #f8f9fa; padding: 5px 8px; border-radius: 3px;'>$password</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class='login-info'>
                        <h4 style='margin-top: 0; color: #17a2b8;'>📍 How to Login:</h4>
                        <ol style='margin: 10px 0; padding-left: 20px;'>
                            <li>Go to the Credit Update System login page</li>
                            <li>Enter your Teacher ID: <strong>$employee_id</strong></li>
                            <li>Enter the password provided above</li>
                            <li>Click 'Log In' to access your account</li>
                        </ol>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong><br>
                        • For security reasons, please consider changing your password after logging in<br>
                        • If you did not request this password recovery, please contact your administrator immediately<br>
                        • This email contains sensitive information - please keep it secure
                    </div>
                    
                    <p>If you have any questions or need technical assistance, please contact the IT support team.</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br><strong>IT Support Team</strong><br>Credit Update System</p>
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
        $mail->AltBody = "Password Recovery - Credit Update System\n\n"
                       . "Hello $employee_name,\n\n"
                       . "Your login credentials:\n"
                       . "Teacher ID: $employee_id\n"
                       . "Password: $password\n\n"
                       . "Please use these credentials to log in to the Credit Update System.\n\n"
                       . "For security reasons, please consider changing your password after logging in.\n\n"
                       . "If you did not request this password recovery, please contact your administrator immediately.\n\n"
                       . "Best regards,\nIT Support Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle forgot password request
    if (isset($_POST['forgot_password'])) {
        $employee_id = trim($_POST['forgot-employee-id']);
        $email = trim($_POST['forgot-email']);
        
        if (empty($employee_id) || empty($email)) {
            $error_message = "Please enter both Teacher ID and Email address.";
            $forgot_password_mode = true;
        } else {
            // Check if employee exists with matching email
            $stmt = $conn->prepare("SELECT employee_id, emp_name, password, email FROM credentials WHERE employee_id = ? AND email = ?");
            $stmt->bind_param("ss", $employee_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                
                // Send password via email using PHPMailer
                if (sendPasswordEmail($email, $row['emp_name'], $employee_id, $row['password'])) {
                    $success_message = "Password has been sent to your email address: " . $email . ". Please check your inbox (and spam folder).";
                    $forgot_password_mode = false; // Close modal on success
                } else {
                    $error_message = "Failed to send email. Please try again or contact support. Make sure the email address is correct.";
                    $forgot_password_mode = true;
                }
            } else {
                $error_message = "Teacher ID and Email combination not found in our records. Please verify your details.";
                $forgot_password_mode = true;
            }
            
            $stmt->close();
        }
    }
    // Handle regular login
    else {
        $employee_id = $_POST['employee-id'];
        $password = $_POST['password'];
        
        // Debug: Let's see what we're getting
        error_log("Login attempt - Employee ID: " . $employee_id . ", Password: " . $password);
        
        // Validate input
        if (empty($employee_id) || empty($password)) {
            $error_message = "Please enter both Teacher ID and Password.";
        } else {
            // Prepare and execute query - using string parameter
            $stmt = $conn->prepare("SELECT employee_id, password, emp_name, role, sub_name, department FROM credentials WHERE employee_id = ?");
            $stmt->bind_param("s", $employee_id); // Changed from "i" to "s"
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                
                // Debug: Let's see what we got from database
                error_log("Database result - Employee ID: " . $row['employee_id'] . ", Password: " . $row['password']);
                
                // Check if password matches (plain text comparison)
                // Trim both values to remove any whitespace
                if (trim($password) === trim($row['password'])) {
                    // Login successful - store user data in session
                    $_SESSION['employee_id'] = $row['employee_id'];
                    $_SESSION['emp_name'] = $row['emp_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['sub_name'] = $row['sub_name'];
                    $_SESSION['department'] = $row['department'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect to dashboard
                    header("Location: tdash.php");
                    exit();
                } else {
                    $error_message = "Invalid Teacher ID or Password.";
                }
            } else {
                $error_message = "Invalid Teacher ID or Password. (Teacher not found)";
            }
            
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Credit Update System Login</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&family=Noto+Sans:wght@400;500;700;900&display=swap" rel="stylesheet"/>
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
        };
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
        
        /* Enhanced Modal styles with better positioning */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2rem;
            width: 100%;
            max-width: 28rem;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .dark .modal-content {
            background: #334155;
            color: white;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1);
            animation: modalScale 0.3s ease-out;
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
        
        /* Error and success message styles */
        .error-message {
            @apply bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 shadow-card animate-fade-in;
        }
        .dark .error-message {
            @apply bg-red-900/20 border-red-800 text-red-400;
        }
        .success-message {
            @apply bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 shadow-card animate-fade-in;
        }
        .dark .success-message {
            @apply bg-green-900/20 border-green-800 text-green-400;
        }
        
        /* Responsive adjustments for mobile */
        @media (max-width: 640px) {
            .modal-overlay {
                padding: 0.5rem;
            }
            .modal-content {
                padding: 1.5rem;
                max-height: 95vh;
            }
        }
        
        /* Prevent body scroll when modal is open */
        body.modal-open {
            overflow: hidden;
        }
    </style>
</head>
<body class="antialiased relative bg-background-light dark:bg-background-dark font-display text-gray-800 dark:text-gray-200">
    <div class="absolute inset-0 -z-10 bg-pattern"></div>
    <div class="flex min-h-screen flex-col items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="flex flex-col items-center mb-8 animate-fade-in">
                <div class="gradient-primary p-4 rounded-full mb-4 shadow-lg">
                    <i class="fas fa-user-graduate text-white text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Teacher Login</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Welcome back! Please enter your details.</p>
            </div>
            
            <div class="bg-card-light dark:bg-card-dark p-8 rounded-xl shadow-card hover-card animate-slide-up">
                <?php if (!empty($error_message) && !$forgot_password_mode): ?>
                    <div class="error-message">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message) && !$forgot_password_mode): ?>
                    <!-- Success message will be shown in popup modal -->
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6" method="POST">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="employee-id">Teacher ID</label>
                        <div class="mt-1 relative">
                            <input class="custom-input block w-full appearance-none rounded-lg border border-gray-300 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 pl-10 text-gray-900 dark:text-white placeholder-gray-400 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary transition-all" 
                                   id="employee-id" 
                                   name="employee-id" 
                                   required 
                                   type="text"
                                   value="<?php echo isset($_POST['employee-id']) ? htmlspecialchars($_POST['employee-id']) : ''; ?>"
                                   placeholder="Enter your Teacher ID"/>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-badge text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="password">Password</label>
                        <div class="mt-1 relative">
                            <input autocomplete="current-password" 
                                   class="custom-input block w-full appearance-none rounded-lg border border-gray-300 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 pl-10 pr-12 text-gray-900 dark:text-white placeholder-gray-400 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary transition-all" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   type="password"
                                   placeholder="Enter your password"/>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" 
                                    onclick="togglePassword()" 
                                    type="button">
                                <i class="fas fa-eye" id="password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <a class="font-medium text-primary hover:text-primary-dark cursor-pointer transition-colors" onclick="openForgotPasswordModal()">Forgot your password?</a>
                        </div>
                    </div>
                    
                    <div>
                        <button class="btn-primary flex w-full justify-center rounded-lg border border-transparent py-3 px-4 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors" 
                                type="submit">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Log In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Message Modal -->
    <div id="successModal" class="modal-overlay <?php echo (!empty($success_message) && !$forgot_password_mode) ? 'active' : ''; ?>">
        <div class="modal-content">
            <div class="flex items-center justify-center mb-6">
                <div class="gradient-primary p-3 rounded-full mr-3">
                    <i class="fas fa-check text-white text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Email Sent Successfully!</h2>
            </div>
            
            <div class="text-center">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 mb-6">
                    <div class="flex items-start justify-center">
                        <i class="fas fa-envelope-open-text text-green-600 dark:text-green-400 text-3xl mr-3"></i>
                        <div class="text-left">
                            <p class="text-green-800 dark:text-green-200 font-medium mb-2">Password Recovery Email Sent</p>
                            <p class="text-sm text-green-700 dark:text-green-300">
                                <?php echo isset($success_message) ? $success_message : ''; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-lightbulb text-blue-500 mr-2 mt-0.5"></i>
                        <div class="text-sm text-blue-700 dark:text-blue-300 text-left">
                            <p class="font-medium mb-2">What to do next:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Check your email inbox for the password recovery email</li>
                                <li>If you don't see it, check your spam/junk folder</li>
                                <li>The email contains your login credentials</li>
                                <li>Use those credentials to log in to the system</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <button onclick="closeSuccessModal()" 
                        class="btn-primary w-full flex items-center justify-center rounded-lg border border-transparent py-3 px-4 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors">
                    <i class="fas fa-thumbs-up mr-2"></i>
                    Got It, Thanks!
                </button>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal with Email Field -->
    <div id="forgotPasswordModal" class="modal-overlay <?php echo $forgot_password_mode ? 'active' : ''; ?>">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-envelope mr-2 text-primary"></i>
                    Password Recovery
                </h2>
                <button onclick="closeForgotPasswordModal()" 
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Display error message in modal -->
            <?php if (!empty($error_message) && $forgot_password_mode): ?>
                <div class="error-message">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" for="forgot-employee-id">Teacher ID</label>
                    <div class="relative">
                        <input class="custom-input block w-full appearance-none rounded-lg border border-gray-300 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 pl-10 text-gray-900 dark:text-white placeholder-gray-400 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary transition-all" 
                               id="forgot-employee-id" 
                               name="forgot-employee-id" 
                               required 
                               type="text"
                               value="<?php echo isset($_POST['forgot-employee-id']) ? htmlspecialchars($_POST['forgot-employee-id']) : ''; ?>"
                               placeholder="Enter your Teacher ID"/>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-id-badge text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" for="forgot-email">Email Address</label>
                    <div class="relative">
                        <input class="custom-input block w-full appearance-none rounded-lg border border-gray-300 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 pl-10 text-gray-900 dark:text-white placeholder-gray-400 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary transition-all" 
                               id="forgot-email" 
                               name="forgot-email" 
                               required 
                               type="email"
                               value="<?php echo isset($_POST['forgot-email']) ? htmlspecialchars($_POST['forgot-email']) : ''; ?>"
                               placeholder="Enter your email address"/>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mr-2 mt-0.5"></i>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p class="mb-2"><strong>Password Recovery Process:</strong></p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Enter your Teacher ID and registered email address</li>
                                <li>We'll verify your details in our system</li>
                                <li>Your password will be sent to your email address</li>
                                <li>Check your inbox (and spam folder) for the recovery email</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                    <button type="submit" 
                            name="forgot_password"
                            class="btn-primary flex-1 flex items-center justify-center rounded-lg border border-transparent py-3 px-4 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Password to Email
                    </button>
                    <button type="button" 
                            onclick="closeForgotPasswordModal()"
                            class="flex-1 flex items-center justify-center rounded-lg border border-gray-300 bg-white dark:bg-gray-700 py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function openForgotPasswordModal() {
            const modal = document.getElementById('forgotPasswordModal');
            document.body.classList.add('modal-open');
            modal.classList.add('active');
            
            // Focus the first input field after modal opens
            setTimeout(() => {
                document.getElementById('forgot-employee-id').focus();
            }, 300);
        }

        function closeForgotPasswordModal() {
            const modal = document.getElementById('forgotPasswordModal');
            document.body.classList.remove('modal-open');
            modal.classList.remove('active');
        }

        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            document.body.classList.remove('modal-open');
            modal.classList.remove('active');
        }

        // Close modal when clicking outside the content
        document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotPasswordModal();
            }
        });

        // Close success modal when clicking outside the content
        document.getElementById('successModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSuccessModal();
            }
        });

        // Handle escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const forgotModal = document.getElementById('forgotPasswordModal');
                const successModal = document.getElementById('successModal');
                
                if (forgotModal.classList.contains('active')) {
                    closeForgotPasswordModal();
                }
                if (successModal.classList.contains('active')) {
                    closeSuccessModal();
                }
            }
        });
        
        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark');
        }

        // Prevent scrolling when modal is open on mobile
        function preventScroll(e) {
            e.preventDefault();
        }

        // Add scroll prevention when modal opens
        document.getElementById('forgotPasswordModal').addEventListener('transitionstart', function() {
            if (this.classList.contains('active')) {
                document.addEventListener('touchmove', preventScroll, { passive: false });
            } else {
                document.removeEventListener('touchmove', preventScroll);
            }
        });
        
        // Auto-open modal if PHP detected forgot password mode
        <?php if ($forgot_password_mode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openForgotPasswordModal();
        });
        <?php endif; ?>
        
        // Auto-close modal and show success message if email was sent successfully
        <?php if (!empty($success_message) && !$forgot_password_mode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal if it's open
            const modal = document.getElementById('forgotPasswordModal');
            if (modal.classList.contains('active')) {
                closeForgotPasswordModal();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>