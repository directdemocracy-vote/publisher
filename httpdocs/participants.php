<?php

require_once '../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$participation = json_decode(file_get_contents("php://input"));
if (!$participation)
  error("Unable to parse JSON post");

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $participation->fingerprint;
$polygons = 'ST_GeomFromText("MULTIPOLYGON(';
$t1 = false;
foreach($participation->polygons as $polygon1) {
  if ($t1)
    $polygons .= ', ';
  $polygons .= '(';
  $t1 = true;
  $t2 = false;
  foreach($polygon1 as $polygon2) {
    if ($t2)
      $polygons .= ', ';
    $polygons .= '(';
    $t2 = true;
    $t3 = false;
    foreach($polygon2 as $coordinates) {
      if ($t3)
        $polygons .= ', ';
      $t3 = true;
      die(json_encode($coordinates));
      $polygons .= $coordinates[0] . ' ' . $coordinates[1];
    }
    $polygons .= ')';
  }
  $polygons .= ')';
}
$polygons .= ')")';

$query = "SELECT id FROM publication WHERE fingerprint=\"$fingerprint\" LEFT JOIN referendum ON referendum.id=publication.id";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$referendum = $result->fetch_assoc();
$referendum_id = $referendum['id'];

# FIXME: add station
$query = "SELECT citizen.id, citizen.familyName, citizen.givenNames, citizen.picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude FROM citizen "
        ."LEFT JOIN registrations ON registrations.citizen=citizen.id AND registrations.referendum=$referendum_id "
        ."WHERE CONTAINS($polygons, home)";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  $citizen['id'] = intval($citizen['id']);
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizens[] = $citizen;
}
$result->free();
echo json_encode($citizens, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
