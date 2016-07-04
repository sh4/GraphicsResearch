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
<script type="text/javascript" src="js/jquery-3.0.0.js"></script>
<link rel="stylesheet" type="text/css" href="index.css">
<title>Test</title>
</head>
<body>

<form method="post" action="<?php echo $action ?>">

<div class="answer-region">

<?php

function renderTestItem($no, $page, $model) {
    $modelId = $model["id"];
    shuffle($model["lod"]);
    $lod = $model["lod"][0];

    echo '<div style="margin-bottom:3em">';
    
    echo '<div class="question">Could you see ANY visible differences between left and right image.</div>';

    echo '<table style="width:100%">';

    $baseFile = $page->getModelPath($modelId, 0);
    $compareFile = $page->getModelPath($modelId, $lod);
    echo '<tr class="test-item">';
    echo '<td><img src="', $baseFile, '"></td>';
    echo '<td><img src="', $compareFile, '"></td>';
    echo '</tr>';

    echo "<tr>";
    echo '<td colspan="2" style="text-align: center; padding-top:2em">';

    $choices = \Model\TestConstants::JudgeList;
    foreach ($choices as $i => $ans) {
        $value = implode(",", [$modelId, $lod, $ans]);
        $id = "answer-form-$no-$i";

        echo '<div class="radio-button">';
        echo '<input autocomplete="off" type="radio" id="', $id, '" name="answer[', $no, ']" value="', $value, '">';
        echo '<label for="', $id, '">', $ans, '</label>';
        echo '</div>';
    }

    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
}

$displayTestCount = 0;
$num = $number;
foreach ($page->getRandomizeOrderTest() as $i => $model) {
    if ($num !== null && --$num < 0) {
        break;
    }
    $displayTestCount++;
    echo '<h2>No.', ($i+1), '</h2>';
    renderTestItem($i, $page, $model);
}
?>
</div>

<?php if ($displayTestCount === 0): ?>

<p>The test have been completed!</p>

<p><a href="index.php">New Test Session</a></p>

<?php elseif ($number > 1): ?>

<div style="margin-top:5em">
    <input type="submit" value="Submit" style="font-size:140%; padding: 0.8em; width:100%">
</div>

<?php endif ?>

</form>

<script type="text/javascript">
(function () {

<?php if ($number == 1): ?>
$(".radio-button").on("change", "input", function () {
    $(this).parents("form:first").submit();
});
<?php endif ?>

})();
</script>

</body>
</html>
