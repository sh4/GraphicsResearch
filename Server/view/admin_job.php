<?php

use GraphicsResearch\Form;

$unitStatusFilter = strtolower(Form::get("status", ""));

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
    <?php $progressPercent = min([100.0, round($job->getAnswerProgress() * 100, 2)]); ?>
    <tr class="<?php if ((int)$progressPercent >= 100): ?>table-success<?php endif ?>">
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

<div style="margin:1em">
    <?php $baseUrl = Router::Path("admin/jobs")."?jobId=".$job->getJobId() ?>
    <?php if ($unitStatusFilter === ""): ?>
        All
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>">All</a>
    <?php endif ?>
    |
    <?php if ($unitStatusFilter === "open"): ?>
        Open
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>&amp;status=open">Open</a>
    <?php endif ?>
    |
    <?php if ($unitStatusFilter === "judging"): ?>
        Judging
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>&amp;status=judging">Judging</a>
    <?php endif ?>
    |
    <?php if ($unitStatusFilter === "completed"): ?>
        Completed
    <?php else: ?>
        <a href="<?php echo $baseUrl ?>&amp;status=completed">Completed</a>
    <?php endif ?>
</div>

<table class="table table-hover">
<thead class="thead-inverse">
    <tr>
        <th>No.</th>
        <th>Status</th>
        <th># of Judged</th>
        <th>Last Judged</th>
        <th>Menu</th>
    </tr>
</thead>
<tbody>
<?php
$no = 0;
foreach ($job->getUnits() as $unit):
    $status = "";
    $judgedCount = count($unit->getJudgementData());

    if ($judgedCount >= $job->getQuestions()) {
        $status = "completed";
    } else if ($judgedCount > 0) {
        $status = "judging";
    } else {
        $status = "open";
    }
    if (!empty($unitStatusFilter) && $status !== $unitStatusFilter) {
        continue;
    }

    $no++;
?>
    <tr>
        <td><?php echo $no ?></td>
        <td>
            <?php if ($status === "completed"): ?>
                <span class="label label-pill label-success">COMPLETED</span>
            <?php elseif ($status === "judging"): ?>
                <span class="label label-pill label-primary">JUDGING</span>
            <?php elseif ($status === "open"): ?>
                <span class="label label-pill label-default">OPEN</span>
            <?php else: ?>
                <span class="label label-pill label-warning">INVALID</span>
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
