<?php
// dn: uid=patrick.heid,ou=Schueler,ou=Benutzer,dc=gertzenstein,dc=local
// objectClass: inetOrgPerson
// objectClass: organizationalPerson
// objectClass: person
// objectClass: top
// uid: patrick.heid
// givenName: Patrick
// sn: Heid
// cn: Patrick Heid
// mail:patrick.heid@gibmit.ch
// userPassword: test
// description: Blablabla
// displayName: Heid, Patrick

require("./fileHandler.php");

$arr_users = $arr_convertedUsers = [];
$error = $message = "";
$uploadFilePath = "upload/upload.csv";
$headerFilePath = "upload/header.txt";
$downloadFilePath = "download/list.ldif";
$downloadAllFilePath = "download/all.ldif";
$passwordFilePath = "download/password.csv";
$groupFilePath = "download/group.ldif";
$convertedUsers = "";
$isUpload = false;

$baseDn = ",dc=gertzenstein,dc=local";


// Upload file
if (isset($_POST["upload-csv"])) {

    if (isset($_FILES["file-csv"])) {

        $mimes = array('application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv');

        // Error during Upload
        if ($_FILES["file-csv"]["error"] > 0) {
            $error .=  "CSV Fehlercode: " . $_FILES["file-csv"]["error"] . "<br />";
        } else if (!in_array($_FILES["file-csv"]["type"], $mimes)) {
            $error .=  "Falscher Dateityp, Datei muss im CSV Format sein<br />";
        } else {
            //Store file in directory "upload" with the name of "upload.csv"
            move_uploaded_file($_FILES["file-csv"]["tmp_name"], $uploadFilePath);
            $message = "CSV Datei erfolgreich hochgeladen<br />";
            $isUpload = true;

            convertCSVtoLDIF($uploadFilePath, $downloadFilePath, $passwordFilePath, $groupFilePath);
            createCompleteList($downloadFilePath, $headerFilePath, $groupFilePath, $downloadAllFilePath);
            $message = "Konvertieren abgeschlossen";
            $convertedUsers = getPreviewData($downloadFilePath);
        }
    }
}

// Upload file
if (isset($_POST["upload-header"])) {

    if (isset($_FILES["file-header"])) {

        // Error during Upload
        if ($_FILES["file-header"]["error"] > 0) {
            $error .=  "Fehlercode: " . $_FILES["file-header"]["error"] . "<br />";
        } else {
            //Store file in directory "upload" with the name of "upload.csv"
            move_uploaded_file($_FILES["file-header"]["tmp_name"], $headerFilePath);
            createCompleteList($downloadFilePath, $headerFilePath, $groupFilePath, $downloadAllFilePath);
            $message = "Header Datei erfolgreich hochgeladen<br />";
        }
    }
}

if (isset($_POST["convert"])) {
    $message = "Starte konvertieren";
    if (convertCSVtoLDIF($uploadFilePath, $downloadFilePath, $passwordFilePath, $groupFilePath)) {
        createCompleteList($downloadFilePath, $headerFilePath, $groupFilePath, $downloadAllFilePath);
        $message = "Konvertieren abgeschlossen";
    } else {
        $message = "";
        $error = "Konvertieren fehlgeschlagen";
    }
}


if (isset($_POST["ldif-download"])) {
    if (file_exists($downloadFilePath)) {
        //Define header information
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: 0");
        header('Content-Disposition: attachment; filename="' . basename($downloadFilePath) . '"');
        header('Content-Length: ' . filesize($downloadFilePath));
        header('Pragma: public');

        //Clear system output buffer
        flush();

        //Read the size of the file
        readfile($downloadFilePath);
    } else {
        $error .= "LDIF Datei existiert nicht.";
    }
}

if (isset($_POST["all-download"])) {
    if (file_exists($downloadFilePath) && file_exists($headerFilePath)) {
        createCompleteList($downloadFilePath, $headerFilePath, $groupFilePath, $downloadAllFilePath);
        //Define header information
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: 0");
        header('Content-Disposition: attachment; filename="' . basename($downloadAllFilePath) . '"');
        header('Content-Length: ' . filesize($downloadAllFilePath));
        header('Pragma: public');

        //Clear system output buffer
        flush();

        //Read the size of the file
        readfile($downloadAllFilePath);
    } else {
        $error .= "LDIF oder Header Datei existiert nicht.";
    }
}

if (isset($_POST["password-download"])) {
    if (file_exists($passwordFilePath)) {
        //Define header information
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: 0");
        header('Content-Disposition: attachment; filename="' . basename($passwordFilePath) . '"');
        header('Content-Length: ' . filesize($passwordFilePath));
        header('Pragma: public');

        //Clear system output buffer
        flush();
        //Read the size of the file
        readfile($passwordFilePath);
    } else {
        $error .= "Passwortdatei existiert nicht.";
    }
}

if (file_exists($downloadFilePath)) {
    $convertedUsers = getPreviewData($downloadFilePath);
}



// $ds = ldap_connect("localhost");  // Annahme: der LDAP Server befindet
//                                 // sich auf diesem Host

// if ($ds) {
//     // bind mit passendem dn für aktualisierenden Zugriff
//     $r = ldap_bind($ds, "vmadmin", "gibbiX12345");

// Daten vorbereiten
// $info["cn"] = "Hans Mustermann";
// $info["sn"] = "Mustermann";
// $info["objectclass"] = "person";

// // hinzufügen der Daten zum Verzeichnis
// $r = ldap_add($ds, "cn=Hans Mustermann, o=Meine Firma, c=DE", $info);

// ldap_close($ds);
// } else {
//     echo "Verbindung zum LDAP Server nicht möglich!";
// }
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDIF Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BmbxuPwQa2lc/FVzBcNJ7UAyJxM6wuqIj61tLrc4wSX0szH/Ev+nYRRuWlolflfl" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="./css/style.css">
</head>

<body>
    <div class="container">
        <div class="row my-3">
            <div class="col-12 mt-4">
                <h1>LDIF Converter</h1>
            </div>

            <div class="col-12 <?php echo ($error || $message) ? "mt-4" : ""; ?>">
                <?php
                echo $error ? "<div class=\"alert alert-danger\" role=\"alert\">" . $error . "</div>" : "";
                echo $message ? "<div class=\"alert alert-success\" role=\"alert\">" . $message . "</div>" : "";
                ?>

            </div>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="col-12 mt-4">
                    <div class="input-group">
                        <input type="file" name="file-csv" class="form-control" accept=".csv" id="file-csv">
                        <button class="btn btn-primary" type="submit" id="upload-csv" name="upload-csv"><i class="bi bi-file-earmark-excel"></i> CSV Hochladen</button>
                    </div>
                    <div class="input-group mt-2">
                        <input type="file" name="file-header" class="form-control" id="file-header">
                        <button class="btn btn-primary" type="submit" id="upload-header" name="upload-header"><i class="bi bi-upload"></i> LDIF Header Hochladen</button>
                    </div>
                </div>
                <div class="col-12 mt-4 <?php echo !file_exists($uploadFilePath) ? "d-none" : ""; ?>">
                    <button type="submit" name="convert" class="btn btn-primary-soft float-start <?php echo false ? "d-none" : ""; ?>">
                        <i class="bi bi-arrow-repeat"></i>
                        Erneut Konvertieren
                    </button>

                    <button type="submit" name="ldif-download" class="btn btn-success float-end <?php echo !file_exists($downloadFilePath) ? "d-none" : ""; ?>">
                        <i class="bi bi-download"></i>
                        Benutzer LDIF-Datei
                    </button>
                    <button type="submit" name="all-download" class="btn btn-success float-end <?php echo !file_exists($downloadFilePath) ? "d-none" : "me-2"; ?>">
                        <i class="bi bi-download"></i>
                        Komplette LDIF-Datei
                    </button>
                    <button type="submit" name="password-download" class="btn btn-success float-end <?php echo !file_exists($downloadFilePath) ? "d-none" : "me-2"; ?>">
                        <i class="bi bi-download"></i>
                        Passwort-Liste
                    </button>
                </div>
            </form>
            <div class="col-12 mt-4 <?php echo !file_exists($downloadFilePath) ? "d-none" : ""; ?>">

                <h3>Vorschau</h3>
                <small>Zeigt nur die ersten fünf Benutzer an</small>

                <div class="bg-light mt-4 p-3">
                    <pre class="m-0"><code data-lang="html"><?php echo $convertedUsers; ?></code></pre>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/js/bootstrap.bundle.min.js" integrity="sha384-b5kHyXgcpbZJO/tY9Ul7kGkf1S0CWuKcCD38l8YkeH8z8QjE0GmW1gYU5S9FOnJ0" crossorigin="anonymous"></script>
    <script src="./js/main.js"></script>
</body>

</html>