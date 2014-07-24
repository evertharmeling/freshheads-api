<?php

require('vendor/autoload.php');
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

const CONTENT_TYPE_JSON = 'application/json';
const CONTENT_TYPE_JSON_LD = 'application/ld+json';
const CONTENT_TYPE_NQUADS = 'application/nquads';

ini_set('html_errors', false);

$requestHeaders = apache_request_headers();;
if ((isset($requestHeaders['Accept']) && $requestHeaders['Accept'] == CONTENT_TYPE_JSON_LD) || isset($_GET['json-ld'])) {
    $contentType = CONTENT_TYPE_JSON_LD;
} elseif ((isset($requestHeaders['Accept']) && $requestHeaders['Accept'] == CONTENT_TYPE_NQUADS) || isset($_GET['nquads'])) {
    $contentType = CONTENT_TYPE_NQUADS;
} else {
    $contentType = CONTENT_TYPE_JSON;
}

header('Content-Type: ' . $contentType);

$file = 'settings.ini';
if (!$settings = parse_ini_file($file, true)) {
    throw new \Exception(sprintf("Unable to open file '%s'", $file));
}

$dsn = sprintf('mysql:dbname=%s;host=%s', $settings['database']['name'], $settings['database']['host']);

try {
    $dbh = new PDO($dsn, $settings['database']['user'], $settings['database']['password'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"]);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

// single
if (isset($_GET['id']) && $id = $_GET['id']) {
    $sth = $dbh->prepare('SELECT * FROM employee WHERE id = :id');
    $sth->execute([':id' => $id]);
    $dbResults = $sth->fetchAll();

    $row = $dbResults[0];

    $result = parseRow($row, $contentType);
}
// collection call
else {
    $sth = $dbh->prepare('SELECT * FROM employee');
    $sth->execute();
    $dbResults = $sth->fetchAll();

    $result = [];
    foreach ($dbResults as $row) {
        $result[] = parseRow($row, $contentType);
    }
}

echo $result;

exit;

//----------------------------------------------------------------------

function parseRow($row, $contentType) {

    $array['givenName'] = $row['given_name'];
    $array['additionalName'] = $row['additional_name'];
    $array['familyName'] = $row['family_name'];
    $array['alternateName'] = $row['alternate_name'];
    $array['jobTitle'] = $row['job_title'];
    $array['birthDate'] = new \DateTime($row['birth_date']);
    $array['gender'] = $row['gender'];
    $array['image'] = $row['image'];
    $array['interests'] = array_map('trim', explode(',', $row['interests']));
    $array['sports'] = array_map('trim', explode(',', $row['sports']));
    $array['birthLocation'] = $row['birth_location'];
    $array['homeLocation'] = $row['home_location'];
    if (!is_null($row['pet'])) {
        $array['pet']['type'] = $row['pet'];
        $array['pet']['name'] = $row['pet_name'];
    } else {
        $array['pet'] = null;
    }
    $array['devices'] = array_map('trim', explode(',', $row['devices']));
    $array['favoriteDrink'] = $row['favorite_drink'];
    $array['favoriteSportingClub'] = $row['favorite_sporting_club'];
    $array['favoriteLunch'] = $row['favorite_lunch'];
    $array['favoriteColor'] = $row['favorite_color'];
    $array['favoriteSoftware'] = array_map('trim', explode(',', $row['favorite_software']));
    if (!is_null($row['pet'])) {
        $array['college']['name'] = $row['college'];
        $array['college']['study'] = $row['study'];
    } else {
        $array['college'] = null;
    }
    $array['inServiceSince'] = new \DateTime($row['in_service_since']);
    $array['facebookUrl'] = $row['facebook_url'];
    $array['twitterUrl'] = $row['twitter_url'];
    $array['linkedinUrl'] = $row['linkedin_url'];

    switch ($contentType) {
        case CONTENT_TYPE_JSON:
            // nothing particular to do
            break;
        case CONTENT_TYPE_JSON_LD:
        case CONTENT_TYPE_NQUADS:

            $context['xsd'] = 'http://www.w3.org/2001/XMLSchema#';
            $context['schema'] = 'http://schema.org/';
            $context['givenName'] = ['@id' => 'schema:givenName', '@type' => '@id'];
            $context['additionalName'] = ['@id' => 'schema:additionalName', '@type' => '@id'];
            $context['familyName'] = ['@id' => 'schema:familyName', '@type' => '@id'];
            $context['alternateName'] = ['@id' => 'schema:alternateName', '@type' => '@id'];
            $context['jobTitle'] = ['@id' => 'schema:jobTitle', '@type' => '@id'];
            $context['birthDate'] = ['@id' => 'schema:birthDate', '@type' => '@id'];
            $context['gender'] = ['@id' => 'schema:gender', '@type' => '@id'];
            $context['birthLocation'] = ['@id' => 'schema:City', '@type' => '@id'];
            $context['homeLocation'] = ['@id' => 'schema:City', '@type' => '@id'];

            $array = [
                    '@context' => $context,
                    '@id' => sprintf('http://evert.dev.freshheads.local/freshheads-api/?id=%s&json-ld', $row['id']),
                    '@type' => 'schema:Person',
                ] + $array;

            $array['homeLocation'] = [
                'name' => $array['homeLocation'],
                '@id' => 'http://dbpedia.org/resource/' . urlencode($array['homeLocation'])
            ];

            $array['birthLocation'] = [
                'name' => $array['birthLocation'],
                '@id' => 'http://dbpedia.org/resource/' . urlencode($array['birthLocation'])
            ];

            break;
        default:
            throw new \Exception(sprintf("Content-Type '%s' is not supported"));
    }

    if ($contentType == CONTENT_TYPE_NQUADS) {
        $quads = JsonLD::toRdf(json_encode($array));
        $nquads = new NQuads();
        $array = $nquads->serialize($quads);
    } else {
        $array = json_encode($array);
    }

    return $array;
}
