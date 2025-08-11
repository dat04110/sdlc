<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing logout.php\n", FILE_APPEND);

session_unset();
session_destroy();
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Logout successful\n", FILE_APPEND);

header('Location: index.php');
exit;
?>