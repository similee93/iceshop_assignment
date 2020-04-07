<?php

require_once('db.php');
require_once('../model/Image.php');
require_once('../model/Response.php');

//Connect to DB
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $err) {
    error_log('Connection error - ' . $err, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

//GET images
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        //Content type header is not set to json
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Content type header is not set to json');
            $response->send();
            exit;
        }

        $rawPOSTData = file_get_contents('php://input');

        //Request body is not valid JSON
        if (!$jsonData = json_decode($rawPOSTData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Request body is not valid JSON');
            $response->send();
            exit;
        }

        //clientId and search required in JSON body
        if (!isset($jsonData->clientId) || !isset($jsonData->search)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->clientId) ? $response->addMessage('Client ID field is mandatory and must be provided') : false);
            (!isset($jsonData->search) ? $response->addMessage('Search field is mandatory and must be provided') : false);
            $response->send();
            exit;
        }

        //Original name search uses SQL LIKE functionality
        $searchOriginalName = '%' . $jsonData->search . '%';

        //Query to retrieve results
        $query = $readDB->prepare('select id, client_id, original_name, hash, format, size_bites, url, status from tbl_images where client_id = :clientId and hash = :searchHash or original_name like :searchOriginalName');
        $query->bindParam(':clientId', $jsonData->clientId, PDO::PARAM_STR);
        $query->bindParam(':searchHash', $jsonData->search, PDO::PARAM_STR);
        $query->bindParam(':searchOriginalName', $searchOriginalName, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        $imagesArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['client_id'], $row['original_name'], $row['hash'], $row['format'], $row['size_bites'], $row['url'], $row['status']);
            $imagesArray[] = $image->returnImageAsArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rowCount;
        $returnData['tasks'] = $imagesArray;

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->toCache(true);
        $response->setData($returnData);
        $response->send();
        exit;
    }
    catch (ImageException $err) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage($err->getMessage());
        $response->send();
        exit;
    }
    catch (PDOException $err) {
        error_log('Database query error - '.$err, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage('Failed to get images');
        $response->send();
        exit;
    }
}
// POST request to upload images
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        //Content type header is not set to json
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Content type header is not set to json');
            $response->send();
            exit;
        }

        $rawPOSTData = file_get_contents('php://input');

        //Request body is not valid JSON
        if (!$jsonData = json_decode($rawPOSTData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Request body is not valid JSON');
            $response->send();
            exit;
        }

        //Client ID field and images are required in JSON body
        if (!isset($jsonData->clientId) || !isset($jsonData->images)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->clientId) ? $response->addMessage('Client ID field is mandatory and must be provided') : false);
            (!isset($jsonData->images) ? $response->addMessage('Images field is mandatory and must be provided') : false);
            $response->send();
            exit;
        }

        $imagesUploadedArray = array();

        //Loop through images received
        foreach ($jsonData->images as $image)
        {
            //Image name and base 64 received
            if (isset($image->originalName) && isset($image->base64)) {

                $temp = explode(".", $image->originalName);
                $extension = end($temp);
                $extension = strtolower($extension);

                if (!isValidMimeType($extension)) {
                    continue;
                }

                $img = "../files/images/$image->originalName";
                $fileSize = filesize($img);

                if (!isValidFileSize($fileSize)) {
                    continue;
                }

                file_put_contents($img, file_get_contents($image->base64));

                $hash = hash_file('md5', $img);
                $imageLink = $_SERVER['SERVER_NAME'] . "/files/images/$image->originalName";

                $newImage = new Image(null, $jsonData->clientId, $image->originalName, $hash, $extension, $fileSize, $imageLink, 'success');

                $clientId = $newImage->getClientId();
                $originalName = $newImage->getOriginalName();
                $hash = $newImage->getHash();
                $extension = $newImage->getFormat();
                $fileSize = $newImage->getSizeBites();
                $imageLink = $newImage->getUrl();
                $status = $newImage->getStatus();

                $query = $writeDB->prepare('insert into tbl_images (client_id, original_name, hash, format, size_bites, url, status) values (:client_id, :original_name, :hash, :format, :size_bites, :url, :status)');
                $query->bindParam(':client_id', $clientId, PDO::PARAM_STR);
                $query->bindParam(':original_name', $originalName, PDO::PARAM_STR);
                $query->bindParam(':hash', $hash, PDO::PARAM_STR);
                $query->bindParam(':format', $extension, PDO::PARAM_STR);
                $query->bindParam(':size_bites', $fileSize, PDO::PARAM_STR);
                $query->bindParam(':url', $imageLink, PDO::PARAM_STR);
                $query->bindParam(':status', $status, PDO::PARAM_STR);
                $query->execute();

                $lastImageID = $writeDB->lastInsertId();

                $query = $writeDB->prepare('select id, client_id, original_name, hash, format, size_bites, url, status from tbl_images where id = :imageid');
                $query->bindParam(':imageid', $lastImageID, PDO::PARAM_INT);
                $query->execute();

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $image = new Image($row['id'], $row['client_id'], $row['original_name'], $row['hash'], $row['format'], $row['size_bites'], $row['url'], $row['status']);

                    $imagesUploadedArray[] = $image->returnImageAsArray();
                }
            }
            //Image received as link
            elseif (isset($image->link)) {
                $url = $image->link;
                // Check if the image url has parameters appended
                if (strpos($url, '?') !== false) {
                    $t = explode('?',$url);
                    $url = $t[0];
                }

                $pathInfo = pathinfo($url);

                $extension = $pathInfo['extension'];

                $imageOriginalName = $pathInfo['basename'];
                $img = "../files/images/$imageOriginalName";

                if (!isValidMimeType($extension)) {
                    continue;
                }

                $fileSize = getRemoteFileSize($url);
                if (!isValidFileSize($fileSize)) {
                    continue;
                }

                file_put_contents($img, file_get_contents($image->link));

                $hash = hash_file('md5', $img);
                $imageLink = $_SERVER['SERVER_NAME'] . "/files/images/$imageOriginalName";

                $newImage = new Image(null, $jsonData->clientId, $imageOriginalName, $hash, $extension, $fileSize, $imageLink, 'success');

                $clientId = $newImage->getClientId();
                $originalName = $newImage->getOriginalName();
                $hash = $newImage->getHash();
                $extension = $newImage->getFormat();
                $fileSize = $newImage->getSizeBites();
                $imageLink = $newImage->getUrl();
                $status = $newImage->getStatus();

                $query = $writeDB->prepare('insert into tbl_images (client_id, original_name, hash, format, size_bites, url, status) values (:client_id, :original_name, :hash, :format, :size_bites, :url, :status)');
                $query->bindParam(':client_id', $clientId, PDO::PARAM_STR);
                $query->bindParam(':original_name', $originalName, PDO::PARAM_STR);
                $query->bindParam(':hash', $hash, PDO::PARAM_STR);
                $query->bindParam(':format', $extension, PDO::PARAM_STR);
                $query->bindParam(':size_bites', $fileSize, PDO::PARAM_STR);
                $query->bindParam(':url', $imageLink, PDO::PARAM_STR);
                $query->bindParam(':status', $status, PDO::PARAM_STR);
                $query->execute();

                $lastImageID = $writeDB->lastInsertId();

                $query = $writeDB->prepare('select id, client_id, original_name, hash, format, size_bites, url, status from tbl_images where id = :imageid');
                $query->bindParam(':imageid', $lastImageID, PDO::PARAM_INT);
                $query->execute();

                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $image = new Image($row['id'], $row['client_id'], $row['original_name'], $row['hash'], $row['format'], $row['size_bites'], $row['url'], $row['status']);

                    $imagesUploadedArray[] = $image->returnImageAsArray();
                }
            }
            else {
                continue;
            }
        }

        $returnData = array();
        $returnData['images'] = $imagesUploadedArray;

        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->addMessage('Images uploaded');
        $response->setData($returnData);
        $response->send();
        exit;
    }
    catch (ImageException $err) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage($err->getMessage());
        $response->send();
        exit;
    }
    catch (PDOException $err) {
        error_log('Database query error - '.$err, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage(/*'Failed to create image in to database, check submitted metadata for errors')*/$err->getMessage());
        $response->send();
        exit;
    }
}
else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage('Request method not allowed');
    $response->send();
    exit;
}

// Check if image mime type is valid
function isValidMimeType($extension)
{
    $allowedMimeTypes = array('gif', 'jpeg', 'jpg', 'png', 'bmp');
    if (in_array($extension, $allowedMimeTypes)) {
        return true;
    }
    return false;
}

// Check file size less than 5MB (5242880 bytes)
function isValidFileSize($fileSize)
{
    if ($fileSize <= 5242880) {
        return true;
    }

    return false;
}

// Get remote file size without downloading content
function getRemoteFileSize($url)
{
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    curl_setopt($curl, CURLOPT_NOBODY, TRUE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

    curl_exec($curl);
    $size = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    curl_close($curl);
    return $size;
}