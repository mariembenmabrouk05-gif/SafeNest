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
$children_count = count($children);

if ($selected_child !== 'tout' && in_array($selected_child, $all_child_ids)) {
    $in_clause = (string)$selected_child;
} else {
    $in_clause = empty($all_child_ids) ? "0" : implode(',', $all_child_ids);
}

// Stats: total incidents, high severity
$stats = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM threat_events WHERE child_id IN ($in_clause)) + 
        (SELECT COUNT(*) FROM alertes WHERE child_id IN ($in_clause)) AS total,
        
        (SELECT COUNT(*) FROM threat_events WHERE severity='high' AND child_id IN ($in_clause)) +
        (SELECT COUNT(*) FROM alertes WHERE child_id IN ($in_clause)) AS high
");
$stats->execute();
$stats = $stats->fetch();

// Sites visited today
$sites_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_visited
    FROM sites
    WHERE child_id IN ($in_clause) AND DATE(timestamp) = CURDATE()
");
$sites_stmt->execute();
$total_sites = (int)$sites_stmt->fetch()['total_visited'];

// Screen time pseudo calculation (assuming 5 minutes per site for demo purposes)
$screen_time_mins = $total_sites * 5;
$hours = floor($screen_time_mins / 60);
$mins = $screen_time_mins % 60;
$screen_time_display = "{$hours}h {$mins}m";
if($total_sites === 0) $screen_time_display = "0h 0m";

// Categories calculations
$cat_stmt = $pdo->prepare("
    SELECT category, COUNT(*) as cnt
    FROM sites
    WHERE child_id IN ($in_clause)
    GROUP BY category
");
$cat_stmt->execute();
$categories_data = $cat_stmt->fetchAll();
$default_cats = ['Éducation' => 0, 'Vidéos' => 0, 'Jeux' => 0, 'Réseaux sociaux' => 0];
$total_cat = array_sum(array_column($categories_data, 'cnt'));
if ($total_cat > 0) {
    foreach ($categories_data as $row) {
        $c = $row['category'];
        $pct = round(($row['cnt'] / $total_cat) * 100);
        if (array_key_exists($c, $default_cats)) {
            $default_cats[$c] = $pct;
        } else {
            $default_cats['Autres'] = ($default_cats['Autres'] ?? 0) + $pct;
        }
    }
}

// Recent threats (unified with alertes)
$recent_stmt = $pdo->prepare("
    (
        SELECT 'Threat' AS src, te.timestamp, te.severity, te.threat_type AS label, c.username AS enfant
        FROM threat_events te
        JOIN users c ON c.id = te.child_id
        WHERE c.parent_id = ?
    )
    UNION
    (
        SELECT 'Alerte' AS src, a.timestamp, 'high' AS severity, CONCAT('Site bloqué : ', s.url) AS label, c.username AS enfant
        FROM alertes a
        JOIN users c ON a.child_id = c.id
        JOIN sites s ON a.site_id = s.id
        WHERE c.parent_id = ?
    )
    ORDER BY timestamp DESC LIMIT 5
");
$recent_stmt->execute([$pid, $pid]);
$recent = $recent_stmt->fetchAll();

// Recent activity sites table
$recent_sites_stmt = $pdo->prepare("
    SELECT s.url, s.is_blocked
    FROM sites s
    WHERE s.child_id IN ($in_clause)
    ORDER BY s.timestamp DESC LIMIT 6
");
$recent_sites_stmt->execute();
$recent_sites = $recent_sites_stmt->fetchAll();

// Extract data for 7-day Line Chart Analytics
$line_dates = [];
$line_counts = [];
for($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $qty = $pdo->query("SELECT COUNT(*) FROM sites WHERE child_id IN ($in_clause) AND DATE(timestamp) = '$d'")->fetchColumn();
    $line_dates[] = date('d M', strtotime($d));
    $line_counts[] = (int)$qty;
}

// Extract data for 5-day Column Chart Profit
$col_labels = [];
$col_allowed = [];
$col_blocked = [];
for($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $qty_ok = $pdo->query("SELECT COUNT(*) FROM sites WHERE child_id IN ($in_clause) AND is_blocked=0 AND DATE(timestamp) = '$d'")->fetchColumn();
    $qty_ko = $pdo->query("SELECT COUNT(*) FROM sites WHERE child_id IN ($in_clause) AND is_blocked=1 AND DATE(timestamp) = '$d'")->fetchColumn();
    $col_labels[] = date('D', strtotime($d));
    $col_allowed[] = (int)$qty_ok;
    $col_blocked[] = (int)$qty_ko;
}

$title = 'Tableau de bord'; $base = ''; $page = 'accueil';
include 'templates/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Welcome back <?= htmlspecialchars($_SESSION['username'] ?? 'Parent') ?></h1>
        <p class="text-gray-500 text-sm mt-1">Surveillance comportementale haute-définition</p>
    </div>
    <div class="col-span-12 flex justify-end gap-3">
        <button onclick="openRulesModal()" class="bg-[#3CB4A8] border-none text-white px-5 py-2.5 rounded-lg font-semibold shadow hover:opacity-90 transition text-sm flex items-center gap-2">
            <span style="font-size:1.2rem;">🛡️👦</span> Règles enfant
        </button>
        <?php if (($_SESSION['username'] ?? '') === 'admin'): ?>
        <button id="testEmailBtn" onclick="testEmailIntegration()" class="bg-[#6A64B6] border-none text-white px-5 py-2.5 rounded-lg font-semibold shadow hover:opacity-90 transition text-sm">✉️ Filtre Test (Admin)</button>
        <?php endif; ?>
    </div>
</div>

<?php if ((int)$stats['high'] > 0): ?>
<div class="bg-red-50 text-red-800 border border-red-200 rounded-lg p-4 mb-6 flex items-center font-bold shadow-sm">
    <span class="mr-3 text-xl">⚠️</span> Interceptions non-autorisées enregistrées récemment 
    <a href="incidents.php" class="ml-auto underline font-semibold hover:text-red-900">Ouvrir registre</a>
</div>
<?php endif; ?>

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

<div class="grid grid-cols-12 gap-6 pb-10">
    <!-- ROW 1: STATS -->
    <div class="col-span-12 grid grid-cols-4 gap-6">
        <div class="bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
            <span class="text-xs font-bold text-[#8a92a6] uppercase tracking-wide">Trafic Inspecté</span>
            <div class="mt-2 text-2xl font-extrabold text-[#2c3240]"><?= $total_sites ?> <span class="text-sm font-medium text-emerald-500 ml-1">requêtes</span></div>
        </div>
        <div class="bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
            <span class="text-xs font-bold text-[#8a92a6] uppercase tracking-wide">Interventions Sécurité</span>
            <div class="mt-2 text-2xl font-extrabold text-[#2c3240]"><?= (int)$stats['total'] ?> <span class="text-sm font-medium text-[#ef4444] ml-1">blocages</span></div>
        </div>
        <div class="bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
            <span class="text-xs font-bold text-[#8a92a6] uppercase tracking-wide">Temps d'Écran Estimé</span>
            <div class="mt-2 text-2xl font-extrabold text-[#2c3240]"><?= $screen_time_display ?></div>
        </div>
        <div class="bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
            <span class="text-xs font-bold text-[#8a92a6] uppercase tracking-wide">Profils Sous Protection</span>
            <div class="mt-2 text-2xl font-extrabold text-[#2c3240]"><?= $children_count ?> <span class="text-sm font-medium text-emerald-500 ml-1">actifs</span></div>
        </div>
    </div>

    <!-- ROW 2 -->
    <!-- LINE CHART (8 cols) -->
    <div class="col-span-8 bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-base font-bold text-[#2c3240]">Activité Réseau Hebdomadaire</h2>
        </div>
        <div class="w-full h-[280px]">
            <canvas id="lineChart"></canvas>
        </div>
    </div>

    <!-- DOUGHNUT CHART (4 cols) -->
    <div class="col-span-4 bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm flex flex-col items-center justify-center">
        <h2 class="text-base font-bold text-[#2c3240] w-full text-left mb-4 border-b border-[#eaedf3] pb-2">Répartition Sources</h2>
        <div class="relative w-full max-w-[240px] aspect-square flex-1 flex items-center justify-center -mt-4">
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-4">
                <span class="text-3xl font-extrabold text-[#2c3240]"><?= $total_cat ?></span>
                <span class="text-[0.65rem] font-bold text-[#8a92a6] uppercase tracking-widest mt-0.5">Logs</span>
            </div>
            <canvas id="doughnutChart"></canvas>
        </div>
    </div>

    <!-- ROW 3 -->
    <!-- COLUMN CHART (6 cols) -->
    <div class="col-span-6 bg-white border border-[#eaedf3] rounded-xl p-5 shadow-sm">
        <h2 class="text-base font-bold text-[#2c3240] mb-4">Flux Rejeté vs Autorisé (7 derniers jours)</h2>
        <div class="w-full h-[300px]">
            <canvas id="colChart"></canvas>
        </div>
    </div>

    <!-- DENSE TABLE (6 cols) -->
    <div class="col-span-6 bg-white border border-[#eaedf3] rounded-xl shadow-sm flex flex-col">
        <div class="p-5 border-b border-[#eaedf3]">
            <h2 class="text-base font-bold text-[#2c3240]">Détail d'Activité Récente</h2>
        </div>
        <div class="flex-1 overflow-x-auto text-[0.8rem] h-[300px] overflow-y-auto">
            <?php if(empty($recent_sites)): ?>
                <p class="text-center text-gray-500 py-8 italic">Aucun trafic détecté récemment.</p>
            <?php else: ?>
            <table class="w-full text-left border-collapse">
                <tbody>
                    <?php foreach(array_slice($recent_sites, 0, 15) as $i => $s): ?>
                    <tr class="border-b border-[#eaedf3] last:border-0 hover:bg-[#f4f5f9] transition">
                        <td class="py-3 px-5 font-semibold text-[#2c3240] truncate max-w-[200px]" title="<?= htmlspecialchars($s['url']) ?>"><?= htmlspecialchars($s['url']) ?></td>
                        <td class="py-3 px-5 text-right w-[100px]">
                            <span class="inline-block px-2 py-0.5 rounded-md text-[0.65rem] font-bold uppercase tracking-wide <?= $s['is_blocked'] ? 'text-[#ef4444] bg-red-50' : 'text-[#3CB4A8] bg-[#e2f5f3]' ?>">
                                <?= $s['is_blocked'] ? 'Bloqué' : 'Permis' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // General Chart Overrides to match Ynex density
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#8a92a6';

    // 1. Line Chart (Revenue Analytics style)
    new Chart(document.getElementById('lineChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= $line_labels = json_encode($line_dates) ?>,
            datasets: [{
                label: 'Trafic Global',
                data: <?= $line_data = json_encode($line_counts) ?>,
                borderColor: '#6A64B6',
                backgroundColor: 'rgba(106, 100, 182, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { borderDash: [4,4], color: '#eaedf3' } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 2. Doughnut (Leads By Source style)
    new Chart(document.getElementById('doughnutChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($default_cats)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($default_cats)) ?>,
                backgroundColor: ['#3CB4A8', '#EBCC84', '#6A64B6', '#B7AED6'],
                borderWidth: 0,
                cutout: '80%',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { bottom: 20 } },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 6 } }
            }
        }
    });

    // 3. Column Chart (Profit Earned style)
    new Chart(document.getElementById('colChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($col_labels) ?>,
            datasets: [
                {
                    label: 'Validé',
                    data: <?= json_encode($col_allowed) ?>,
                    backgroundColor: '#3CB4A8',
                    borderRadius: 0,
                    barPercentage: 0.6
                },
                {
                    label: 'Bloqué',
                    data: <?= json_encode($col_blocked) ?>,
                    backgroundColor: '#0B1B44',
                    borderRadius: 0,
                    barPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false } },
                y: { display: false, beginAtZero: true } // hide Y axis fully like the image
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // API Test Button & AI Hook
    function testEmailIntegration() {
        const btn = document.getElementById('testEmailBtn');
        btn.innerHTML = 'Envoi réseau en cours...';
        btn.disabled = true;
        fetch('api/add_site.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                child_id: <?= !empty($children) ? $children[0]['id'] : 0 ?>,
                url: 'test-alerte-ynex.com',
                category: 'Test Manuel',
                is_blocked: 1
            })
        }).then(res => res.json())
        .then(data => { alert('Alerte envoyée!'); window.location.reload(); })
        .catch(err => { alert('Erreur réseau.'); btn.innerHTML = '✉️ Filtre Test (Admin)'; btn.disabled = false; });
    }
</script>

<!-- Rules Modal -->
<div id="rulesModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); z-index:9999; justify-content:center; align-items:center; padding: 1rem;">
    <div class="bg-white p-8 rounded-xl shadow-2xl relative w-full max-w-[650px] text-[#2c3240] max-h-[90vh] flex flex-col">
        <button onclick="closeRulesModal()" class="absolute flex justify-center items-center w-8 h-8 rounded-full top-4 right-4 bg-gray-100 text-gray-500 hover:bg-gray-200 transition">&times;</button>
        <h2 class="text-xl font-bold mb-2 flex items-center gap-2">🛡️ Règles de Blocage</h2>
        <p class="text-xs text-gray-500 mb-6">Gérez la liste noire des sites non autorisés. Ces sites seront automatiquement bloqués indépendamment de leur catégorie.</p>
        
        <div class="flex gap-2 mb-6">
            <input type="text" id="newRuleUrl" placeholder="Ex: facebook.com ou tiktok.com" class="flex-1 py-2 px-3 bg-gray-50 border border-gray-200 rounded text-sm outline-none focus:border-[#3CB4A8] focus:ring-1 focus:ring-[#3CB4A8]">
            <select id="newRuleChild" class="py-2 px-3 bg-gray-50 border border-gray-200 rounded text-sm outline-none">
                <option value="tout">Tous les enfants</option>
                <?php foreach($children as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="addRule()" class="bg-[#EBCC84] hover:bg-[#d6b772] text-[#0B1B44] px-4 py-2 rounded font-bold transition">Ajouter</button>
        </div>

        <div class="flex-1 overflow-y-auto border border-gray-100 rounded-lg min-h-[250px]">
            <table class="w-full text-left border-collapse text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="py-2 px-4 font-bold text-gray-600">URL Bloquée</th>
                        <th class="py-2 px-4 font-bold text-gray-600">Cible</th>
                        <th class="py-2 px-4 font-bold text-gray-600 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="rulesTableBody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function openRulesModal() {
        document.getElementById('rulesModal').style.display = 'flex';
        loadRules();
    }
    
    function closeRulesModal() {
        document.getElementById('rulesModal').style.display = 'none';
    }

    function loadRules() {
        fetch('api/manage_rules.php')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('rulesTableBody');
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="py-8 text-center text-gray-500 italic">Aucune règle définie.</td></tr>';
                    return;
                }
                
                const childrenMap = {
                    <?php foreach($children as $c): ?>
                    "<?= $c['id'] ?>": "<?= htmlspecialchars($c['username']) ?>",
                    <?php endforeach; ?>
                };

                data.forEach(rule => {
                    const target = rule.child_id ? (childrenMap[rule.child_id] || 'Inconnu') : 'Tous';
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-100 last:border-0 hover:bg-gray-50 transition';
                    tr.innerHTML = `
                        <td class="py-3 px-4 text-red-500 font-semibold truncate max-w-[250px]" title="${rule.url}">${rule.url}</td>
                        <td class="py-3 px-4 text-gray-600">${target}</td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="deleteRule(${rule.id})" class="text-red-500 hover:text-red-700 font-bold px-3 py-1 rounded bg-red-50 hover:bg-red-100 transition">Supprimer</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

    function addRule() {
        const url = document.getElementById('newRuleUrl').value.trim();
        const childId = document.getElementById('newRuleChild').value;
        if (!url) return alert('Veuillez entrer une URL');

        fetch('api/manage_rules.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url, child_id: childId })
        }).then(res => res.json()).then(res => {
            if (res.success) {
                document.getElementById('newRuleUrl').value = '';
                loadRules();
            } else {
                alert(res.error || 'Erreur');
            }
        });
    }

    function deleteRule(id) {
        if (!confirm('Supprimer cette règle ?')) return;
        fetch('api/manage_rules.php?id=' + id, { method: 'DELETE' })
            .then(res => res.json())
            .then(res => {
                if(res.success) loadRules();
                else alert(res.error || 'Erreur');
            });
    }
</script>

<?php include 'templates/footer.php'; ?>
