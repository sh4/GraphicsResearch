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
$answerGroupId = "";
if (Crypto::isValidUniqueId(Form::request("gid", ""))) {
    $answerGroupId = Form::request("gid");
}
$gsParams = [
    "quizMode" => (int)Form::request("quizMode", 0) == 1,
    "unitId" => $unitId,
    "quizSid" => $quizSid,
    "quizUnitId" => $quizUnitId,
    "gid" => $answerGroupId,
];

// すでに作業が完了していれば、完了ページに遷移
if ($progress->remain <= 0) {
    Router::redirect("done", $gsParams);
}
$num = $page->getNumber();
$root = Router::Path();

function question(Page\Index $page, $models, $no) {
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
<link rel="stylesheet" type="text/css" href="<?php echo $root ?>/css/index.css">
<?php if ($page->isPaintMode()): ?>
<link rel="stylesheet" type="text/css" href="<?php echo $root ?>/css/paint.css">
<link rel="stylesheet" type="text/css" href="<?php echo $root ?>/vendor/font-awesome-4.7.0/css/font-awesome.css">
<?php endif ?>
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
$defaultQuestionPage = $page->getDefaultQuestionPage();
$instructions = $defaultQuestionPage["instructions"];
if ($job = $page->getJob()) {
    $instructions = $job->getQuestionInstructions();
}

foreach ($page->getQuestionOrders() as $i => $models):
    if ($num !== null && --$num < 0) {
        break;
    }
?>
<div class="question-item">
    <h2>No.<span class="question-no"><?php echo ($progress->answered+$i+1) ?></span></h2>
    <div style="margin-bottom:3em">
        <div class="question"><?php echo $instructions ?></div>
        <?php
        //question($page, $models, $i);
        ?>
        <table style="width:100%">
        <tr class="test-item">
            <td class="left-test-item">
                <img src="<?php echo $root ?>/css/loading.svg" class="question-loading">
                <div class="index-button">
                </div>
            </td>
            <td class="right-test-item">
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
$answerContext = $page->getAnswerContext();
if ($answeredLods = $answerContext->getAnsweredLods()):
    $modelId = $answerContext->getLastAnswer()["model_id"];
    foreach ($answeredLods as $lod):
?>
    <input type="hidden" name="answeredLods[]" value="<?php echo (int)$modelId ?>,<?php echo (int)$lod ?>">
<?php
    endforeach;
endif
?>
</div>

<div id="question-submit" style="display:none;margin-top:3em;">
    <input type="submit" id="question-submit-button" value="Submit" style="font-size:140%; padding: 0.8em; width:100%">
</div>

</form>

<script type="text/javascript">
!function () {

window.GS = {
    params: <?php echo json_encode($gsParams) ?>,
    num: <?php echo $page->getNumber() ?>,
    paint: {
        enabled: <?php echo json_encode($page->isPaintMode()) ?>,
    },
};

}();
</script>
<script type="text/javascript" src="<?php echo $root ?>/js/question.js"></script>

<?php if ($page->isPaintMode()): ?>
<script type="text/javascript" src="<?php echo $root ?>/js/paint.js"></script>
<?php endif ?>

</body>
</html>
