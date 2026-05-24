<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../PHPMailer-PHPMailer-3cd2a2a/src/Exception.php';
require __DIR__ . '/../PHPMailer-PHPMailer-3cd2a2a/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-PHPMailer-3cd2a2a/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/auto_category.php';

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$child_id   = (int)($data['child_id'] ?? 0);
$url        = trim($data['url'] ?? '');
$title      = trim($data['title'] ?? '');
$category_input = trim($data['category'] ?? '');
$category   = (!empty($category_input) && $category_input !== 'Divers') ? $category_input : autoCategorizeUrl($url, $title);
$is_blocked = (int)($data['is_blocked'] ?? 0);

if ($child_id <= 0 || empty($url)) {
    http_response_code(422);
    echo json_encode(['error' => 'child_id and url are required']);
    exit;
}

// Check child exists
$stmt = $pdo->prepare("SELECT username, parent_id FROM users WHERE id = ? AND role = 'child'");
$stmt->execute([$child_id]);
$child = $stmt->fetch();

if (!$child) {
    http_response_code(404);
    echo json_encode(['error' => 'Child not found']);
    exit;
}

$child_name = $child['username'];
$parent_id = $child['parent_id'];

// Check custom rules
$stmt = $pdo->prepare("SELECT id FROM custom_blocked_sites WHERE parent_id = ? AND (child_id = ? OR child_id IS NULL) AND ? LIKE CONCAT('%', url, '%')");
$stmt->execute([$parent_id, $child_id, $url]);
$custom_rule = $stmt->fetch();

if ($custom_rule) {
    $is_blocked = 1;
} elseif ($is_blocked === 0 && in_array($category, ['Adulte', 'Violence', 'Jeux d\'argent', 'Réseaux sociaux'])) {
    $is_blocked = 1;
}

// Insert site
$stmt = $pdo->prepare("INSERT INTO sites (child_id, url, category, is_blocked) VALUES (?, ?, ?, ?)");
$stmt->execute([$child_id, $url, $category, $is_blocked]);
$site_id = $pdo->lastInsertId();

if ($is_blocked === 1) {
    // 1. Insert into ALERTES
    $pdo->prepare("INSERT INTO alertes (child_id, site_id, status) VALUES (?, ?, 'new')")
        ->execute([$child_id, $site_id]);

    // 2. SEND EMAIL VIA PHPMAILER
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // User provided credentials:
        $mail->Username   = 'veilleenfantine@gmail.com';
        $mail->Password   = 'rbfa rmaa nvkt jldq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->CharSet    = 'UTF-8';

        // Recup Email Parent
        $p_email = $pdo->prepare("SELECT email FROM users WHERE id = (SELECT parent_id FROM users WHERE id = ?)");
        $p_email->execute([$child_id]);
        $parent_email = $p_email->fetchColumn();

        // Recipients
        $mail->setFrom('veilleshield@gmail.com', 'Alerte Veille Enfantine');
        if (!empty($parent_email)) {
            $mail->addAddress($parent_email);
        } else {
            // Failsafe
            $mail->addAddress('mariembenmabrouk05@gmail.com'); 
        }

        $mail->isHTML(true);
        $mail->Subject = "⚠️ ALERTE : Tentative d'accès bloquée pour " . $child_name;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; background-color: #fce8e8; padding: 20px; border-radius: 10px; border: 1px solid #fca5a5; max-width: 600px;'>
                <h2 style='color: #dc2626; margin-top: 0;'>Alerte de Sécurité - SafeNest</h2>
                <p style='color: #4b5563; font-size: 16px;'>Bonjour,</p>
                <p style='color: #4b5563; font-size: 16px;'>Notre système a intercepté et bloqué une tentative d'accès à un site réseau non sécurisé ou interdit :</p>
                
                <ul style='background: white; padding: 15px; border-radius: 5px; font-size: 15px; color: #1f2937;'>
                    <li style='margin-bottom: 5px;'><strong>Enfant concerné :</strong> {$child_name}</li>
                    <li style='margin-bottom: 5px;'><strong>Site intercepté :</strong> <span style='color: #ef4444; font-weight: bold;'>{$url}</span></li>
                    <li style='margin-bottom: 5px;'><strong>Catégorie détectée :</strong> {$category}</li>
                    <li style='margin-bottom: 5px;'><strong>Heure :</strong> " . date('d/m/Y H:i:s') . "</li>
                </ul>
                
                <p style='color: #4b5563; font-size: 15px;'>Veuillez consulter votre <a style='color: #2563eb; font-weight: bold;' href='http://localhost/SafeNestt/SafeNest/parent_dashboard.php'>Tableau de bord SafeNest</a> pour examiner ce comportement en détail et échanger avec votre enfant.</p>
                
                <hr style='border: 0; border-top: 1px solid #fecaca; margin: 20px 0;'>
                <p style='color: #9ca3af; font-size: 12px; text-align: center;'>SafeNest - Dédié à la sécurité numérique de la jeunesse.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Protect Mailer Error: {$mail->ErrorInfo}");
    }
}

echo json_encode(['success' => true, 'message' => 'Site ajouté et analysé avec succès']);
