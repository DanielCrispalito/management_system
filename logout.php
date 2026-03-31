<?php
session_start();
session_destroy();
header('Location: /pjr_parking/login.php');
exit;
?>
