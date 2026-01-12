<?php
// config/cloudinary.php - TEMPORARY HARDCODED VERSION (remove secrets later!)

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => 'dyr0rok0l',      // ← REPLACE with your actual Cloudinary cloud name (from dashboard)
        'api_key'    => '564255156769188',         // ← REPLACE
        'api_secret' => '0TqAR76L8fEgKOuvDI4mbpVtw5c',      // ← REPLACE
    ],
    'url' => [
        'secure' => true,
    ],
]);