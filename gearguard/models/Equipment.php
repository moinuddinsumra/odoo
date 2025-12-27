<?php
/**
 * GearGuard CMMS - Equipment Model
 * Business Logic for Equipment Management
 */

require_once __DIR__ . '/../config/database.php';

class Equipment {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create new equipment with auto-validation
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO equipment (
                    name, serial_number, category, department_id, 
                    assigned_employee_id, maintenance_team_id, default_technician_id,
                    location, purchase_date, warranty_expiry, model, manufacturer, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['serial_number'],
                $data['category'],
                $data['department_id'] ?? null,
                $data['assigned_employee_id'] ?? null,
                $data['maintenance_team_id'],
                $data['default_technician_id'] ?? null,
                $data['location'] ?? null,
                $data['purchase_date'] ?? null,
                $data['warranty_expiry'] ?? null,
                $data['model'] ?? null,
                $data['manufacturer'] ?? null,
                $data['description'] ?? null
            ]);
            
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch(PDOException $e) {
            error_log("Equipment Create Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create equipment'];
        }
    }
    
    /**
     * Get equipment by ID with related data
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    e.*,
                    d.name as department_name,
                    u.full_name as assigned_employee_name,
                    mt.name as team_name,
                    mt.id as team_id,
                    tech.full_name as technician_name
                FROM equipment e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN users u ON e.assigned_employee_id = u.id
                LEFT JOIN maintenance_teams mt ON e.maintenance_team_id = mt.id
                LEFT JOIN users tech ON e.default_technician_id = tech.id
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Equipment Get Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all equipment with filters
     */
    public function getAll($filters = []) {
        try {
            $sql = "
                SELECT 
                    e.*,
                    d.name as department_name,
                    u.full_name as assigned_employee_name,
                    mt.name as team_name
                FROM equipment e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN users u ON e.assigned_employee_id = u.id
                LEFT JOIN maintenance_teams mt ON e.maintenance_team_id = mt.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND e.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['category'])) {
                $sql .= " AND e.category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['department_id'])) {
                $sql .= " AND e.department_id = ?";
                $params[] = $filters['department_id'];
            }
            
            if (!empty($filters['team_id'])) {
                $sql .= " AND e.maintenance_team_id = ?";
                $params[] = $filters['team_id'];
            }
            
            $sql .= " ORDER BY e.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Equipment GetAll Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update equipment
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE equipment SET
                    name = ?,
                    category = ?,
                    department_id = ?,
                    assigned_employee_id = ?,
                    maintenance_team_id = ?,
                    default_technician_id = ?,
                    location = ?,
                    purchase_date = ?,
                    warranty_expiry = ?,
                    model = ?,
                    manufacturer = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['category'],
                $data['department_id'] ?? null,
                $data['assigned_employee_id'] ?? null,
                $data['maintenance_team_id'],
                $data['default_technician_id'] ?? null,
                $data['location'] ?? null,
                $data['purchase_date'] ?? null,
                $data['warranty_expiry'] ?? null,
                $data['model'] ?? null,
                $data['manufacturer'] ?? null,
                $data['description'] ?? null,
                $data['status'] ?? 'active',
                $id
            ]);
            
            return ['success' => true];
        } catch(PDOException $e) {
            error_log("Equipment Update Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update equipment'];
        }
    }
    
    /**
     * Mark equipment as scrapped
     */
    public function scrap($id) {
        try {
            $stmt = $this->db->prepare("UPDATE equipment SET status = 'scrapped' WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch(PDOException $e) {
            error_log("Equipment Scrap Error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * Get open maintenance requests count for equipment (Smart Button)
     */
    public function getOpenRequestsCount($equipmentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM maintenance_requests 
                WHERE equipment_id = ? AND status NOT IN ('repaired', 'scrap')
            ");
            $stmt->execute([$equipmentId]);
            $result = $stmt->fetch();
            return $result['count'];
        } catch(PDOException $e) {
            error_log("Get Open Requests Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all categories for dropdown
     */
    public function getCategories() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT category FROM equipment ORDER BY category");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(PDOException $e) {
            error_log("Get Categories Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if serial number already exists
     */
    public function serialExists($serial, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM equipment WHERE serial_number = ?";
            $params = [$serial];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch(PDOException $e) {
            error_log("Serial Check Error: " . $e->getMessage());
            return false;
        }
    }
}
?>