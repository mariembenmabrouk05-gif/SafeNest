<?php
require 'config.php';
require_role('parent');

$pid = (int)$_SESSION['user_id'];

$selected_child = isset($_GET['child_id']) && $_GET['child_id'] !== 'tout' ? (int)$_GET['child_id'] : 'tout';

// Get children IDs
$children_stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE parent_id = ? AND role = 'child'");
$children_stmt->execute([$pid]);
$children = $children_stmt->fetchAll();
$all_child_ids = array_column($children, 'id');

if ($selected_child !== 'tout' && in_array($selected_child, $all_child_ids)) {
    $in_clause = (string)$selected_child;
} else {
    $in_clause = empty($all_child_ids) ? "0" : implode(',', $all_child_ids);
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'], $_POST['status'], $_POST['event_type'])) {
    $allowed = ['new', 'reviewed', 'resolved'];
    if (in_array($_POST['status'], $allowed)) {
        if ($_POST['event_type'] === 'threat') {
            $pdo->prepare("
                UPDATE threat_events te
                JOIN users c ON c.id = te.child_id AND c.parent_id = ?
                SET te.status = ? WHERE te.id = ?
            ")->execute([$pid, $_POST['status'], (int)$_POST['event_id']]);
        } elseif ($_POST['event_type'] === 'alerte') {
            $pdo->prepare("
                UPDATE alertes a
                JOIN users c ON c.id = a.child_id AND c.parent_id = ?
                SET a.status = ? WHERE a.id = ?
            ")->execute([$pid, $_POST['status'], (int)$_POST['event_id']]);
        }
    }
    header('Location: incidents.php' . ($selected_child !== 'tout' ? "?child_id=$selected_child" : ""));
    exit;
}

$stmt = $pdo->prepare("
    SELECT te.id, 'threat' AS src, te.threat_type, te.severity, te.context, te.status, te.timestamp, c.username as enfant
    FROM threat_events te
    JOIN users c ON c.id = te.child_id AND c.parent_id = ?
    WHERE c.id IN ($in_clause)
    
    UNION ALL
    
    SELECT a.id, 'alerte' AS src, 'Site Bloqué' AS threat_type, 'high' AS severity, CONCAT('URL: ', s.url) AS context, a.status, a.timestamp, c.username as enfant
    FROM alertes a
    JOIN users c ON c.id = a.child_id AND c.parent_id = ?
    JOIN sites s ON a.site_id = s.id
    WHERE c.id IN ($in_clause)
    
    ORDER BY timestamp DESC
");
$stmt->execute([$pid, $pid]);
$events = $stmt->fetchAll();

$title = 'Incidents'; $base = ''; $page = 'incidents';
include 'templates/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Incidents</h1>
        <p class="text-gray-500 text-sm mt-1">Tous les incidents détectés. Modifiez le statut directement depuis le tableau.</p>
    </div>
</div>

<div class="flex flex-wrap gap-3 mb-6">
    <a href="?child_id=tout" class="flex items-center gap-2 px-4 py-2 rounded-full font-semibold transition <?= $selected_child === 'tout' ? 'bg-[#0B1B44] text-white shadow-md' : 'bg-white text-[#8a92a6] border border-[#eaedf3] hover:bg-gray-50' ?>">
        <span style="font-size:1.1rem;">🌐</span> Tout
    </a>
    <?php foreach($children as $c): ?>
        <a href="?child_id=<?= $c['id'] ?>" class="flex items-center gap-3 px-4 py-1.5 rounded-full font-semibold transition <?= $selected_child === (int)$c['id'] ? 'bg-[#0B1B44] text-white shadow-md' : 'bg-white text-[#8a92a6] border border-[#eaedf3] hover:bg-gray-50' ?>">
            <img src="image/<?= htmlspecialchars($c['avatar'] ?? 'avatar1.png') ?>" class="w-7 h-7 rounded-full object-cover border-2 <?= $selected_child === (int)$c['id'] ? 'border-white/20' : 'border-[#eaedf3]' ?>">
            <?= htmlspecialchars($c['username']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <?php if (empty($events)): ?>
        <p style="color:var(--muted);text-align:center;padding:3rem;font-size:.875rem;">Aucun incident enregistré.</p>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>Enfant</th>
            <th>Type de menace</th>
            <th>Sévérité</th>
            <th>Contexte</th>
            <th>Statut</th>
            <th>Date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($e['enfant']) ?></td>
            <td>
                <?php if ($e['src'] === 'alerte'): ?>
                    <span title="Alerte Système" style="margin-right:5px;">🛡️</span>
                <?php endif; ?>
                <?= htmlspecialchars($e['threat_type']) ?>
            </td>
            <td><span class="badge badge-<?= $e['severity'] ?>"><?= $e['severity'] ?></span></td>
            <td style="color:var(--muted);max-width:260px;font-size:.82rem;">
                <?= htmlspecialchars(mb_strimwidth($e['context'], 0, 80, '…')) ?>
            </td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                    <input type="hidden" name="event_type" value="<?= $e['src'] ?>">
                    <select name="status" class="status-select" onchange="this.form.submit()">
                        <option value="new"      <?= $e['status']==='new'      ? 'selected':'' ?>>Nouveau</option>
                        <option value="reviewed" <?= $e['status']==='reviewed' ? 'selected':'' ?>>Examiné</option>
                        <option value="resolved" <?= $e['status']==='resolved' ? 'selected':'' ?>>Résolu</option>
                    </select>
                </form>
            </td>
            <td style="color:var(--muted);font-size:.8rem;white-space:nowrap;">
                <?= date('d/m/Y H:i', strtotime($e['timestamp'])) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>
