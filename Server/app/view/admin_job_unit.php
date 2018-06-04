<?php
use GraphicsResearch\Question;
use GraphicsResearch\JobUnit;
use GraphicsResearch\Form;
use GraphicsResearch\Job;

$question = Question::instance();
$units = [];
$jobId = (int)Form::get("jobId", 0);
$job = Job::loadFromId($jobId);
$isChoiceMode = 
    $job->getTaskType() === Job::TaskType_Choice ||
    $job->getTaskType() === Job::TaskType_ThresholdJudgement;
$unitId = Form::get("unitId", "");
if ($answerGroupId = Form::get("gid", "")) {
    $units = JobUnit::loadsFromAnswerGroupId($answerGroupId);
} else {
    $units[] = JobUnit::loadFromId($unitId);
}
$root = \Router::Path();
$judgementFilter = Form::get("filter", "");
?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Judgement Detail - Job: <?php Form::e($job->getTitle()) ?> - Admin</title>
    <link rel="stylesheet" type="text/css" href="<?php echo Router::Path() ?>/css/bootstrap.css">
    <script type="text/javascript" src="<?php echo Router::Path() ?>/js/jquery-3.0.0.js"></script>
    <style type="text/css">
        .judgement-table {
            border-spacing: 0;
            margin-bottom: 1em;
        }
        .judgement-table img {
            width: 100%;
            height: inherit;
            padding: 0em;
            border: 5px solid #aaa;
        }
        .judgement-table td {
            padding: 0 0.5em;
        }
        .judgement-table td.active > img {
            border: 5px solid red;
        }
        .user-painting-image {
            border: none;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0.35;
        }
    </style>
</head>
<body>

<div class="container">

<a href="<?php echo Router::Path("admin/jobs")?>?jobId=<?php echo $jobId ?>">Return Job page</a>

<h1>Judgement Detail</h1>

<div style="margin:1em">
    <?php $baseUrl = Router::Path("admin/jobs/unit")."?jobId=$jobId&amp;unitId=$unitId" ?>
    <?php if ($judgementFilter === ""): ?>
        All
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>">All</a>
    <?php endif ?>
    <?php if ($isChoiceMode): ?>
        |
        <?php if ($judgementFilter === "ref"): ?>
            Reference is better
        <?php else: ?>
            <a href="<?php echo $baseUrl ?>&amp;filter=ref">Reference is better</a>
        <?php endif ?>
        |
        <?php if ($judgementFilter === "comp"): ?>
            Comparison is better
        <?php else: ?>
            <a href="<?php echo $baseUrl ?>&amp;filter=comp">Comparison is better</a>
        <?php endif ?>
    <?php endif ?>
</div>

<?php
$index = 0;
foreach ($units as $unit):
?>

    <?php
    foreach ($unit->getJudgementData() as $data):
        $index++;
        $modelId = $data["model_id"];
        $modelLod = $data["lod"];
        $modelRotation = $data["rotation_id"];
        $modelIsBetterThanRef = $data["is_better_than_ref"] == 1;
        if ($judgementFilter === "ref" && $modelIsBetterThanRef):
            continue;
        elseif ($judgementFilter === "comp" && !$modelIsBetterThanRef):
            continue;
        endif
    ?>
    <table class="judgement-table">
    <thead>
        <tr>
            <td colspan="2" style="height:2em;border:2px solid #666; background: #eee;">No. <?php echo $index ?></td>
        </tr>
        <tr>
            <th>Reference</th>
            <th>
                <?php if ($isChoiceMode): ?>
                Comparison
                <?php else: ?>
                User Painting
                <?php endif ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>LOD 0</td>
            <td>LOD <?php echo $modelLod ?></td>
        </tr>
        <tr>
            <td<?php if ($isChoiceMode && !$modelIsBetterThanRef): ?> class="active"<?php endif ?>><img src="<?php echo $root, "/../", $question->modelPath($modelId, $modelRotation, 0); ?>"></td>
            <td<?php if ($isChoiceMode && $modelIsBetterThanRef): ?> class="active"<?php endif ?> style="position:relative">
                <img class="reference-model-image" src="<?php echo $root, "/../", $question->modelPath($modelId, $modelRotation, $modelLod); ?>">
                <?php if ($job->getTaskType() === Job::TaskType_Painting): ?>
                    <a class="download-painting" href="javascript:void(0)"><img src="<?php echo $root, "/../", $unit->getPaintingFilePathFromJudgement($data) ?>" class="user-painting-image"></a>
                <?php endif ?>
            </td>
        </tr>
    </tbody>
    </table>
    <?php endforeach ?>

<?php endforeach ?>

</div>

<script type="text/javascript">
!function ($) {

function renderHighlightBrush(texture, img) {
    texture.width = img.naturalWidth;
    texture.height = img.naturalHeight;
    var ctx = texture.getContext("2d");
    ctx.clearRect(0, 0, texture.width, texture.height);
    ctx.drawImage(img, 0, 0);
    var image = ctx.getImageData(0, 0, texture.width, texture.height);
    var imageData = image.data; // rgbargba..
    var imageLength = imageData.length;
    for (var i = 0; i < imageLength; i += 4) {
        if (imageData[i] > 0) {
            imageData[i+1] = imageData[i];
            imageData[i+2] = 0;
            imageData[i+3] -= 192;
        }
    }
    image.data = imageData;
    ctx.putImageData(image, 0, 0);
}

$(".download-painting").click(function () {
    var $referenceModelImg = $(this).parents("td:first").find(".reference-model-image");
    var referenceModel = {
        width: $referenceModelImg[0].naturalWidth,
        height: $referenceModelImg[0].naturalHeight,
    };
    var $userPaintingImg = $(this).find(".user-painting-image");
    var texture = document.createElement("canvas");

    renderHighlightBrush(texture, $userPaintingImg[0]);

    var canvas = document.createElement("canvas");
    canvas.width = referenceModel.width;
    canvas.height = referenceModel.height;
    var ctx = canvas.getContext("2d");
    ctx.drawImage($referenceModelImg[0], 0, 0);
    ctx.drawImage(texture, 0, 0);

    var dataHeader = "data:image/jpeg;";
    var dataImage = canvas.toDataURL("image/jpeg");
    var downloadImage = "data:application/octet-stream;";
    downloadImage += dataImage.substr(dataHeader.length - 1);

    if (/([0-9_\-a-z]+\.jpe?g)$/ig.test($referenceModelImg.attr("src"))) {
        this.href = downloadImage;
        this.download = RegExp.$1;
    }
});

}(jQuery);
</script>

</body>
</html>
