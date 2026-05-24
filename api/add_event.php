<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed — use POST']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$child_id     = (int)($data['child_id']      ?? 0);
$parent_token = trim($data['parent_token']   ?? ($_SERVER['HTTP_X_PARENT_TOKEN'] ?? ''));
$threat_type  = trim($data['threat_type']    ?? '');
$severity     = $data['severity']            ?? '';
$context      = trim($data['context']        ?? '');

// ── Validate fields ───────────────────────────────────────────
$errors = [];
if ($child_id  <= 0)                                $errors[] = 'child_id must be a positive integer.';
if ($parent_token === '')                           $errors[] = 'parent_token is required.';
if ($threat_type === '')                            $errors[] = 'threat_type is required.';
if (strlen($threat_type) > 100)                    $errors[] = 'threat_type must be ≤ 100 characters.';
if (!in_array($severity, ['low','medium','high']))  $errors[] = 'severity must be low, medium, or high.';
if ($context === '')                                $errors[] = 'context is required.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}

// ── Verify: child exists AND belongs to a parent with this token ──
$stmt = $pdo->prepare("
    SELECT c.id AS child_id, p.id AS parent_id, p.monitor_token
    FROM users c
    JOIN users p ON p.id = c.parent_id
    WHERE c.id = ? AND c.role = 'child' AND p.role = 'parent'
");
$stmt->execute([$child_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Child not found or has no parent linked.']);
    exit;
}

if (empty($row['monitor_token']) || !hash_equals($row['monitor_token'], $parent_token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid parent_token.']);
    exit;
}

// ── Insert event ──────────────────────────────────────────────
try {
    $pdo->prepare('INSERT INTO threat_events (child_id, threat_type, severity, context) VALUES (?,?,?,?)')
        ->execute([$child_id, $threat_type, $severity, $context]);

    $new_id = (int)$pdo->lastInsertId();
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id'      => $new_id,
        'message' => "Threat event #{$new_id} created successfully.",
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
