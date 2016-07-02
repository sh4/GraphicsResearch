<?php require_once dirname(__FILE__)."/env.php";

$page = new Page\Index();

$number = (int)Form::get("num", 100);
$action = "index.php";
if ($number !== null && $number > 0) {
    $action .= "?num=$number";
}

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" type="text/css" href="index.css">
<title>Test</title>
</head>
<body>

<form method="post" action="<?php echo $action ?>">
<input type="hidden" name="testSessionId" value="<?php echo $page->getSessionId() ?>">

<div class="answer-region">

<?php
$displayTestCount = 0;
foreach ($page->getRandomizeOrderTest() as $i => $model) {
    if ($number !== null && --$number < 0) {
        break;
    }

    $displayTestCount++;

    echo '<h2>No.', ($i+1), '</h2>';

    $modelId = $model["id"];
    $lods = $model["lod"];

    shuffle($lods);
    $lod1 = 0;
    $lod2 = $lods[0];

    $file1 = $page->getModelPath($modelId, $lod1);
    $file2 = $page->getModelPath($modelId, $lod2);

    echo '<div style="margin-bottom:3em">';
    echo '<table style="width:100%">';
    echo '<tr>';

    echo '<td style="width:50%; text-align: center"><img style="width:100%" src="', $file1, '"></td>';
    echo '<td style="width:50%; text-align: center"><img style="width:100%" src="', $file2, '"></td>';

    echo '</tr>';
    echo '<tr>';

    echo '<td colspan="2" style="text-align: center; padding-top:2em">';

    echo '<div class="question">Could you see ANY visible differences between left and right image.</div>';

    foreach (\Model\TestConstants::JudgeList as $ans) {
        $value = implode(",", [$modelId, $lod2, $ans]);
        $id = "answer-form-$i-$ans";
        echo '<div class="radio-button">';
        echo '<input autocomplete="off" type="radio" id="', $id, '" name="answer[', $i, ']" value="', $value, '">';
        echo '<label for="', $id, '">', $ans, '</label>';
        echo '</div>';
    }

    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
}
?>
</div>

<?php if ($displayTestCount === 0): ?>

<p>The test have been completed!</p>

<p><a href="index.php">New Test Session</a></p>

<?php else: ?>

<div style="margin-top:5em">
    <input type="submit" value="Submit" style="font-size:140%; padding: 0.8em; width:100%">
</div>

<?php endif ?>

</form>

<script type="text/javascript">
(function () {
})();
</script>

</body>
</html>
