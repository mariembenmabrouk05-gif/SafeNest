<?php
require 'config.php';
require_role('parent');

$pid = (int)$_SESSION['user_id'];

$selected_child = isset($_GET['child_id']) && $_GET['child_id'] !== 'tout' ? (int)$_GET['child_id'] : 'tout';

$children_stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE parent_id = ? AND role = 'child'");
$children_stmt->execute([$pid]);
$children = $children_stmt->fetchAll();
$all_child_ids = array_column($children, 'id');

$child_sql = "";
if ($selected_child !== 'tout' && in_array($selected_child, $all_child_ids)) {
    $child_sql = " AND c.id = " . (int)$selected_child;
}

// Get all sites for all children
$sites_stmt = $pdo->prepare("
    SELECT s.url, s.category, s.is_blocked, s.timestamp, c.username AS enfant
    FROM sites s
    JOIN users c ON s.child_id = c.id
    WHERE c.parent_id = ?$child_sql
    ORDER BY s.timestamp ASC
");
$sites_stmt->execute([$pid]);
$all_sites = $sites_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent threats/events for detailed daily list
$recent_stmt = $pdo->prepare("
    (
        SELECT 'Threat' AS src, te.timestamp, te.severity, te.threat_type AS label, c.username AS enfant
        FROM threat_events te
        JOIN users c ON c.id = te.child_id
        WHERE c.parent_id = ?$child_sql
    )
    UNION
    (
        SELECT 'Alerte' AS src, a.timestamp, 'high' AS severity, CONCAT('Site bloqué : ', s.url) AS label, c.username AS enfant
        FROM alertes a
        JOIN users c ON a.child_id = c.id
        JOIN sites s ON a.site_id = s.id
        WHERE c.parent_id = ?$child_sql
    )
    ORDER BY timestamp DESC LIMIT 20
");
$recent_stmt->execute([$pid, $pid]);
$recent = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Activités'; $page = 'activites';
include 'templates/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Activité & Synthèse</h1>
        <p class="text-gray-500 text-sm mt-1">Intelligence Pédagogique et historique de navigation détaillé</p>
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

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- IA SYNTHESIS BLOCK -->
    <div class="bg-white border border-[#eaedf3] rounded-xl py-6 px-6 shadow-sm flex flex-col relative overflow-hidden">
        <div class="border-b border-[#eaedf3] pb-4 mb-4 flex items-center justify-between">
            <h2 class="text-base font-bold text-[#2c3240]">Synthèse Éducative (Moteur Local)</h2>
            <span class="bg-[#EBCC84]/20 text-[#0B1B44] text-[0.6rem] px-2 py-0.5 rounded font-bold uppercase tracking-wide">Pédagogique</span>
        </div>
        <div id="ai-summary-text" class="text-[0.75rem] text-[#2c3240] font-medium leading-relaxed bg-[#f4f5f9] p-5 rounded-lg shadow-inner flex-1">
            <div class="flex items-center justify-center h-full text-gray-400 italic">Analyse des données et génération de la synthèse...</div>
        </div>
    </div>

    <!-- TIMELINE LIST -->
    <div class="bg-white border border-[#eaedf3] rounded-xl py-6 shadow-sm flex flex-col relative overflow-hidden h-[350px]">
        <div class="px-6 border-b border-[#eaedf3] pb-4 mb-4">
            <h2 class="text-base font-bold text-[#2c3240]">Historique Détaillé (Incidents & Blockings)</h2>
        </div>
        <div class="flex-1 overflow-y-auto px-6 pb-2">
            <?php if(empty($recent)): ?>
                <p class="text-xs text-center text-gray-400 italic mt-8">Aucun événement majeur détecté</p>
            <?php else: ?>
                <div class="flex flex-col relative w-full mb-4">
                    <?php foreach($recent as $idx => $r): ?>
                        <?php 
                            $dateStr = date('M d, H:i', strtotime($r['timestamp'])); 
                            $nodeColor = $r['severity'] === 'high' ? 'bg-[#ef4444]' : 'bg-[#EBCC84]';
                            $showLine = $idx !== count($recent) - 1;
                        ?>
                        <div class="relative pl-6 py-2">
                            <div class="absolute left-[-4px] top-4 block w-2 h-2 rounded-full border <?= $nodeColor ?> <?= $nodeColor ?> z-10 box-content outline outline-[4px] outline-white"></div>
                            <?php if($showLine): ?>
                                <div class="absolute left-[-2px] top-6 bottom-[-20px] w-0 border-l-2 border-dashed border-[#eaedf3] z-0"></div>
                            <?php endif; ?>
                            <span class="block text-[0.65rem] font-bold text-[#8a92a6] uppercase tracking-wide mb-0.5"><?= $dateStr ?></span>
                            <div class="text-[0.75rem] text-[#2c3240] font-semibold leading-snug">
                                <span class="text-[#6A64B6]"><?= htmlspecialchars($r['enfant']) ?></span>: <?= htmlspecialchars($r['label']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
    <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
        <h2 class="text-lg font-bold text-gray-800">Fréquence de Navigation Globale</h2>
        <div class="flex bg-gray-100 rounded-lg p-1" id="time-toggles">
            <button onclick="updateChart('hours')" class="time-btn bg-white shadow-sm text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition">Heures</button>
            <button onclick="updateChart('days')" class="time-btn text-gray-500 hover:text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition">Jours</button>
            <button onclick="updateChart('months')" class="time-btn text-gray-500 hover:text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition">Mois</button>
            <button onclick="updateChart('years')" class="time-btn text-gray-500 hover:text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition">Années</button>
        </div>
    </div>
    
    <div class="w-full h-[400px]">
        <canvas id="activityChart"></canvas>
    </div>
</div>

<script>
    const sites = <?= json_encode($all_sites) ?>;
    let chartInstance = null;

    function groupData(timeframe) {
        const map = new Map();
        
        sites.forEach(site => {
            const d = new Date(site.timestamp);
            let key = '';
            
            if(timeframe === 'hours') {
                key = d.toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', hour:'2-digit' }) + 'h';
            } else if(timeframe === 'days') {
                key = d.toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric' });
            } else if(timeframe === 'months') {
                key = d.toLocaleString('fr-FR', { month:'short', year:'numeric' });
            } else if(timeframe === 'years') {
                key = d.getFullYear().toString();
            }
            
            if(!map.has(key)) map.set(key, { allowed: 0, blocked: 0 });
            
            const entry = map.get(key);
            if(parseInt(site.is_blocked) === 1) entry.blocked++;
            else entry.allowed++;
        });

        return {
            labels: Array.from(map.keys()),
            allowed: Array.from(map.values()).map(e => e.allowed),
            blocked: Array.from(map.values()).map(e => e.blocked)
        };
    }

    function updateChart(timeframe) {
        // Update Buttons Styling
        document.querySelectorAll('.time-btn').forEach(btn => {
            btn.className = "time-btn text-gray-500 hover:text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition";
        });
        const activeBtn = event ? event.currentTarget : document.querySelector('.time-btn');
        activeBtn.className = "time-btn bg-white shadow-sm text-gray-800 font-medium px-4 py-1.5 rounded-md text-sm transition";

        // Generate Data
        const parsedData = groupData(timeframe);

        if (chartInstance) chartInstance.destroy();

        const ctx = document.getElementById('activityChart').getContext('2d');
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: parsedData.labels,
                datasets: [
                    {
                        label: 'Autorisé',
                        data: parsedData.allowed,
                        backgroundColor: '#3CB4A8',
                        borderRadius: 0,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Bloqué',
                        data: parsedData.blocked,
                        backgroundColor: '#0B1B44',
                        borderRadius: 0,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { borderDash: [4, 4] } }
                },
                plugins: {
                    legend: { position: 'top', labels: { font: { family: "'Poppins', sans-serif" }, usePointStyle: true } }
                }
            }
        });
    }

    updateChart('hours');

    // Fetch AI Summary
    fetch('api/ai_summary.php')
        .then(response => response.json())
        .then(data => {
            const el = document.getElementById('ai-summary-text');
            if(data.summary) { el.innerHTML = data.summary.replace(/\n/g, '<br>'); }
            else { el.innerText = data.error || 'Indisponible.'; }
        });
</script>

<?php include 'templates/footer.php'; ?>
