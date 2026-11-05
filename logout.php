<?php
/**
 * Logout
 */

require_once __DIR__ . '/config/app.php';

Auth::logout();
header('Location: login.php');
exit;
