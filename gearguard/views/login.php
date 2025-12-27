<?php
/**
 * GearGuard CMMS - Login Page
 * Secure session-based authentication
 */

require_once 'C:\xampp\htdocs\gearguard\config\database.php';
require_once 'C:\xampp\htdocs\gearguard\includes\auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $result = login($username, $password);
        
        if ($result['success']) {
            // Check if there's a redirect URL
            $redirectUrl = $_SESSION['redirect_after_login'] ?? 'kanban.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Please enter both username and password";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GearGuard CMMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 3rem;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .logo p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .demo-credentials {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .demo-credentials h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .demo-account {
            padding: 0.5rem 0;
            color: #555;
        }
        
        .demo-account strong {
            color: #2c3e50;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #999;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🛠️ GearGuard</h1>
            <p>Maintenance & Asset Management System</p>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required 
                       autofocus
                       autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required
                       autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn-login">
                Login to System
            </button>
        </form>
        
        <div class="demo-credentials">
            <h3>🎓 Hackathon Demo Accounts:</h3>
            <div class="demo-account">
                <strong>Administrator:</strong> admin / password
            </div>
            <div class="demo-account">
                <strong>Manager:</strong> john.manager / password
            </div>
            <div class="demo-account">
                <strong>Technician:</strong> tech.mike / password
            </div>
            <div class="demo-account">
                <strong>Regular User:</strong> user.david / password
            </div>
        </div>
        
        <div class="footer">
            Odoo × Adani University Hackathon 2025
        </div>
    </div>
</body>
</html>