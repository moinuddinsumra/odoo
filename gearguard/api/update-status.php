<?php
/**
 * GearGuard CMMS - Maintenance Request Model
 * Business Logic with Auto-Fill & Workflow Automation
 */

require_once __DIR__ . '/../config/database.php';

class MaintenanceRequest {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Generate unique request number (e.g., REQ-2025-0001)
     */
    private function generateRequestNumber() {
        $year = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM maintenance_requests 
            WHERE YEAR(created_at) = ?
        ");
        $stmt->execute([$year]);
        $result = $stmt->fetch();
        $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return "REQ-{$year}-{$sequence}";
    }
    
    /**
     * Create new maintenance request with auto-fill logic
     * CRITICAL: Auto-fetches team and category from equipment
     */
    public function create($data) {
        try {
            // BUSINESS LOGIC: Auto-fill equipment details
            $stmt = $this->db->prepare("
                SELECT maintenance_team_id, category, default_technician_id 
                FROM equipment 
                WHERE id = ?
            ");
            $stmt->execute([$data['equipment_id']]);
            $equipment = $stmt->fetch();
            
            if (!$equipment) {
                return ['success' => false, 'message' => 'Equipment not found'];
            }
            
            // Auto-assign team and category from equipment
            $teamId = $equipment['maintenance_team_id'];
            $category = $equipment['category'];
            $defaultTech = $equipment['default_technician_id'];
            
            $requestNumber = $this->generateRequestNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO maintenance_requests (
                    request_number, subject, description, equipment_id,
                    equipment_category, maintenance_team_id, request_type,
                    priority, status, scheduled_date, requested_by, assigned_to
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $requestNumber,
                $data['subject'],
                $data['description'] ?? null,
                $data['equipment_id'],
                $category,
                $teamId,
                $data['request_type'],
                $data['priority'] ?? 'medium',
                'new',
                $data['scheduled_date'] ?? null,
                $data['requested_by'],
                $data['assigned_to'] ?? $defaultTech
            ]);
            
            $requestId = $this->db->lastInsertId();
            
            // Log creation
            $this->logAction($requestId, $data['requested_by'], 'created', null, 'new');
            
            return ['success' => true, 'id' => $requestId, 'request_number' => $requestNumber];
        } catch(PDOException $e) {
            error_log("Request Create Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create request'];
        }
    }
    
    /**
     * Get request by ID with full details
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    mr.*,
                    e.name as equipment_name,
                    e.serial_number,
                    mt.name as team_name,
                    req.full_name as requester_name,
                    tech.full_name as technician_name,
                    tech.avatar as technician_avatar
                FROM maintenance_requests mr
                INNER JOIN equipment e ON mr.equipment_id = e.id
                INNER JOIN maintenance_teams mt ON mr.maintenance_team_id = mt.id
                INNER JOIN users req ON mr.requested_by = req.id
                LEFT JOIN users tech ON mr.assigned_to = tech.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Request Get Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all requests with filters (for Kanban and Calendar)
     */
    public function getAll($filters = []) {
        try {
            $sql = "
                SELECT 
                    mr.*,
                    e.name as equipment_name,
                    mt.name as team_name,
                    tech.full_name as technician_name,
                    tech.avatar as technician_avatar,
                    CASE 
                        WHEN mr.scheduled_date < CURDATE() AND mr.status != 'repaired' 
                        THEN 1 ELSE 0 
                    END as is_overdue
                FROM maintenance_requests mr
                INNER JOIN equipment e ON mr.equipment_id = e.id
                INNER JOIN maintenance_teams mt ON mr.maintenance_team_id = mt.id
                LEFT JOIN users tech ON mr.assigned_to = tech.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Team-based visibility (RBAC)
            if (!empty($filters['team_id'])) {
                $sql .= " AND mr.maintenance_team_id = ?";
                $params[] = $filters['team_id'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND mr.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['request_type'])) {
                $sql .= " AND mr.request_type = ?";
                $params[] = $filters['request_type'];
            }
            
            if (!empty($filters['equipment_id'])) {
                $sql .= " AND mr.equipment_id = ?";
                $params[] = $filters['equipment_id'];
            }
            
            if (!empty($filters['assigned_to'])) {
                $sql .= " AND mr.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }
            
            // Calendar view: only preventive with scheduled dates
            if (isset($filters['calendar_view']) && $filters['calendar_view']) {
                $sql .= " AND mr.request_type = 'preventive' AND mr.scheduled_date IS NOT NULL";
            }
            
            $sql .= " ORDER BY mr.priority DESC, mr.scheduled_date ASC, mr.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Request GetAll Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update request status with workflow validation
     */
    public function updateStatus($id, $newStatus, $userId, $duration = null) {
        try {
            $this->db->beginTransaction();
            
            // Get current status
            $stmt = $this->db->prepare("SELECT status FROM maintenance_requests WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Request not found'];
            }
            
            $oldStatus = $current['status'];
            
            // Update status
            $stmt = $this->db->prepare("
                UPDATE maintenance_requests 
                SET status = ?, completed_date = ?, duration_hours = ?
                WHERE id = ?
            ");
            
            $completedDate = ($newStatus === 'repaired' || $newStatus === 'scrap') ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$newStatus, $completedDate, $duration, $id]);
            
            // If moving to scrap, mark equipment as scrapped
            if ($newStatus === 'scrap') {
                $stmt = $this->db->prepare("
                    UPDATE equipment e
                    INNER JOIN maintenance_requests mr ON e.id = mr.equipment_id
                    SET e.status = 'scrapped'
                    WHERE mr.id = ?
                ");
                $stmt->execute([$id]);
            }
            
            // Log status change
            $this->logAction($id, $userId, 'status_changed', $oldStatus, $newStatus);
            
            $this->db->commit();
            return ['success' => true];
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Update Status Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    /**
     * Assign technician to request
     */
    public function assignTechnician($id, $technicianId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE maintenance_requests SET assigned_to = ? WHERE id = ?
            ");
            $stmt->execute([$technicianId, $id]);
            
            $this->logAction($id, $userId, 'assigned', null, $technicianId);
            return ['success' => true];
        } catch(PDOException $e) {
            error_log("Assign Technician Error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * Log action for audit trail
     */
    private function logAction($requestId, $userId, $action, $oldValue, $newValue) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO maintenance_request_logs (request_id, user_id, action, old_value, new_value)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$requestId, $userId, $action, $oldValue, $newValue]);
        } catch(PDOException $e) {
            error_log("Log Action Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get requests grouped by status (for Kanban)
     */
    public function getKanbanData($filters = []) {
        $statuses = ['new', 'in_progress', 'repaired', 'scrap'];
        $kanban = [];
        
        foreach ($statuses as $status) {
            $filters['status'] = $status;
            $kanban[$status] = $this->getAll($filters);
        }
        
        return $kanban;
    }
    
    /**
     * Get dashboard statistics
     */
    public function getStats($filters = []) {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'repaired' THEN 1 ELSE 0 END) as repaired_count,
                    SUM(CASE WHEN request_type = 'corrective' THEN 1 ELSE 0 END) as corrective_count,
                    SUM(CASE WHEN request_type = 'preventive' THEN 1 ELSE 0 END) as preventive_count,
                    SUM(CASE WHEN scheduled_date < CURDATE() AND status NOT IN ('repaired', 'scrap') THEN 1 ELSE 0 END) as overdue_count
                FROM maintenance_requests
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['team_id'])) {
                $sql .= " AND maintenance_team_id = ?";
                $params[] = $filters['team_id'];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get Stats Error: " . $e->getMessage());
            return [];
        }
    }
}
?>