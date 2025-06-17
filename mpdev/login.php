<?php
session_start();
require_once '../backend/database.php';

// Function to create default users if not exist
function createDefaultUsers($conn) {
    try {
        // Check if users table has any data
        $checkUsers = $conn->query("SELECT COUNT(*) FROM users");
        $userCount = $checkUsers->fetchColumn();
        
        if ($userCount == 0) {
            // Create default users
            $defaultUsers = [
                ['username' => 'admin', 'password' => 'password123', 'role' => 'admin'],
                ['username' => 'trainer', 'password' => 'password123', 'role' => 'trainer'],
                ['username' => 'leader', 'password' => 'password123', 'role' => 'leader'],
                ['username' => 'foreman', 'password' => 'password123', 'role' => 'foreman'],
                ['username' => 'spv', 'password' => 'password123', 'role' => 'spv'],
                ['username' => 'manager', 'password' => 'password123', 'role' => 'manager']
            ];
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            
            foreach ($defaultUsers as $user) {
                $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
                $stmt->execute([$user['username'], $hashedPassword, $user['role']]);
            }
            
            return "✅ Default users berhasil dibuat!";
        }
        
        return null;
    } catch (PDOException $e) {
        return "❌ Error creating users: " . $e->getMessage();
    }
}

// Function to reset all user passwords
function resetUserPasswords($conn) {
    try {
        $defaultUsers = [
            ['username' => 'admin', 'password' => 'password123'],
            ['username' => 'trainer', 'password' => 'password123'],
            ['username' => 'leader', 'password' => 'password123'],
            ['username' => 'foreman', 'password' => 'password123'],
            ['username' => 'spv', 'password' => 'password123'],
            ['username' => 'manager', 'password' => 'password123']
        ];
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        
        foreach ($defaultUsers as $user) {
            $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
            $stmt->execute([$hashedPassword, $user['username']]);
        }
        
        return "🔄 Password semua user berhasil di-reset ke 'password123'!";
    } catch (PDOException $e) {
        return "❌ Error resetting passwords: " . $e->getMessage();
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$debug_info = '';

// Initialize database and create users if needed
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Handle password reset request
    if (isset($_GET['reset_passwords'])) {
        $resetResult = resetUserPasswords($conn);
        if ($resetResult) {
            $success = $resetResult;
        }
    }
    
    // Auto-create default users if table is empty
    $createResult = createDefaultUsers($conn);
    if ($createResult) {
        $success = $createResult;
    }
    
} catch (PDOException $e) {
    $error = 'Database connection error: ' . $e->getMessage();
}

if ($_POST && !$error) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // Trim password juga
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            // Debug: Check if users table exists and has data
            $checkTable = $conn->query("SELECT COUNT(*) FROM users");
            $userCount = $checkTable->fetchColumn();
            $debug_info = "Total users in database: $userCount";
            
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $debug_info .= " | User found: {$user['username']} (Role: {$user['role']})";
                
                // Debug: Show password info
                $debug_info .= " | Input password length: " . strlen($password);
                $debug_info .= " | Hash starts with: " . substr($user['password'], 0, 10) . "...";
                
                // Try password verification
                $passwordMatch = password_verify($password, $user['password']);
                $debug_info .= " | Password match: " . ($passwordMatch ? 'YES' : 'NO');
                
                if ($passwordMatch) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: dashboard.php');
                            break;
                        case 'manager':
                        case 'spv':
                            header('Location: dashboard.php');
                            break;
                        case 'leader':
                        case 'foreman':
                            header('Location: employees.php');
                            break;
                        case 'trainer':
                            header('Location: education.php');
                            break;
                        default:
                            header('Location: dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Username atau password salah';
                }
            } else {
                $debug_info .= " | User NOT found";
                $error = 'Username atau password salah';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MP Dev System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login-style.css">
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">🏭 MPD MS</h2>
                <p class="text-muted">Silakan login untuk melanjutkan</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($debug_info && !$success): ?>
                <div class="alert alert-info" role="alert">
                    <small><strong>Debug:</strong> <?php echo htmlspecialchars($debug_info); ?></small>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary login-btn w-100">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="mt-3 text-center">
                <a href="?reset_passwords=1" class="btn btn-sm btn-outline-warning">
                    🔄 Reset All Passwords
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>