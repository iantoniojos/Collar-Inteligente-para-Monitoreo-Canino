<?php
require_once 'auth.php';
startSession();
session_destroy();
header('Location: login.php');
exit;
