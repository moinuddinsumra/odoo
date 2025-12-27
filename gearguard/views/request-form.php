<?php
/**
 * GearGuard CMMS - Maintenance Request Form
 * Auto-fills team and category from equipment selection
 */

require_once 'C:\xampp\htdocs\gearguard\config\database.php';
require_once 'C:\xampp\htdocs\gearguard\includes\auth.php';
require_once 'C:\xampp\htdocs\gearguard\models\MaintenanceRequest.php';
require_once 'C:\xampp\htdocs\gearguard\models\Equipment.php';

requireAuth();

$equipmentModel = new Equipment();
$requestModel = new MaintenanceRequest();
$currentUser = getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'subject' => $_POST['subject'],
        'description' => $_POST['description'],
        'equipment_id' => $_POST['equipment_id'],
        'request_type' => $_POST['request_type'],
        'priority' => $_POST['priority'],
        'scheduled_date' => !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null,
        'requested_by' => $currentUser['id']
    ];
    
    $result = $requestModel->create($data);
    
    if ($result['success']) {
        $_SESSION['success'] = "Maintenance request {$result['request_number']} created successfully!";
        header('Location: kanban.php');
        exit;
    } else {
        $_SESSION['error'] = $result['message'];
    }
}

// Get all active equipment
$equipment = $equipmentModel->getAll(['status' => 'active']);

// Get equipment data for auto-fill (JSON)
$db = getDB();
$stmt = $db->query("
    SELECT 
        e.id, 
        e.name,
        e.category,
        e.maintenance_team_id,
        mt.name as team_name
    FROM equipment e
    INNER JOIN maintenance_teams mt ON e.maintenance_team_id = mt.id
    WHERE e.status = 'active'
");
$equipmentData = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Maintenance Request - GearGuard CMMS</title>
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        label.required::after {
            content: " *";
            color: #dc3545;
        }
        
        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #004085;
        }
        
        .auto-fill-indicator {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 0.75rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #155724;
            display: none;
        }
        
        .auto-fill-indicator.show {
            display: block;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
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
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>🛠️ GearGuard CMMS</h1>
        <a href="kanban.php" class="btn btn-secondary">← Back to Kanban</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="form-title">Create Maintenance Request</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>🤖 Smart Auto-Fill:</strong> When you select equipment, the system will automatically 
                assign the correct maintenance team and category. No manual duplication needed!
            </div>
            
            <form method="POST" id="requestForm">
                <div class="form-group">
                    <label for="equipment_id" class="required">Equipment</label>
                    <select name="equipment_id" id="equipment_id" required>
                        <option value="">-- Select Equipment --</option>
                        <?php foreach ($equipment as $eq): ?>
                        <option value="<?= $eq['id'] ?>" 
                                data-team="<?= htmlspecialchars($eq['team_name']) ?>"
                                data-category="<?= htmlspecialchars($eq['category']) ?>">
                            <?= htmlspecialchars($eq['name']) ?> (<?= htmlspecialchars($eq['serial_number']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="auto-fill-indicator" id="autoFillInfo">
                        <strong>✓ Auto-filled:</strong> 
                        Team: <span id="displayTeam"></span> | 
                        Category: <span id="displayCategory"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="request_type" class="required">Request Type</label>
                    <select name="request_type" id="request_type" required>
                        <option value="corrective">Corrective (Breakdown/Repair)</option>
                        <option value="preventive">Preventive (Routine Maintenance)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority" class="required">Priority</label>
                    <select name="priority" id="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject" class="required">Subject</label>
                    <input type="text" 
                           name="subject" 
                           id="subject" 
                           placeholder="e.g., Leaking Oil, Computer Not Starting"
                           required
                           maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" 
                              id="description" 
                              placeholder="Provide detailed information about the issue or maintenance needed"></textarea>
                </div>
                
                <div class="form-group" id="scheduledDateGroup" style="display:none;">
                    <label for="scheduled_date">Scheduled Date</label>
                    <input type="date" 
                           name="scheduled_date" 
                           id="scheduled_date"
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Create Request</button>
                    <a href="kanban.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Equipment selection auto-fill logic
        document.getElementById('equipment_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const team = selectedOption.getAttribute('data-team');
            const category = selectedOption.getAttribute('data-category');
            
            if (team && category) {
                document.getElementById('displayTeam').textContent = team;
                document.getElementById('displayCategory').textContent = category;
                document.getElementById('autoFillInfo').classList.add('show');
            } else {
                document.getElementById('autoFillInfo').classList.remove('show');
            }
        });
        
        // Show/hide scheduled date for preventive maintenance
        document.getElementById('request_type').addEventListener('change', function() {
            const scheduledDateGroup = document.getElementById('scheduledDateGroup');
            const scheduledDateInput = document.getElementById('scheduled_date');
            
            if (this.value === 'preventive') {
                scheduledDateGroup.style.display = 'block';
                scheduledDateInput.required = true;
            } else {
                scheduledDateGroup.style.display = 'none';
                scheduledDateInput.required = false;
                scheduledDateInput.value = '';
            }
        });
    </script>
</body>
</html>