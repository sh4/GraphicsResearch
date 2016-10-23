<?php

use GraphicsResearch\Form;

?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Job: <?php Form::e($job->getTitle()) ?> - Admin</title>
    <link rel="stylesheet" type="text/css" href="<?php echo Router::Path() ?>/css/bootstrap.css">
    <script type="text/javascript" src="<?php echo Router::Path() ?>/js/jquery-3.0.0.js"></script>
    <style type="text/css">
        td {
            vertical-align:  middle;
        }
        .label {
            width: 8em;
        }
    </style>
</head>
<body>

<div class="container">

<p><a href="<?php echo Router::Path("admin") ?>">Return Job list</a></p>

<h1><?php Form::e($job->getTitle()) ?></h1>

<table class="table table-hover">
    <thead class="thead-inverse">
    <tr>
        <th>Total Reward Amount</th>
        <th># of Questions</th>
        <th># of Max Assignments</th>
        <th>Progress (%)</th>
        <th>Created Date</th>
        <th>Menu</th>
    </tr>
    </thead>
    <tbody>
    <?php $progressPercent = round($job->getAnswerProgress() * 100, 2) ?>
    <tr class="<?php if ((int)$progressPercent === 100): ?>table-success<?php endif ?>">
        <td>
            <?php echo sprintf("%.2f", $job->estimateTotalAmountUSD()) ?> USD
            (<?php echo $job->getMaxAssignments() ?> * <?php echo $job->getRewardAmountUSD() ?> USD)
        </td>
        <td><?php echo $job->getQuestions() ?></td>
        <td><?php echo $job->getMaxAssignments() ?></td>
        <td><?php echo $progressPercent ?></td>
        <td><?php echo $job->createdOn()->format("Y/m/d H:i:s") ?></td>
        <td>
            <ul class="nav">
                <li class="nav-item"><a class="nav-link" href="https://make.crowdflower.com/jobs/<?php echo $job->getCrowdFlowerJobId() ?>" target="_blank">CrowdFlower Job Page</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo Router::Path("download") ?>?jobId=<?php echo $job->getJobId() ?>">Download CSV</a></li>
                <li class="nav-item"><a class="nav-link launch-job" href="<?php echo Router::Path("admin/jobs/launch") ?>?jobId=<?php echo $job->getJobId() ?>&amp;channel[]=cf_internal">Launch Job (Internal)</a></li>
                <li class="nav-item"><a class="nav-link launch-job" href="<?php echo Router::Path("admin/jobs/launch") ?>?jobId=<?php echo $job->getJobId() ?>&amp;channel[]=cf_internal&amp;channel[]=on_demand">Launch Job (External &amp; Internal)</a></li>
            </ul>
        </td>
    </tr>
    </tbody>
</table>

<h2>Job Unit List</h2>

<table class="table table-hover">
<thead class="thead-inverse">
    <tr>
        <th>Status</th>
        <th># of Judged</th>
        <th>Last Judged</th>
        <th>Menu</th>
    </tr>
</thead>
<tbody>
<?php
foreach ($job->getUnits() as $unit):
    $judgedCount = count($unit->getJudgementData());
?>
    <tr>
        <td>
            <?php if ($judgedCount === $job->getQuestions()): ?>
                <span class="label label-pill label-success">COMPLETED</span>
            <?php elseif ($judgedCount > 0): ?>
                <span class="label label-pill label-primary">JUDGING</span>
            <?php else: ?>
                <span class="label label-pill label-default">OPEN</span>
            <?php endif ?>
        </td>
        <td><?php echo $judgedCount ?></td>
        <td><?php
            if ($judged = $unit->getLastJudged()):
                echo $judged->format("Y/m/d H:i:s");
            endif
        ?></td>
        <td>
            <ul class="nav">
                <li class="nav-item"><a class="nav-link" href="<?php echo Router::Path("/") ?>?jobId=<?php echo $job->getJobId() ?>&amp;unitId=<?php echo $unit->getUnitId() ?>" target="_blank">Question Page (for DEBUG)</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo Router::Path("admin/jobs/unit") ?>?jobId=<?php echo $job->getJobId() ?>&amp;unitId=<?php echo $unit->getUnitId() ?>">Judgement Detail</a></li>
            </ul>
        </td>
    </tr>
<?php endforeach ?>
</tbody>
</table>

</div>

</body>
</html>
