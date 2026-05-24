<?php
require '../config.php';
header('Content-Type: application/json');
if (!logged_in() || $_SESSION['role'] !== 'parent') {
    die(json_encode(['error' => 'Unauthorized']));
}

$pid = (int)$_SESSION['user_id'];

// Get all sites mapped to parent profile for today
$in_clause = "SELECT id FROM users WHERE parent_id = $pid AND role = 'child'";
$stmt = $pdo->prepare("
    SELECT s.category, s.is_blocked, s.timestamp 
    FROM sites s
    WHERE s.child_id IN ($in_clause) AND DATE(s.timestamp) = CURDATE()
");
$stmt->execute();
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sites)) {
    echo json_encode(['summary' => "Vos enfants n'ont enregistré aucune activité réseau détectable aujourd'hui.", 'is_fallback' => true]);
    exit;
}

$total = count($sites);
$blocked_count = 0;
$late_night = 0;
$categories = [];

foreach ($sites as $site) {
    if ($site['is_blocked']) $blocked_count++;
    
    $cat = $site['category'] ?: 'Autres';
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    
    $hour = (int)date('H', strtotime($site['timestamp']));
    if ($hour >= 23 || $hour <= 5) $late_night++;
}

arsort($categories);
$top_category = array_key_first($categories);
$top_category_pct = round(($categories[$top_category] / $total) * 100);

// Build smart algorithmic phrase
$summary = "✨ Analyse Hors-Ligne : ";

if ($total < 10) {
    $summary .= "Activité de navigation très modérée aujourd'hui. ";
} elseif ($total > 50) {
    $summary .= "Temps d'écran et volume de navigation intenses. ";
} else {
    $summary .= "Rythme de navigation global régulier. ";
}

if ($top_category_pct >= 40) {
    if (strtolower($top_category) === 'éducation') {
        $summary .= "Très forte concentration sur des contenus studieux et éducatifs. ";
    } else {
        $summary .= "L'activité a été largement dominée (à $top_category_pct%) par la catégorie '$top_category'. ";
    }
} else {
    $summary .= "Les intérêts consultés ont été très éclectiques et variés sur la toile. ";
}

if ($blocked_count > 0) {
    $summary .= "Attention cependant, $blocked_count tentative(s) d'accès à des sites restreints ont été interceptées ! ";
} else {
    $summary .= "La navigation est restée parfaitement sécurisée sans tentatives transgressives. ";
}

if ($late_night > 0) {
    $summary .= "Attention à l'exposition aux écrans : des connexions très tardives ont été détectées.";
}

echo json_encode(['summary' => trim($summary), 'is_fallback' => false]);
?>
