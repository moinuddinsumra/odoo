<?php
/**
 * GearGuard CMMS - Dashboard
 * Overview and statistics for all users
 */

require_once 'C:\xampp\htdocs\gearguard\config\database.php';
require_once 'C:\xampp\htdocs\gearguard\includes\auth.php';

requireAuth();

$currentUser = getCurrentUser();
$db = getDB();

// Get statistics
$stats = [
    'total_equipment' => 0,
    'active_equipment' => 0,
    'total_requests' => 0,
    'new_requests' => 0,
    'in_progress' => 0,
    'repaired_today' => 0,
    'overdue_requests' => 0,
    'my_assigned' => 0
];

// Equipment stats
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
    FROM equipment");
$equipStats = $stmt->fetch();
$stats['total_equipment'] = $equipStats['total'];
$stats['active_equipment'] = $equipStats['active'];

// Request stats
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'repaired' AND DATE(completed_date) = CURDATE() THEN 1 ELSE 0 END) as repaired_today,
    SUM(CASE WHEN scheduled_date < CURDATE() AND status NOT IN ('repaired', 'scrap') THEN 1 ELSE 0 END) as overdue
    FROM maintenance_requests");
$reqStats = $stmt->fetch();
$stats['total_requests'] = $reqStats['total'];
$stats['new_requests'] = $reqStats['new_count'];
$stats['in_progress'] = $reqStats['in_progress'];
$stats['repaired_today'] = $reqStats['repaired_today'];
$stats['overdue_requests'] = $reqStats['overdue'];

// My assigned requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests 
    WHERE assigned_to = ? AND status NOT IN ('repaired', 'scrap')");
$stmt->execute([$currentUser['id']]);
$myStats = $stmt->fetch();
$stats['my_assigned'] = $myStats['count'];

// Recent requests
$stmt = $db->query("
    SELECT 
        mr.id,
        mr.request_number,
        mr.subject,
        mr.status,
        mr.priority,
        mr.created_at,
        e.name as equipment_name,
        u.full_name as requester_name
    FROM maintenance_requests mr
    INNER JOIN equipment e ON mr.equipment_id = e.id
    INNER JOIN users u ON mr.requested_by = u.id
    ORDER BY mr.created_at DESC
    LIMIT 10
");
$recentRequests = $stmt->fetchAll();

// Urgent requests (high/critical priority + not completed)
$stmt = $db->query("
    SELECT 
        mr.id,
        mr.request_number,
        mr.subject,
        mr.status,
        mr.priority,
        mr.scheduled_date,
        e.name as equipment_name,
        mt.name as team_name,
        tech.full_name as technician_name
    FROM maintenance_requests mr
    INNER JOIN equipment e ON mr.equipment_id = e.id
    INNER JOIN maintenance_teams mt ON mr.maintenance_team_id = mt.id
    LEFT JOIN users tech ON mr.assigned_to = tech.id
    WHERE mr.priority IN ('high', 'critical') 
    AND mr.status NOT IN ('repaired', 'scrap')
    ORDER BY 
        CASE mr.priority 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
        END,
        mr.scheduled_date ASC
    LIMIT 5
");
$urgentRequests = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GearGuard CMMS</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .welcome-section h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.danger { border-left: 4px solid #dc3545; }
        .stat-card.info { border-left: 4px solid #17a2b8; }
        
        .quick-actions {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .quick-actions h3 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
            font-size: 1.3rem;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .request-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .request-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .request-item:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .request-number {
            font-weight: 600;
            color: #007bff;
        }
        
        .priority-badge,
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-critical { background: #dc3545; color: white; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-medium { background: #ffc107; color: #333; }
        .priority-low { background: #6c757d; color: white; }
        
        .status-new { background: #007bff; color: white; }
        .status-in_progress { background: #ffc107; color: #333; }
        .status-repaired { background: #28a745; color: white; }
        
        .request-subject {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .request-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>🛠️ GearGuard CMMS</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($currentUser['full_name']) ?> (<?= ucfirst($currentUser['role']) ?>)</span>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-section">
            <h2>👋 Welcome to GearGuard CMMS</h2>
            <p>Manage your maintenance operations efficiently and effectively</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="icon">📦</div>
                <div class="number"><?= $stats['active_equipment'] ?></div>
                <div class="label">Active Equipment</div>
            </div>
            
            <div class="stat-card warning">
                <div class="icon">🆕</div>
                <div class="number"><?= $stats['new_requests'] ?></div>
                <div class="label">New Requests</div>
            </div>
            
            <div class="stat-card info">
                <div class="icon">⚙️</div>
                <div class="number"><?= $stats['in_progress'] ?></div>
                <div class="label">In Progress</div>
            </div>
            
            <div class="stat-card success">
                <div class="icon">✅</div>
                <div class="number"><?= $stats['repaired_today'] ?></div>
                <div class="label">Repaired Today</div>
            </div>
            
            <div class="stat-card danger">
                <div class="icon">⚠️</div>
                <div class="number"><?= $stats['overdue_requests'] ?></div>
                <div class="label">Overdue Requests</div>
            </div>
            
            <div class="stat-card primary">
                <div class="icon">👤</div>
                <div class="number"><?= $stats['my_assigned'] ?></div>
                <div class="label">Assigned to Me</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h3>⚡ Quick Actions</h3>
            <div class="action-buttons">
                <a href="kanban.php" class="btn btn-primary">
                    🎯 Open Kanban Board
                </a>
                <a href="request-form.php" class="btn btn-success">
                    ➕ Create New Request
                </a>
                <a href="equipment-list.php" class="btn btn-info">
                    📦 View Equipment
                </a>
                <a href="calendar.php" class="btn btn-info">
                    📅 Calendar View
                </a>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="card">
                <h3>📋 Recent Requests</h3>
                <?php if (empty($recentRequests)): ?>
                <div class="empty-state">
                    <p>No requests found. Create your first maintenance request!</p>
                </div>
                <?php else: ?>
                <div class="request-list">
                    <?php foreach ($recentRequests as $req): ?>
                    <div class="request-item">
                        <div class="request-header">
                            <span class="request-number"><?= htmlspecialchars($req['request_number']) ?></span>
                            <div style="display: flex; gap: 0.5rem;">
                                <span class="priority-badge priority-<?= $req['priority'] ?>">
                                    <?= strtoupper($req['priority']) ?>
                                </span>
                                <span class="status-badge status-<?= $req['status'] ?>">
                                    <?= str_replace('_', ' ', strtoupper($req['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="request-subject"><?= htmlspecialchars($req['subject']) ?></div>
                        <div class="request-meta">
                            📦 <?= htmlspecialchars($req['equipment_name']) ?> | 
                            👤 <?= htmlspecialchars($req['requester_name']) ?> |
                            🕒 <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>🚨 Urgent Requests</h3>
                <?php if (empty($urgentRequests)): ?>
                <div class="empty-state">
                    <p>✅ No urgent requests at the moment!</p>
                </div>
                <?php else: ?>
                <div class="request-list">
                    <?php foreach ($urgentRequests as $req): ?>
                    <div class="request-item">
                        <div class="request-header">
                            <span class="request-number"><?= htmlspecialchars($req['request_number']) ?></span>
                            <span class="priority-badge priority-<?= $req['priority'] ?>">
                                <?= strtoupper($req['priority']) ?>
                            </span>
                        </div>
                        <div class="request-subject"><?= htmlspecialchars($req['subject']) ?></div>
                        <div class="request-meta">
                            📦 <?= htmlspecialchars($req['equipment_name']) ?><br>
                            👥 <?= htmlspecialchars($req['team_name']) ?><br>
                            <?php if ($req['technician_name']): ?>
                            👤 <?= htmlspecialchars($req['technician_name']) ?>
                            <?php else: ?>
                            ⚠️ Unassigned
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>