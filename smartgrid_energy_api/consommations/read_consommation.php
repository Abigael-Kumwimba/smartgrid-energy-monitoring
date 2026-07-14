<?php
include("../config.php");

$sql = "SELECT * FROM consommations";

$result = $conn->query($sql);

$data = array();

while($row = $result->fetch_assoc()){
$data[] = $row;
}

echo json_encode($data);
?>