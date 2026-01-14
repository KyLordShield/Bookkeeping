<?php
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
die("Session dump above. If this is empty or missing 'user_type' and 'staff_id', that's the problem.");
?>