<?php
/**
 * GearGuard CMMS - Equipment List
 * Shows equipment with smart buttons to view maintenance requests
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'models/Equipment.php';

requireAuth();

$equipmentModel = new Equipment();
$equipment = $equipmentModel->getAll();
$currentUser = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - GearGuard CMMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .topbar {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .topbar h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .container {
            padding: 2rem;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .equipment-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .equipment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .equipment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .equipment-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-scrapped {
            background: #f8d7da;
            color: #721c24;
        }
        
        .equipment-details {
            margin: 1rem 0;
        }
        
        .detail-row {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
            width: 140px;
        }
        
        .detail-value {
            color: #333;
            flex: 1;
        }
        
        .smart-button-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #e9ecef;
        }
        
        .smart-button {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #007bff;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .smart-button:hover {
            background: #0056b3;
        }
        
        .smart-button-label {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .smart-button-badge {
            background: rgba(255,255,255,0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>📦 Equipment Management</h1>
        <a href="kanban.php" class="btn btn-secondary">← Back to Kanban</a>
    </div>
    
    <div class="container">
        <div class="actions">
            <?php if (hasRole(['admin', 'manager'])): ?>
            <a href="equipment-form.php" class="btn btn-primary">+ Add Equipment</a>
            <?php endif; ?>
            <a href="kanban.php" class="btn btn-secondary">View All Requests</a>
        </div>
        
        <?php if (empty($equipment)): ?>
        <div class="empty-state">
            <h3>No Equipment Found</h3>
            <p>Start by adding your first piece of equipment.</p>
        </div>
        <?php else: ?>
        <div class="equipment-grid">
            <?php foreach ($equipment as $eq): 
                $openRequests = $equipmentModel->getOpenRequestsCount($eq['id']);
            ?>
            <div class="equipment-card">
                <div class="equipment-header">
                    <div class="equipment-name"><?= htmlspecialchars($eq['name']) ?></div>
                    <span class="status-badge status-<?= $eq['status'] ?>">
                        <?= strtoupper($eq['status']) ?>
                    </span>
                </div>
                
                <div class="equipment-details">
                    <div class="detail-row">
                        <div class="detail-label">Serial Number:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['serial_number']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['category']) ?></div>
                    </div>
                    <?php if ($eq['department_name']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Department:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['department_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($eq['assigned_employee_name']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Assigned To:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['assigned_employee_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <div class="detail-label">Maintenance Team:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['team_name']) ?></div>
                    </div>
                    <?php if ($eq['location']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value"><?= htmlspecialchars($eq['location']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="smart-button-section">
                    <a href="equipment-requests.php?id=<?= $eq['id'] ?>" class="smart-button">
                        <span class="smart-button-label">
                            🔧 Maintenance Requests
                        </span>
                        <span class="smart-button-badge">
                            <?= $openRequests ?> Open
                        </span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>