<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!logged_in() || $_SESSION['role'] !== 'parent') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$parent_id = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Fetch rules
if ($method === 'GET') {
    $child_id = isset($_GET['child_id']) && $_GET['child_id'] !== 'tout' ? (int)$_GET['child_id'] : null;
    
    if ($child_id) {
        $stmt = $pdo->prepare("SELECT id, url, child_id FROM custom_blocked_sites WHERE parent_id = ? AND (child_id = ? OR child_id IS NULL) ORDER BY created_at DESC");
        $stmt->execute([$parent_id, $child_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, url, child_id FROM custom_blocked_sites WHERE parent_id = ? ORDER BY created_at DESC");
        $stmt->execute([$parent_id]);
    }
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Data input
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Add Rule
if ($method === 'POST') {
    $url = trim($data['url'] ?? '');
    $child_id = isset($data['child_id']) && $data['child_id'] !== 'tout' ? (int)$data['child_id'] : null;
    
    if (empty($url)) {
        http_response_code(400);
        echo json_encode(['error' => 'L\'URL est requise.']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO custom_blocked_sites (parent_id, child_id, url) VALUES (?, ?, ?)");
    $stmt->execute([$parent_id, $child_id, $url]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'url' => $url]);
    exit;
}

// Delete Rule
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide.']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM custom_blocked_sites WHERE id = ? AND parent_id = ?");
    $stmt->execute([$id, $parent_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

// Edit Rule (PUT)
if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    $url = trim($data['url'] ?? '');
    
    if ($id <= 0 || empty($url)) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides.']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE custom_blocked_sites SET url = ? WHERE id = ? AND parent_id = ?");
    $stmt->execute([$url, $id, $parent_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
