<?php
require 'config.php';
if (!logged_in() || $_SESSION['role'] !== 'child') {
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['parent_phone'] ?? '');
    
    // Find parent from child's parent_id
    $p = $pdo->prepare("SELECT u.id, u.username, u.role, u.avatar, u.phone FROM users u JOIN users c ON c.parent_id = u.id WHERE c.id = ?");
    $p->execute([$_SESSION['user_id']]);
    $parent = $p->fetch();
    
    if ($parent && !empty($parent['phone']) && $parent['phone'] === $phone) {
        // Swap session
        $_SESSION['user_id']  = $parent['id'];
        $_SESSION['username'] = $parent['username'];
        $_SESSION['role']     = $parent['role'];
        $_SESSION['avatar']   = $parent['avatar'] ?? 'avatar1.png';
        header('Location: parent_dashboard.php');
        exit;
    } else {
        // Redirect back with err (simplest way is back to child dashboard via JS)
        echo "<script>alert('Numéro incorrect ou compte parent mal configuré.'); window.location.href='child_dashboard.php';</script>";
        exit;
    }
}
header('Location: child_dashboard.php');
