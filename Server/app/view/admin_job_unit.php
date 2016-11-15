<?php
use GraphicsResearch\Question;
use GraphicsResearch\JobUnit;
use GraphicsResearch\Form;

$question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
$unit = JobUnit::loadFromId(Form::get("unitId", ""));
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
    </style>
</head>
<body>

<div class="container">

<a href="<?php echo Router::Path("admin/jobs")?>?jobId=<?php echo $unit->getJobId() ?>">Return Job page</a>

<h1>Judgement Detail</h1>

<div style="margin:1em">
    <?php $baseUrl = Router::Path("admin/jobs/unit")."?jobId=".$unit->getJobId()."&amp;unitId=".$unit->getUnitId() ?>
    <?php if ($judgementFilter === ""): ?>
        All
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>">All</a>
    <?php endif ?>
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
</div>

<?php
foreach ($unit->getJudgementData() as $data):
    $modelId = $data["model_id"];
    $modelLod = $data["lod"];
    $modelRotation = $data["rotation_id"];
    $modelIsBetterThanRef = $data["is_better_than_ref"] == 0;
    if ($judgementFilter === "ref" && $modelIsBetterThanRef):
        continue;
    elseif ($judgementFilter === "comp" && !$modelIsBetterThanRef):
        continue;
    endif
?>
<table class="judgement-table">
<thead>
    <tr>
        <th>Reference</th>
        <th>Comparison</th>
    </tr>
</thead>
<tbody>
    <tr>
        <td>LOD 0</td>
        <td>LOD <?php echo $modelLod ?></td>
    </tr>
    <tr>
        <td<?php if (!$modelIsBetterThanRef): ?> class="active"<?php endif ?>><img src="<?php echo $root, "/", $question->modelPath($modelId, $modelRotation, 0); ?>"></td>
        <td<?php if ($modelIsBetterThanRef): ?> class="active"<?php endif ?>><img src="<?php echo $root, "/", $question->modelPath($modelId, $modelRotation, $modelLod); ?>"></td>
    </tr>
</tbody>
</table>
<?php endforeach ?>

</div>

</body>
</html>