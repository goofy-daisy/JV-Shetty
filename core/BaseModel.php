<?php
/**
 * Base Model Class
 * Provides common database operations for all models
 */

abstract class BaseModel {
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Find a record by ID
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Find a record by a specific column
     */
    public function findBy(string $column, $value): ?array {
        // Sanitize column: only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = :value";
        
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':value' => $value]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Get all records
     */
    public function getAll(array $conditions = [], array $orderBy = [], int $limit = 0, int $offset = 0): array {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Add conditions
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $sql .= " AND {$key} IN (" . implode(',', array_fill(0, count($value), '?')) . ")";
                $params = array_merge($params, $value);
            } else {
                $sql .= " AND {$key} = ?";
                $params[] = $value;
            }
        }
        
        // Soft delete check
        if ($this->softDeletes && !isset($conditions['deleted_at'])) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        // Order by — whitelist column names and directions to prevent SQL injection
        if (!empty($orderBy)) {
            $orders = [];
            $allowedDirections = ['ASC', 'DESC'];
            foreach ($orderBy as $column => $direction) {
                // Only allow alphanumeric/underscore column names
                if (!preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
                    throw new InvalidArgumentException("Invalid ORDER BY column: {$column}");
                }
                $direction = strtoupper(trim($direction));
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'ASC';
                }
                $orders[] = "{$column} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orders);
        }
        
        // Limit and offset
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new record
     */
    public function create(array $data): int {
        // Filter fillable fields
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        // Add timestamps
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update a record
     */
    public function update(int $id, array $data): bool {
        // Filter fillable fields
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        // Add updated timestamp
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :id";
        
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $data['id'] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a record
     */
    public function delete(int $id): bool {
        if ($this->softDeletes) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * Count records
     */
    public function count(array $conditions = []): int {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        $params = [];
        
        foreach ($conditions as $key => $value) {
            $sql .= " AND {$key} = ?";
            $params[] = $value;
        }
        
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->db->rollBack();
    }
}
