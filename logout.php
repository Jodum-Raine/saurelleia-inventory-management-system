<?php
session_start();
require_once 'functions.php';

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], "Logout", "User logged out");
}

session_destroy();
header("Location: login.php");
?>