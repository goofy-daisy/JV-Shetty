<?php
/**
 * Maintenance Mode Page
 * Shown when system_settings.maintenance_mode = 'true'
 */

// Minimal bootstrap — DO NOT include app.php here, it would loop
define('MAINTENANCE_PAGE', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- Auto-refresh every 60s -->
    <title>Under Maintenance — JV Shetty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .gear-icon {
            font-size: 5rem;
            color: #667eea;
            animation: spin 4s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="maintenance-card card mx-3">
        <div class="card-body p-5 text-center">
            <div class="gear-icon mb-3">
                <i class="bi bi-gear-fill"></i>
            </div>
            <h2 class="fw-bold mb-2">Under Maintenance</h2>
            <p class="text-muted mb-4">
                JV Shetty Campus Store is temporarily down for scheduled maintenance.
                We'll be back soon — this page will auto-refresh every minute.
            </p>

            <div class="alert alert-info py-2 mb-4">
                <i class="bi bi-clock me-2"></i>
                <strong>Store Hours:</strong> 4:00 PM – 9:00 PM daily
            </div>

            <p class="pulse text-muted small mb-0">
                <i class="bi bi-arrow-repeat me-1"></i>Checking availability...
            </p>
        </div>
    </div>
</body>
</html>
