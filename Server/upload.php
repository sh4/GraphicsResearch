<?php require_once dirname(__FILE__)."/env.php";

header("Content-Type: application/json; charset=utf-8");

$page = new Page\Upload();
$result = [ "ok" => false ];

if (!$page->isValidUploadKey()) {
    echo json_encode($result);
    exit;
}

if (Form::file("file")) {
    // upload file
    $result["ok"] = $page->uploadModelFile();
}
if (Form::post("ls")) {
    // list files
    $result["files"] = $page->listModelFile();
    $result["ok"] = true;
}
if (Form::post("rm")) {
    // remove files
    $removedFiles = $page->removeModelFile(Form::post("rm", []));
    if (!empty($removedFiles)) {
        $result["ok"] = true;
        $result["removedFiles"] = $removedFiles;
    }
}

echo json_encode($result);