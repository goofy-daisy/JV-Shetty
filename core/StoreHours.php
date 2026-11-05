<?php
/**
 * Store Hours & Timing Utility
 * Manages store open/closed status and order windows
 */

class StoreHours {
    
    /**
     * Check if store is currently open
     */
    public static function isOpen(): bool {
        $now = new DateTime();
        $dayOfWeek = (int)$now->format('w');
        $currentTime = $now->format('H:i:s');
        
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM time_windows 
            WHERE (day_of_week = ? AND special_date IS NULL) 
               OR (special_date = CURDATE())
            ORDER BY special_date DESC
            LIMIT 1
        ");
        $stmt->execute([$dayOfWeek]);
        $window = $stmt->fetch();
        
        if (!$window || !$window['is_open']) {
            return false;
        }
        
        return $currentTime >= $window['open_time'] && $currentTime <= $window['close_time'];
    }
    
    /**
     * Get store status with details
     */
    public static function getStatus(): array {
        $now = new DateTime();
        $dayOfWeek = (int)$now->format('w');
        $currentTime = $now->format('H:i:s');
        
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM time_windows 
            WHERE (day_of_week = ? AND special_date IS NULL) 
               OR (special_date = CURDATE())
            ORDER BY special_date DESC
            LIMIT 1
        ");
        $stmt->execute([$dayOfWeek]);
        $window = $stmt->fetch();
        
        if (!$window) {
            return [
                'is_open' => false,
                'message' => 'Store hours not configured',
                'next_open' => null,
                'today_hours' => null
            ];
        }
        
        $isOpen = $window['is_open'] && 
                  $currentTime >= $window['open_time'] && 
                  $currentTime <= $window['close_time'];
        
        $formatter = new DateTime('2000-01-01 ' . $window['open_time']);
        $openTimeFormatted = $formatter->format('g:i A');
        $formatter = new DateTime('2000-01-01 ' . $window['close_time']);
        $closeTimeFormatted = $formatter->format('g:i A');
        
        return [
            'is_open' => $isOpen,
            'message' => $isOpen ? 'Open now' : 'Closed now',
            'today_hours' => [
                'open' => $openTimeFormatted,
                'close' => $closeTimeFormatted
            ],
            'can_order' => $isOpen,
            'can_browse' => true
        ];
    }
    
    /**
     * Validate if checkout is allowed
     */
    public static function validateCheckout(): array {
        if (!self::isOpen()) {
            $status = self::getStatus();
            return [
                'allowed' => false,
                'message' => 'Orders can only be placed between 4:00 PM and 9:00 PM. Please come back during store hours.',
                'hours' => $status['today_hours']
            ];
        }
        
        return ['allowed' => true, 'message' => 'Checkout available'];
    }
    
    /**
     * Get remaining time until store closes
     */
    public static function getTimeUntilClose(): ?int {
        if (!self::isOpen()) {
            return null;
        }
        
        $now = new DateTime();
        $db = Database::getInstance();
        $dayOfWeek = (int)$now->format('w');
        
        $stmt = $db->prepare("
            SELECT close_time FROM time_windows 
            WHERE (day_of_week = ? AND special_date IS NULL) 
               OR (special_date = CURDATE())
            ORDER BY special_date DESC
            LIMIT 1
        ");
        $stmt->execute([$dayOfWeek]);
        $window = $stmt->fetch();
        
        if (!$window) {
            return null;
        }
        
        $closeTime = new DateTime($window['close_time']);
        $now = new DateTime();
        
        return $now->diff($closeTime)->i + ($now->diff($closeTime)->h * 60);
    }
    
    /**
     * Get next opening time
     */
    public static function getNextOpening(): ?array {
        $now = new DateTime();
        $db = Database::getInstance();
        
        // Get all windows for the week
        $stmt = $db->query("SELECT * FROM time_windows WHERE special_date IS NULL ORDER BY day_of_week");
        $windows = $stmt->fetchAll();
        
        $dayOfWeek = (int)$now->format('w');
        
        // Check remaining days this week
        for ($i = 0; $i < 7; $i++) {
            $checkDay = ($dayOfWeek + $i) % 7;
            
            foreach ($windows as $window) {
                if ($window['day_of_week'] == $checkDay && $window['is_open']) {
                    $nextOpen = new DateTime();
                    $nextOpen->modify("+{$i} days");
                    $nextOpen->setTime(
                        (int)substr($window['open_time'], 0, 2),
                        (int)substr($window['open_time'], 3, 2)
                    );
                    
                    // If same day, check if already passed
                    if ($i == 0) {
                        $currentTime = $now->format('H:i:s');
                        if ($currentTime > $window['open_time']) {
                            continue;
                        }
                    }
                    
                    return [
                        'day' => $nextOpen->format('l'),
                        'date' => $nextOpen->format('j M'),
                        'time' => $nextOpen->format('g:i A'),
                        'datetime' => $nextOpen->format('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Format store hours for display
     */
    public static function getFormattedHours(): array {
        $db = Database::getInstance();
        $stmt = $db->query("
            SELECT day_of_week, open_time, close_time, is_open
            FROM time_windows 
            WHERE special_date IS NULL
            ORDER BY day_of_week
        ");
        $windows = $stmt->fetchAll();
        
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $formatted = [];
        
        foreach ($windows as $window) {
            $open = new DateTime($window['open_time']);
            $close = new DateTime($window['close_time']);
            
            $formatted[] = [
                'day' => $days[$window['day_of_week']],
                'hours' => $window['is_open'] 
                    ? $open->format('g:i A') . ' - ' . $close->format('g:i A')
                    : 'Closed',
                'is_open' => (bool)$window['is_open']
            ];
        }
        
        return $formatted;
    }
}
