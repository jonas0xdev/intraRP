<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

// Hole alle Fahrzeuge aus der Datenbank (letzte 24 Stunden)
try {
    $stmt = $pdo->query("
        SELECT * FROM emd_vehicles 
        WHERE last_update > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY last_update DESC
    ");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehicles = [];
    $error = "Datenbankfehler: " . $e->getMessage();
}

// Status-Labels
$statusLabels = [
    1 => 'Einsatzbereit',
    2 => 'Ausgerückt',
    3 => 'An Einsatzstelle',
    4 => 'Patient aufgenommen',
    5 => 'Auf Krankenhaus',
    6 => 'Ankunft Krankenhaus'
];

// Statistiken berechnen
$stats = [
    'total' => count($vehicles),
    'inDispatch' => count(array_filter($vehicles, fn($v) => $v['dispatch_id'] > 0)),
    'available' => count(array_filter($vehicles, fn($v) => $v['status'] == 1)),
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Dispatch Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            color: #667eea;
            font-size: 48px;
            font-weight: bold;
        }

        .vehicles-grid {
            display: grid;
            gap: 20px;
        }

        .vehicle-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .vehicle-card.in-dispatch {
            border-left: 5px solid #e74c3c;
        }

        .vehicle-card.available {
            border-left: 5px solid #2ecc71;
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .vehicle-callsign {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .vehicle-type {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .vehicle-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-1 {
            background: #d4edda;
            color: #155724;
        }

        .status-2 {
            background: #fff3cd;
            color: #856404;
        }

        .status-3 {
            background: #f8d7da;
            color: #721c24;
        }

        .status-4 {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-5 {
            background: #cce5ff;
            color: #004085;
        }

        .status-6 {
            background: #d6d8db;
            color: #383d41;
        }

        .dispatch-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }

        .dispatch-badge::before {
            content: "🚨";
            font-size: 16px;
        }

        .timestamp {
            text-align: center;
            color: white;
            margin-top: 20px;
            font-size: 14px;
            opacity: 0.9;
        }

        .no-data {
            background: white;
            padding: 60px;
            border-radius: 15px;
            text-align: center;
            color: #999;
            font-size: 18px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .refresh-btn:hover {
            background: #667eea;
            color: white;
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .vehicle-info {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚑 Emergency Dispatch Monitor</h1>
            <p>Live-Übersicht aller Einsatzfahrzeuge (letzte 24 Stunden)</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error">
                <strong>Fehler:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Gesamt</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Im Einsatz</h3>
                <div class="number" style="color: #e74c3c;"><?php echo $stats['inDispatch']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Einsatzbereit</h3>
                <div class="number" style="color: #2ecc71;"><?php echo $stats['available']; ?></div>
            </div>
        </div>

        <div class="vehicles-grid">
            <?php if (empty($vehicles)): ?>
                <div class="no-data">
                    📭 Keine Fahrzeugdaten vorhanden
                </div>
            <?php else: ?>
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="vehicle-card <?php echo $vehicle['dispatch_id'] > 0 ? 'in-dispatch' : ($vehicle['status'] == 1 ? 'available' : ''); ?>">
                        <div class="vehicle-header">
                            <div class="vehicle-callsign">
                                <?php echo htmlspecialchars($vehicle['callsign']); ?>
                            </div>
                            <div class="vehicle-type">
                                <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                            </div>
                        </div>

                        <div class="vehicle-info">
                            <div class="info-item">
                                <span class="info-label">Spieler ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['player_id']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Job</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['job']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Funkart</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['funkart']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Funkkanal</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['funkkanal']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Wache</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['department']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Leitstelle</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['leitstelle']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Fahrzeug ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($vehicle['vehicle_id']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Letzte Aktualisierung</span>
                                <span class="info-value"><?php echo date('H:i:s', strtotime($vehicle['last_update'])); ?></span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                            <span class="status-badge status-<?php echo $vehicle['status']; ?>">
                                <?php echo $statusLabels[$vehicle['status']] ?? 'Unbekannt'; ?>
                            </span>

                            <?php if ($vehicle['dispatch_id'] > 0): ?>
                                <span class="dispatch-badge">
                                    Einsatz #<?php echo htmlspecialchars($vehicle['dispatch_id']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="timestamp">
            Zuletzt aktualisiert: <?php echo date('d.m.Y H:i:s'); ?>
        </div>
    </div>

    <button class="refresh-btn" onclick="location.reload()">
        🔄 Aktualisieren
    </button>
</body>

</html>