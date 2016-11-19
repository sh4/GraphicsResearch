<?php
use GraphicsResearch\Page;
use GraphicsResearch\Form;
use GraphicsResearch\Crypto;

$page = new Page\Index();
$progress = $page->getAnswerProgress();

$unitId = "";
if (Crypto::isValidUniqueId($page->getUnitId())) {
    $unitId = $page->getUnitId();
}
$quizUnitId = "";
if (Crypto::isValidUniqueId(Form::request("quizUnitId", ""))) {
    $quizUnitId = Form::request("quizUnitId");
}
$quizSid = "";
if (Crypto::isValidUniqueId(Form::request("quizSid", ""))) {
    $quizSid = Form::request("quizSid");
}
$gsParams = [
    "quizMode" => (int)Form::request("quizMode", 0) == 1,
    "unitId" => $unitId,
    "quizSid" => $quizSid,
    "quizUnitId" => $quizUnitId,
];

// すでに作業が完了していれば、完了ページに遷移
if ($progress->remain <= 0) {
    Router::redirect("done", $gsParams);
}
$num = $page->getNumber();
$root = Router::Path();

function question(Page\Index $page, $models, $no) {
    $questionPage = $page->getQuestionPage();
    $root = Router::Path();
    $modelOrder = array_keys($models);
    shuffle($modelOrder);
?>
    <table style="width:100%">
        <tr class="test-item">
            <?php
            foreach ($modelOrder as $order): 
                $model = $models[$order];
            ?>
            <td>
                <?php if (file_exists($model["path"])): ?>
                <div class="index-button">
                    <input autocomplete="off" type="radio" id="<?php echo $model["formId"] ?>" name="answer[<?php echo $no ?>]" value="<?php echo $model["formValue"] ?>">
                    <label for="<?php echo $model["formId"] ?>"><img class="question-image" src="<?php echo $root, "/", $model["path"]; ?>"></label>
                </div>
                <?php endif ?>
            </td>
            <?php endforeach ?>
        </tr>
    </tr>
    </table>
<?php
}

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" type="text/css" href="<?php echo $root ?>/index.css">
<script type="text/javascript" src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
<title>Test</title>
</head>
<body>

<form id="answer-form" method="post" action="<?php echo $page->getFormAction() ?>">

<?php foreach ($gsParams as $key => $value): ?>
<input type="hidden" name="<?php echo $key ?>" value="<?php Form::e($value) ?>">
<?php endforeach ?>

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
$questionPage = $page->getQuestionPage();
foreach ($page->getQuestionOrders() as $i => $models):
    if ($num !== null && --$num < 0) {
        break;
    }
?>
<div class="question-item">
    <h2>No.<span class="question-no"><?php echo ($progress->answered+$i+1) ?></span></h2>
    <div style="margin-bottom:3em">
        <div class="question"><?php echo $questionPage["instructions"] ?></div>
        <?php
        //question($page, $models, $i);
        ?>
        <table style="width:100%">
        <tr class="test-item">
            <td>
                <img src="<?php echo $root ?>/css/loading.svg" class="question-loading">
                <div class="index-button">
                </div>
            </td>
            <td>
                <img src="<?php echo $root ?>/css/loading.svg" class="question-loading">
                <div class="index-button">
                </div>
            </td>
        </tr>
        </table>
    </div>
</div>
<?php endforeach ?>

<div class="form-answered-lods">
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
</div>

<div style="margin-top:5em;<?php if ($page->getNumber() <= 1): ?>display:none<?php endif ?>">
    <input type="submit" id="question-submit" value="Submit" style="font-size:140%; padding: 0.8em; width:100%">
</div>

</form>

<script type="text/javascript">
!function () {

window.GS = {
    params: <?php echo json_encode($gsParams) ?>,
    num: <?php echo $page->getNumber() ?>,
};

}();
</script>
<script type="text/javascript" src="<?php echo $root ?>/js/question.js"></script>

</body>
</html>
