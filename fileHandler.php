<?php
function writeArrayInFile(string $ldifFile, array $user)
{
    if (empty($ldifFile) || empty($user)) {
        return false;
    }

    $file = fopen($ldifFile, "a") or die("Datei konnte nicht geöffnet werden");

    foreach ($user as $line) {
        fwrite($file, $line . "\n");
    }
    fwrite($file, "\n");
    fclose($file);
    return true;
}

function writePasswordInDownloadFile(string $passwordFile, string $userLastname, string $userFirstname, string $username, string $password)
{
    if (empty($passwordFile) || empty($userLastname) || empty($userFirstname) || empty($password)) {
        return false;
    }

    $file = fopen($passwordFile, "a") or die("Datei konnte nicht geöffnet werden");

    fwrite($file, $userFirstname . "," . $userLastname . "," . $username . "," . $password . "\n");
    fclose($file);
}

function convertCSVtoLDIF(string $csvFile, string $ldifFile, string $passwordFile, string $groupFile)
{
    if (!file_exists($csvFile)) {
        return false;
    }

    $arr_readUsers = [];
    $arr_addedUsers = [];

    $arr_uidMemberAll = [
        "dn: cn=bg_alle-benutzer,ou=Gruppen,dc=gertzenstein,dc=local",
        "changetype: modify",
        "add: memberUid",
    ];

    $arr_uidMemberDeactivated = [
        "dn: cn=bg_deaktivierte-benutzer,ou=Gruppen,dc=gertzenstein,dc=local",
        "changetype: modify",
        "add: memberUid",
    ];

    // Delete download file
    if (file_exists($ldifFile)) {
        unlink($ldifFile);
    }

    if (file_exists($passwordFile)) {
        unlink($passwordFile);
    }

    if (file_exists($groupFile)) {
        unlink($groupFile);
    }

    $openCsvFile = fopen($csvFile, 'r');
    while (($userLine = fgetcsv($openCsvFile)) !== FALSE) {
        foreach ($userLine as $key => $value) {
            // Convert to UTF-8
            $userLine[$key] = iconv('ISO-8859-1', 'UTF-8', $value);
        }
        array_push($arr_readUsers, $userLine);
    }
    fclose($openCsvFile);

    $uidNumber = 10000;

    // Write User in File
    foreach ($arr_readUsers as $user) {
        $arr_tempUser = [];

        $password = generatePassword();
        $encryptedPassword = "{SHA}" . base64_encode(pack("H*", sha1($password)));

        // Find Duplicate
        $user[4] = strtolower(sonderzeichen($user[0]));
        $userNumber = 2;
        while (findDuplicateUser($user, $arr_addedUsers)) {
            if ($userNumber > 2) {
                $user[4] = substr($user[4], 0, -1);
            }
            $user[4] = $user[4] . $userNumber;
            $userNumber++;
        }

        $userOu = "";

        $uidName = strtolower(sonderzeichen($user[1])) . "." . strtolower($user[4]);
        // Deaktiviert
        if ($user[2] != "Deaktiviert") {
            $userOu = ",ou=Benutzer";
            $gidNumber =  10001;
            $arr_uidMemberAll[] = "memberUid: " . $uidName;
        } else {
            $gidNumber = 10000;
            $arr_uidMemberDeactivated[] = "memberUid: " . $uidName;
        }


        $arr_tempUser[1] = "dn: uid=" . $uidName . ",ou=" . $user[2] . $userOu . ",dc=gertzenstein,dc=local";
        $arr_tempUser[2] = "objectClass: inetOrgPerson";
        $arr_tempUser[3] = "objectClass: organizationalPerson";
        $arr_tempUser[4] = "objectclass: posixAccount";
        $arr_tempUser[5] = "objectClass: person";
        $arr_tempUser[6] = "objectClass: top";
        $arr_tempUser[7] = "uid: " . $uidName;
        $arr_tempUser[8] = "givenName: " . $user[1];
        $arr_tempUser[9] = "sn: " . $user[0];
        $arr_tempUser[10] = "cn: " . $user[1] . " " . $user[0];
        $arr_tempUser[11] = "mail: " . $uidName . "@gertzenstein.local";
        $arr_tempUser[12] = "userPassword: " . $encryptedPassword;
        $arr_tempUser[13] = "description: " . $user[3];
        $arr_tempUser[14] = "displayName: " . $user[0] . ", " . $user[1];
        $arr_tempUser[15] = "uidNumber: " . $uidNumber;
        $arr_tempUser[16] = "gidNumber: " . $gidNumber;
        $arr_tempUser[17] = "homeDirectory: /home/" . $uidName;

        writeArrayInFile($ldifFile, $arr_tempUser);
        writePasswordInDownloadFile($passwordFile, $user[1], $user[0], $uidName, $password);
        $arr_addedUsers[] = $user;
        $uidNumber++;
    }

    if (count($arr_uidMemberAll) > 3) {
        writeArrayInFile($groupFile, $arr_uidMemberAll);
    }

    if (count($arr_uidMemberDeactivated) > 3) {
        writeArrayInFile($groupFile, $arr_uidMemberDeactivated);
    }

    return true;
}

function createCompleteList(string $ldifFile, string $headerFile, string $groupFile, string $allFile)
{
    if (file_exists($ldifFile) && file_exists($headerFile)) {
        $contents = [];
        $contents[] = file_get_contents($headerFile);
        $contents[] = file_get_contents($ldifFile);

        if (file_exists($groupFile)) {
            $contents[] = file_get_contents($groupFile);
        }

        $content = implode("\n", $contents);
        file_put_contents($allFile, $content);
    }
}

function findDuplicateUser(array $user, array $arr_addedUsers)
{
    foreach ($arr_addedUsers as $addedUser) {
        if (strtolower($addedUser[4]) === strtolower($user[4]) && $addedUser[1] === $user[1]) {
            return true;
        }
    }
    return false;
}

function getPreviewData(string $ldifFile)
{
    $arr_lines = array_slice(file($ldifFile), 0, 71);
    $arr_converted = implode($arr_lines);
    return $arr_converted;
}

function generatePassword()
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $arr_password = []; //remember to declare $password as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache

    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $arr_password[] = $alphabet[$n];
    }

    return implode($arr_password); //turn the array into a string
}

function sonderzeichen($string)
{
    $search = array("Ä", "Ö", "Ü", "É", "È", "ä", "ö", "ü", "é", "è", "ß", "´");
    $replace = array("Ae", "Oe", "Ue", "E", "E", "ae", "oe", "ue", "e", "e", "ss", "");
    return str_replace($search, $replace, $string);
}
