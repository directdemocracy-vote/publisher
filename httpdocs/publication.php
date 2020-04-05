<?php
require_once '../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));
$key = $mysqli->escape_string(get_string_parameter('key'));

$now = floatval(microtime(true) * 1000);  # milliseconds
$query = "SELECT * FROM publication WHERE published > $now AND expires > $now AND ";
if ($key)
  $query .= "`key`=\"$key\"";
elseif ($fingerprint)
  $query .= "fingerprint=\"$fingerprint\";";
else
  error("No fingerprint or key argument provided.");

$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Publication not found.");
$publication = $result->fetch_assoc();
$result->free();
$type = get_type($publication['schema']);
if ($type == 'citizen') {
  $query = "SELECT familyName, givenNames, picture, latitude, longitude FROM citizen WHERE id=$publication[id]";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizen = $result->fetch_assoc();
  $result->free();
  $citizen['latitude'] = intval($citizen['latitude']);
  $citizen['longitude'] = intval($citizen['longitude']);
  $citizen = array('schema' => $publication['schema'],
                   'key' => $publication['key'],
                   'signature' => $publication['signature'],
                   'published' => floatval($publication['published']),
                   'expires' => floatval($publication['expires'])) + $citizen;
  echo json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'endorsement') {
  $query = "SELECT publicationKey, publicationSignature, `revoke`, message, comment FROM endorsement WHERE id=$publication[id]";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsement = $result->fetch_assoc();
  $result->free();
  $endorsement['revoke'] = ($endorsement['revoke'] == 1);
  $endorsement = array('schema' => $publication['schema'],
                       'key' => $publication['key'],
                       'signature' => $publication['signature'],
                       'published' => floatval($publication['published']),
                       'expires' => floatval($publication['expires'])) + $endorsement;
  echo json_encode($endorsement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'referendum') {
  $query = "SELECT trustee, area, title, description, question, answers, deadline, website FROM referendum WHERE id=$publication[id]";
  # id AS areas is just a placeholder for having areas in the right order of fields
  $result = $mysqli->query($query) or error($mysqli->error);
  $referendum = $result->fetch_assoc();
  $result->free();
  if ($referendum['website'] == '')
    unset($referendum['website']);
  $referendum['deadline'] = floatval($referendum['deadline']);
  $referendum = array('schema' => $publication['schema'],
                      'key' => $publication['key'],
                      'signature' => $publication['signature'],
                      'published' => floatval($publication['published']),
                      'expires' => floatval($publication['expires'])) + $referendum;
  echo json_encode($referendum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'vote') {
  $query = "SELECT answer from vote WHERE id=$publication[id]";
  $result = $mysqli->query($query) or error($mysqli->error);
  $vote = $result->fetch_assoc();
  $result->free();
  $vote = array('schema' => $publication['schema'],
                'key' => $publication['key'],
                'signature' => $publication['signature'],
                'published' => floatval($publication['published']),
                'expires' => floatval($publication['expires'])) + $vote;
  echo json_encode($vote, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$mysqli->close();
?>
