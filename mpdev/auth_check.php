<?php
// Authentication check file
session_start();

function checkAuth($required_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Check if specific roles are required
    if (!empty($required_roles) && !in_array($_SESSION['role'], $required_roles)) {
        header('Location: unauthorized.php');
        exit();
    }
    
    return true;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserName() {
    return $_SESSION['username'] ?? null;
}

function hasPermission($permission) {
    $role = getUserRole();
    
    $permissions = [
        'admin' => ['view_all', 'edit_all', 'delete_all', 'manage_users'],
        'manager' => ['view_all', 'edit_all', 'manage_reports'],
        'spv' => ['view_all', 'edit_employees', 'manage_education'],
        'leader' => ['view_employees', 'edit_employees', 'view_education'],
        'foreman' => ['view_employees', 'edit_employees'],
        'trainer' => ['view_education', 'edit_education', 'manage_training']
    ];
    
    return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
}
?>