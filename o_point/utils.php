<?php
// Helpers O.point
function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_logged_in() { return isset($_SESSION['user_id']); }
function require_login() { if (!is_logged_in()) { header('Location: /o_point/login.php'); exit; } }
