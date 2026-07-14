<?php
include("config.php");

$idCompteur = 1;

$tension = rand(210,240);
$courant = rand(1,10);
$puissance = $tension * $courant;
$energie = rand(1,5);

$sql = "INSERT INTO consommations
(id_compteur,tension,courant,puissance,energie)
VALUES
('$idCompteur','$tension','$courant','$puissance','$energie')";

$conn->query($sql);

echo "Données simulées ajoutées";
?>