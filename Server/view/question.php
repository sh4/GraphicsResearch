<?php
use GraphicsResearch\Page;
use GraphicsResearch\Form;

$page = new Page\Index();
$progress = $page->getAnswerProgress();
// すでに作業が完了していれば、完了ページに遷移
if ($progress->remain === 0) {
    Router::redirect("done", ["quizMode" => Form::request("quizMode", 0)]);
}

function question(Page\Index $page, $models) {
    $questionPage = $page->getQuestionPage();
    $root = Router::Path();
    $modelOrder = array_keys($models);
    shuffle($modelOrder);
?>
    <div style="margin-bottom:3em">
    <div class="question">
    <?php echo $questionPage["instructions"] ?>
    </div>
    <table style="width:100%">
        <tr class="test-item">
            <?php
            foreach ($modelOrder as $order): 
                $model = $models[$order];
            ?>
            <td>
                <?php if (file_exists($model["path"])): ?>
                <div class="index-button">
                    <input autocomplete="off" type="radio" id="<?php echo $model["formId"] ?>" name="answer[<?php echo $model["no"] ?>]" value="<?php echo $model["formValue"] ?>">
                    <label for="<?php echo $model["formId"] ?>"><img src="<?php echo $root, "/", $model["path"]; ?>"></label>
                </div>
                <?php endif ?>
            </td>
            <?php endforeach ?>
        </tr>
    </tr>
    </table>
    </div>
<?php
}

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" type="text/css" href="<?php echo Router::Path() ?>/index.css">
<title>Test</title>
</head>
<body>

<form id="answer-form" method="post" action="<?php echo $page->getFormAction() ?>">

<input type="hidden" name="quizMode" value="<?php echo Form::request("quizMode", 0) == 1 ? 1 : 0 ?>">
<input type="hidden" name="quizSid" valuie="<?php Form::e(Form::request("quizSid")) ?>">
<input type="hidden" name="unitId" value="<?php Form::e($page->getUnitId()) ?>">

<div class="answer-region">

    <div style="margin:1.5em 0">
        <div class="progress-bar">
            <span class="progress-label"><?php echo $progress->remain ?> left</span>
            <div class="progress-bar-content" style="width:<?php echo round($progress->answered / $progress->total * 100.0, 2) ?>%">
                <span class="progress-label"><?php echo $progress->remain ?> left</span>
            </div>
        </div>
    </div>

<?php
$num = $page->getNumber();
foreach ($page->getQuestionOrders() as $i => $models) {
    if ($num !== null && --$num < 0) {
        break;
    }
    echo '<h2>No.', ($i+1), '</h2>';
    question($page, $models);
}
?>
</div>

<?php
if ($answerContext = $page->getAnswerContext()):
    $modelId = $answerContext["lastAnswer"]["model_id"];
    foreach ($answerContext["answeredLods"] as $lod):
?>
    <input type="hidden" name="answeredLods[]" value="<?php echo (int)$modelId ?>,<?php echo (int)$lod ?>">
<?php
    endforeach;
endif
?>

<?php if ($page->getNumber() > 1): ?>
    <div style="margin-top:5em">
        <input type="submit" value="Submit" style="font-size:140%; padding: 0.8em; width:100%">
    </div>
<?php endif ?>

</form>

<script type="text/javascript">
(function () {

<?php if ($page->getNumber() === 1): ?>
Array.prototype.slice.call(document.querySelectorAll(".index-button") || []).forEach(function (el) {
    el.addEventListener("change", function () {
        document.querySelector("#answer-form").submit();
    });
});
<?php endif ?>

})();
</script>

</body>
</html>
