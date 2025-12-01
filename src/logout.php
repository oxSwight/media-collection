<?php
require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_valid_csrf_token($_POST['_token'] ?? null);

session_unset();
session_destroy();

header("Location: login.php");
exit;