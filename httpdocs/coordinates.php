<?php
require_once '../php/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$key = $mysqli->escape_string($_POST['key']);
$query = "SELECT ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen INNER JOIN publication ON publication.id = citizen.id "
        ."WHERE publication.`key` = '$key'";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $key\"}");
$result->free();
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
$mysqli->close();
die(json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
