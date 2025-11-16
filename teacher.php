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
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $employee_id = $_POST['employee_id'];
        $password = $_POST['password'];
        $emp_name = $_POST['emp_name'];
        $role = $_POST['role'];
        $sub_name = $_POST['sub_name'];
        $department = $_POST['department'];
        $email = $_POST['email'];
        
        // Verify that the department matches admin's branch
        if ($department !== $admin_branch) {
            $message = 'You can only add/edit teachers in your department: ' . $admin_branch;
            $messageType = 'danger';
        } else {
            // Handle profile image upload
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                $upload_dir = 'uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $new_filename = $employee_id . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    $profile_image = $upload_path;
                }
            }
            
            if ($action === 'add') {
                $sql = "INSERT INTO credentials (employee_id, password, emp_name, role, sub_name, department, Email, profile_image) 
                        VALUES (:employee_id, :password, :emp_name, :role, :sub_name, :department, :email, :profile_image)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':employee_id' => $employee_id,
                    ':password' => $password,
                    ':emp_name' => $emp_name,
                    ':role' => $role,
                    ':sub_name' => $sub_name,
                    ':department' => $department,
                    ':email' => $email,
                    ':profile_image' => $profile_image
                ]);
                $message = 'Teacher added successfully!';
                $messageType = 'success';
            } else {
                // Verify the teacher belongs to admin's department before editing
                $check_sql = "SELECT department FROM credentials WHERE employee_id = :employee_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':employee_id' => $employee_id]);
                $existing_teacher = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_teacher && $existing_teacher['department'] !== $admin_branch) {
                    $message = 'You can only edit teachers in your department!';
                    $messageType = 'danger';
                } else {
                    $update_fields = [
                        'password' => $password,
                        'emp_name' => $emp_name,
                        'role' => $role,
                        'sub_name' => $sub_name,
                        'department' => $department,
                        'Email' => $email
                    ];
                    
                    if ($profile_image) {
                        $update_fields['profile_image'] = $profile_image;
                    }
                    
                    $set_clause = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($update_fields)));
                    $sql = "UPDATE credentials SET $set_clause WHERE employee_id = :employee_id";
                    
                    $stmt = $pdo->prepare($sql);
                    $update_fields['employee_id'] = $employee_id;
                    $stmt->execute($update_fields);
                    
                    $message = 'Teacher updated successfully!';
                    $messageType = 'success';
                }
            }
        }
    } elseif ($action === 'delete') {
        $employee_id = $_POST['employee_id'];
        
        // Verify the teacher belongs to admin's department before deleting
        $check_sql = "SELECT department FROM credentials WHERE employee_id = :employee_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':employee_id' => $employee_id]);
        $teacher = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher && $teacher['department'] !== $admin_branch) {
            $message = 'You can only delete teachers in your department!';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM credentials WHERE employee_id = :employee_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':employee_id' => $employee_id]);
            $message = 'Teacher deleted successfully!';
            $messageType = 'danger';
        }
    }
}

// Fetch teachers with filters - ONLY from admin's branch
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT * FROM credentials WHERE department = :admin_branch";
$params = [':admin_branch' => $admin_branch];

if ($search) {
    $sql .= " AND (emp_name LIKE :search OR employee_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
    $sql .= " AND role = :role";
    $params[':role'] = $role_filter;
}

$sql .= " ORDER BY employee_id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Teacher Management</title>
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
        .profile-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #4f46e5;
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
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary/10 text-primary border-l-3 border-primary transition-all" href="teacher.php">
                    <i class="fas fa-chalkboard-teacher text-lg"></i>
                    <span class="font-medium">Teachers</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-primary/5 transition-all" href="classes.php">
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
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Teacher Management</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage teachers in <span class="font-semibold text-primary"><?php echo htmlspecialchars($admin_branch); ?></span> department.</p>
                    </div>
                    <button onclick="openModal()" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold flex items-center gap-2">
                        <i class="fas fa-user-plus"></i>
                        Add New Teacher
                    </button>
                </header>

                <?php if ($message): ?>
                <div class="mb-4 px-6 py-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-lg mb-8">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or ID..." class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role</label>
                            <select name="role" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-gray-800 dark:text-white px-4 py-2">
                                <option value="">All Roles</option>
                                <option value="Professor" <?php echo $role_filter === 'Professor' ? 'selected' : ''; ?>>Professor</option>
                                <option value="Associate Professor" <?php echo $role_filter === 'Associate Professor' ? 'selected' : ''; ?>>Associate Professor</option>
                                <option value="Assistant Professor" <?php echo $role_filter === 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary-dark transition-all font-medium">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <a href="teacher.php" class="px-4 py-2 rounded-lg border-2 border-primary text-primary hover:bg-primary hover:text-white transition-all font-medium">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Teachers Table -->
                <section>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">All Teachers</h2>
                        <span class="text-gray-600 dark:text-gray-400">Total: <span class="font-bold text-primary"><?php echo count($teachers); ?></span> Teachers</span>
                    </div>
                    <div class="overflow-x-auto bg-card-light dark:bg-card-dark rounded-xl shadow-lg">
                        <table class="w-full text-left">
                            <thead class="border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Profile</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Employee ID</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Name</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Email</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Subject</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Role</th>
                                    <th class="p-4 font-semibold text-gray-600 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-primary/5 transition-all">
                                    <td class="p-4">
                                        <img src="<?php echo $teacher['profile_image'] ?: 'https://via.placeholder.com/50'; ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover border-2 border-primary">
                                    </td>
                                    <td class="p-4 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($teacher['employee_id']); ?></td>
                                    <td class="p-4 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($teacher['emp_name']); ?></td>
                                    <td class="p-4 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($teacher['Email']); ?></td>
                                    <td class="p-4 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($teacher['sub_name']); ?></td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($teacher['role']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <button onclick='editTeacher(<?php echo json_encode($teacher); ?>)' class="text-primary hover:text-primary-dark transition-colors mr-3" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this teacher?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="employee_id" value="<?php echo $teacher['employee_id']; ?>">
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

    <!-- Add/Edit Teacher Modal -->
    <div id="teacher-modal" class="modal">
        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-lg p-8 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 dark:text-white" id="modal-title">Add New Teacher</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" id="form-action" value="add">
                
                <div class="flex flex-col items-center mb-6">
                    <img id="profile-preview" src="https://via.placeholder.com/120" alt="Profile Preview" class="profile-preview mb-4">
                    <input type="file" name="profile_image" id="profile-image" accept="image/*" class="mt-2">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Employee ID *</label>
                        <input type="text" name="employee_id" id="employee-id" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name *</label>
                        <input type="text" name="emp_name" id="emp-name" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email *</label>
                        <input type="email" name="email" id="email" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password *</label>
                        <input type="password" name="password" id="password" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Department *</label>
                        <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($admin_branch); ?>" readonly required class="block w-full rounded-lg border-gray-300 px-4 py-2 bg-gray-100 cursor-not-allowed"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role *</label>
                        <select name="role" id="role" required class="block w-full rounded-lg border-gray-300 px-4 py-2">
                            <option value="">Select Role</option>
                            <option value="Professor">Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Assistant Professor">Assistant Professor</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject Name *</label>
                        <input type="text" name="sub_name" id="sub-name" required class="block w-full rounded-lg border-gray-300 px-4 py-2"/>
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="submit" class="btn-primary flex-1 px-6 py-3 rounded-lg text-white font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Teacher
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-100 transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const adminBranch = <?php echo json_encode($admin_branch); ?>;

        function openModal() {
            document.getElementById('teacher-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Add New Teacher';
            document.getElementById('form-action').value = 'add';
            document.getElementById('employee-id').removeAttribute('readonly');
            document.getElementById('department').value = adminBranch;
        }

        function closeModal() {
            document.getElementById('teacher-modal').classList.remove('active');
            document.querySelector('form').reset();
            document.getElementById('profile-preview').src = 'https://via.placeholder.com/120';
            document.getElementById('department').value = adminBranch;
        }

        function editTeacher(teacher) {
            document.getElementById('teacher-modal').classList.add('active');
            document.getElementById('modal-title').textContent = 'Edit Teacher';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('employee-id').value = teacher.employee_id;
            document.getElementById('employee-id').setAttribute('readonly', 'readonly');
            document.getElementById('emp-name').value = teacher.emp_name;
            document.getElementById('email').value = teacher.Email;
            document.getElementById('password').value = teacher.password;
            document.getElementById('department').value = teacher.department;
            document.getElementById('role').value = teacher.role;
            document.getElementById('sub-name').value = teacher.sub_name;
            if (teacher.profile_image) {
                document.getElementById('profile-preview').src = teacher.profile_image;
            }
        }

        document.getElementById('profile-image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>