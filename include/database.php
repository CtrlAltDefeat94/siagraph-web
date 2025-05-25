<?php
include_once "config.php";
require __DIR__ . '/../vendor/autoload.php'; // Ensure Predis is installed via Composer

// MySQL database connection configuration

$mysqli = mysqli_connect(
    $SETTINGS['database']['servername'],
    $SETTINGS['database']['username'],
    $SETTINGS['database']['password'],
    $SETTINGS['database']['database']
);

// Check connection
if (!$mysqli) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}
