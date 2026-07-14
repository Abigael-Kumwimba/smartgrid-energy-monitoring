<?php

$host = "localhost";
$user = "root";
$password = "";
$dbname = "smartgrid_energy";

$conn = new mysqli($host, $user, $password, $dbname, 3306);

if ($conn->connect_error) {
    die("Connexion échouée: " . $conn->connect_error);
}
?>
