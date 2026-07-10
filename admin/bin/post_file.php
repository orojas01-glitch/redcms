<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/upload_helpers.php';

function red_post_file_clean($value)
{
    return preg_replace("'<[^>]+>'U", '', (string) $value);
}

function red_post_file_stmt($connection, $query, $types = '', &...$values)
{
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        throw new RuntimeException('Database prepare failed.');
    }

    if ($types !== '' && !mysqli_stmt_bind_param($stmt, $types, ...$values)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Database parameter binding failed.');
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Database execution failed.');
    }

    return $stmt;
}

function red_post_file_article_exists($connection, $recordId)
{
    $stmt = red_post_file_stmt($connection, 'SELECT RecordID FROM RED_Articles WHERE RecordID=? LIMIT 1', 'i', $recordId);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function red_post_file_save_article_picture($connection, $recordId, $field, $storedName, $insert)
{
    $allowedFields = ['BigPict', 'SmallPict', 'SmallPict2'];
    if ($recordId <= 0 || !in_array($field, $allowedFields, true)) {
        red_upload_status('Invalid upload target.', 400);
    }

    $articleExists = red_post_file_article_exists($connection, $recordId);
    if (!$insert && !$articleExists) {
        throw new RuntimeException('Article upload target does not exist.');
    }

    if ($insert && !$articleExists) {
        $query = "INSERT INTO RED_Articles (RecordID, `$field`) VALUES (?, ?)";
        $stmt = red_post_file_stmt($connection, $query, 'is', $recordId, $storedName);
    } else {
        $query = "UPDATE RED_Articles SET `$field`=? WHERE RecordID=?";
        $stmt = red_post_file_stmt($connection, $query, 'si', $storedName, $recordId);
    }

    mysqli_stmt_close($stmt);
    return true;
}

function red_post_file_save_gallery($connection, $recordId, $artRecordId, $storedName)
{
    if ($recordId <= 0) {
        red_upload_status('Invalid upload target.', 400);
    }

    $stmt = red_post_file_stmt($connection, 'SELECT LongDesc, GalleryType FROM RED_C_Gallery WHERE RecordID=? LIMIT 1', 'i', $recordId);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($row) {
        $photos = (string) $row['LongDesc'];
        if ($row['GalleryType'] === 'Banner' || $row['GalleryType'] === 'Video') {
            $photos = $storedName;
        } else {
            $photos = $photos !== '' ? $photos . ',' . $storedName : $storedName;
        }

        $stmt = red_post_file_stmt($connection, 'UPDATE RED_C_Gallery SET LongDesc=? WHERE RecordID=?', 'si', $photos, $recordId);
        mysqli_stmt_close($stmt);
        return true;
    }

    $stmt = red_post_file_stmt($connection, 'INSERT INTO RED_C_Gallery (LongDesc, RecordID, RefID) VALUES (?, ?, ?)', 'sii', $storedName, $recordId, $artRecordId);
    mysqli_stmt_close($stmt);
    return true;
}

function red_post_file_save_logo($connection, $recordId, $storedName)
{
    if ($recordId <= 0) {
        red_upload_status('Invalid upload target.', 400);
    }

    $stmt = red_post_file_stmt($connection, 'SELECT RecordID FROM RED_Advanced WHERE RecordID=? LIMIT 1', 'i', $recordId);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$exists) {
        throw new RuntimeException('Logo upload target does not exist.');
    }

    $stmt = red_post_file_stmt($connection, 'UPDATE RED_Advanced SET Content=? WHERE RecordID=?', 'si', $storedName, $recordId);
    mysqli_stmt_close($stmt);
    return true;
}

$allowedImages = ['jpg', 'jpeg', 'png', 'gif'];
$maxImageBytes = 6 * 1024 * 1024;

$recordId = (int) ($_GET['RecordID'] ?? 0);
$artRecordId = (int) ($_GET['ArtRecordID'] ?? 0);
$uploadCase = red_post_file_clean($_GET['UC'] ?? '');
$insert = red_post_file_clean($_GET['Insert'] ?? '') === 'true';

if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    red_upload_status('Error! Wrong HTTP method!', 405);
}

if (!array_key_exists('pic', $_FILES)) {
    red_upload_status('No file was uploaded.', 400);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$file = $_FILES['pic'];

switch ($uploadCase) {
    case 'Gallery':
        if ($recordId <= 0) {
            $db->close();
            red_upload_status('Invalid upload target.', 400);
        }
        $fileInfo = red_upload_validate_file($file, $allowedImages, $maxImageBytes, true);
        $storedName = red_upload_move_and_persist(
            $file,
            'images/gallery',
            $fileInfo['safe_name'],
            function ($storedName) use ($db, $recordId, $artRecordId) {
                return red_post_file_save_gallery($db->connection, $recordId, $artRecordId, $storedName);
            }
        );
        $db->close();
        red_upload_status('File was uploaded successfully!', 200, ['stored_name' => $storedName]);
        break;

    case 'BigPict':
    case 'SmallPict':
    case 'SmallPict2':
        if ($recordId <= 0) {
            $db->close();
            red_upload_status('Invalid upload target.', 400);
        }
        $fileInfo = red_upload_validate_file($file, $allowedImages, $maxImageBytes, true);
        $storedName = red_upload_move_and_persist(
            $file,
            'images/articles',
            $fileInfo['safe_name'],
            function ($storedName) use ($db, $recordId, $uploadCase, $insert) {
                return red_post_file_save_article_picture($db->connection, $recordId, $uploadCase, $storedName, $insert);
            }
        );
        $db->close();
        red_upload_status('File was uploaded successfully!', 200, ['stored_name' => $storedName]);
        break;

    case 'Webpage_Logo':
        if ($recordId <= 0) {
            $db->close();
            red_upload_status('Invalid upload target.', 400);
        }
        $fileInfo = red_upload_validate_file($file, $allowedImages, $maxImageBytes, true);
        $storedName = red_upload_move_and_persist(
            $file,
            'images',
            $fileInfo['safe_name'],
            function ($storedName) use ($db, $recordId) {
                return red_post_file_save_logo($db->connection, $recordId, $storedName);
            }
        );
        $db->close();
        red_upload_status('Logo was uploaded successfully!', 200, ['stored_name' => $storedName]);
        break;

    default:
        $db->close();
        red_upload_status('Invalid upload target.', 400);
        break;
}

?>
