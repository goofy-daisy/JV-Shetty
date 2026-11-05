<?php
/**
 * User Model
 */

require_once __DIR__ . '/../core/BaseModel.php';

class User extends BaseModel {
    protected string $table = 'users';
    protected array $fillable = ['email', 'password_hash', 'role_id', 'is_active', 'is_verified', 'email_verified_at', 'remember_token'];
    protected bool $softDeletes = true;
    
    /**
     * Get user with profile
     */
    public function getWithProfile(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, p.first_name, p.last_name, p.phone, p.avatar_url, 
                   p.date_of_birth, p.gender, p.student_id, p.department,
                   r.name as role_name, r.display_name as role_display_name
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get user addresses
     */
    public function getAddresses(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT a.*, h.name as hostel_name, h.type as hostel_type
            FROM addresses a
            LEFT JOIN hostel_list h ON a.hostel_id = h.id
            WHERE a.user_id = ? AND a.is_active = 1
            ORDER BY a.is_default DESC, a.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create user with profile
     */
    public function createWithProfile(array $userData, array $profileData): int {
        try {
            $this->db->beginTransaction();
            
            $userId = $this->create($userData);
            
            $stmt = $this->db->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, phone)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $profileData['first_name'],
                $profileData['last_name'],
                $profileData['phone'] ?? null
            ]);
            
            $this->db->commit();
            return $userId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Update user with profile
     */
    public function updateWithProfile(int $id, array $userData, array $profileData): bool {
        try {
            $this->db->beginTransaction();
            
            if (!empty($userData)) {
                $this->update($id, $userData);
            }
            
            if (!empty($profileData)) {
                $fields = [];
                $values = [];
                foreach ($profileData as $key => $value) {
                    $fields[] = "{$key} = ?";
                    $values[] = $value;
                }
                $values[] = $id;
                
                $stmt = $this->db->prepare("
                    UPDATE user_profiles SET " . implode(', ', $fields) . " WHERE user_id = ?
                ");
                $stmt->execute($values);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get users by role
     */
    public function getByRole(string $roleName): array {
        $stmt = $this->db->prepare("
            SELECT u.*, p.first_name, p.last_name, p.phone
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE r.name = ? AND u.deleted_at IS NULL AND u.is_active = 1
        ");
        $stmt->execute([$roleName]);
        return $stmt->fetchAll();
    }
    
    /**
     * Search users
     */
    public function search(string $query): array {
        $search = "%{$query}%";
        $stmt = $this->db->prepare("
            SELECT u.*, p.first_name, p.last_name, p.phone, r.name as role_name
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            JOIN roles r ON u.role_id = r.id
            WHERE (u.email LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)
            AND u.deleted_at IS NULL
            LIMIT 20
        ");
        $stmt->execute([$search, $search, $search, $search]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get delivery partners
     */
    public function getDeliveryPartners(bool $availableOnly = false): array {
        $sql = "
            SELECT u.*, p.first_name, p.last_name, p.phone, dp.*
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN delivery_partners dp ON u.id = dp.user_id
            WHERE r.name = 'delivery_partner' AND u.deleted_at IS NULL AND u.is_active = 1
        ";
        
        if ($availableOnly) {
            $sql .= " AND dp.is_available = 1";
        }
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
