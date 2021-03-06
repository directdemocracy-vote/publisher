<?php
require_once '../php/database.php';

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$referendum = $mysqli->escape_string(get_string_parameter('referendum'));
if (!$referendum)
  die("Missing referendum argument.");
$citizen_key = $mysqli->escape_string(get_string_parameter('citizen'));
if (!$citizen_key)
  die("Missing citizen argument.");

$query = "SELECT trustee, area FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`=\"$referendum\"";
$result = $mysqli->query($query) or die($mysqli->error);
$r = $result->fetch_assoc();
$result->free();
if (!$r)
  die('Referendum not found.');
$trustee = $r['trustee'];
$area = $r['area'];

# check if citizen's home is inside the referendum area
$query = "SELECT ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen "
        ."LEFT JOIN publication ON publication.id=citizen.id WHERE publication.`key`=\"$citizen_key\"";
$result = $mysqli->query($query) or die($mysqli->error);
$citizen = $result->fetch_assoc();
$result->free();
if (!$citizen)
  die('Citizen not found.');
$latitude = $citizen['latitude'];
$longitude = $citizen['longitude'];

$query = "SELECT area.id FROM area LEFT JOIN publication ON publication.id=area.id "
        ." WHERE publication.`key`=\"$trustee\" AND area.name=\"$area\"";
$result = $mysqli->query($query) or die($mysqli->error);
$a = $result->fetch_assoc();
$result->free();
if (!$a)
  die('Area not found.');
$area = $a['id'];

$query = "SELECT area.id FROM area WHERE area.id=$area AND ST_Contains(polygons, POINT($longitude, $latitude))";
$result = $mysqli->query($query) or die($mysqli->error);
$a = $result->fetch_assoc();
$result->free();
if (!$a)
  die("Home of citizen not in referendum area.");

# check if citizen is currently endorsed by trustee
$query = "SELECT revoked "
        ."FROM endorsement LEFT JOIN publication ON publication.id=endorsement.id "
        ."WHERE publication.`key`=\"$trustee\" AND endorsement.publicationKey=\"$citizen_key\" "
        ."AND endorsement.revoked=publication.expires "
        ."ORDER BY revoked DESC LIMIT 1";
$result = $mysqli->query($query) or die($mysqli->error);
$endorsement = $result->fetch_assoc();
$result->free();
$now = intval(microtime(true) * 1000);  # milliseconds
if (!$endorsement || $endorsement['revoked'] < $now)
  die("Citizen not endorsed by trustee");

die("yes");
?>
