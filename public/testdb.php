<?php
require 'Database.php';
$db = Database::getInstance()->getConnection();
echo "✅ Connected to Aiven successfully!";
