<?php
/**
 * GearGuard CMMS - Authentication & Session Management
 * Production-Ready Security Implementation
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS in production
    session_start();
}

/**
 * Check if user is authenticated
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, username, email, full_name, role, avatar 
            FROM users 
            WHERE id = ? AND is_active = TRUE
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Get Current User Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    if (is_array($role)) {
        return in_array($user['role'], $role);
    }
    
    return $user['role'] === $role;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require specific role - redirect to dashboard if insufficient permissions
 */
function requireRole($role) {
    requireAuth();
    
    if (!hasRole($role)) {
        $_SESSION['error'] = "You don't have permission to access this resource.";
        header('Location: /dashboard.php');
        exit;
    }
}

/**
 * User login - authenticate credentials
 */
function login($username, $password) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, username, password_hash, full_name, role 
            FROM users 
            WHERE username = ? AND is_active = TRUE
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        return ['success' => true, 'message' => 'Login successful'];
        
    } catch(PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during login'];
    }
}

/**
 * User logout - destroy session
 */
function logout() {
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Check if user is member of specific maintenance team
 */
function isTeamMember($teamId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM team_members 
            WHERE team_id = ? AND user_id = ? AND is_active = TRUE
        ");
        $stmt->execute([$teamId, $_SESSION['user_id']]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch(PDOException $e) {
        error_log("Check Team Member Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's teams
 */
function getUserTeams() {
    if (!isLoggedIn()) {
        return [];
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT mt.id, mt.name, mt.code 
            FROM maintenance_teams mt
            INNER JOIN team_members tm ON mt.id = tm.team_id
            WHERE tm.user_id = ? AND tm.is_active = TRUE AND mt.is_active = TRUE
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Get User Teams Error: " . $e->getMessage());
        return [];
    }
}
?>