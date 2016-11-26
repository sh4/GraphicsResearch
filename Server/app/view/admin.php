<?php

use GraphicsResearch\Job;
use GraphicsResearch\Form;
use GraphicsResearch\Question;

$question = Question::buildFromModelDirectory(JUDGEMENT_IMAGES);
$invalidModelInfos = $question->invalidModelInfos();

$questionPage = GraphicsResearch\QuestionPage::DefaultPage();

?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Admin</title>
    <link rel="stylesheet" type="text/css" href="<?php echo Router::Path() ?>/css/bootstrap.css">
    <script type="text/javascript" src="<?php echo Router::Path() ?>/js/jquery-3.0.0.js"></script>
    <script type="text/javascript" src="<?php echo Router::Path() ?>/js/jquery-sortable-table.js"></script>
    <style type="text/css">
    label {
        display: block;
        margin: 0.5em 0;
    }
    .longfield {
        width: 100%;
    }
    textarea.longfield {
        height: 8em;
    }
    .numeric {
    }
    .validate {
        display: none;
    }
    </style>
</head>
<body>

<div class="container">

<a href="<?php echo Router::Path("admin/logout") ?>">Logout</a>

<h1>Admin</h1>

<?php include "_flash_alert.php" ?>

<h2>Question Page (Default)</h2>

<form method="post" id="form-update-question-page" action="<?php echo Router::Path("admin/question") ?>">
    <?php Form::enableCSRF() ?>

    <div class="form-group">
        <label for="question-instructions">Instructions (Default)</label>
        <textarea class="form-control longfield" id="question-instructions" name="question[instructions]"><?php Form::e($questionPage["instructions"]) ?></textarea>
    </div>

    <div class="form-group">
        <input id="submit-update-question-page" type="submit">
    </div>
</form>

<h2>Job List</h2>

<table class="table table-hover table-sorter job-list-table">
<thead class="thead-inverse">
    <tr>
        <th>Title</th>
        <th># of Scenes</th>
        <th># of Workers</th>
        <th>Progress</th>
        <th>Quiz PassRate</th>
        <th>Created Date</th>
        <th>Menu</th>
    </tr>
</thead>
<tbody>
<?php
foreach (Job::getJobs() as $job):
    $progressPercent = min([100.0, round($job->getProgress($question) * 100, 2)]);
    ?>
    <tr class="<?php if ((int)$progressPercent >= 100): ?>table-success<?php endif ?>">
        <td><a href="<?php echo Router::Path("admin/jobs") ?>/?jobId=<?php echo $job->getJobId() ?>"><?php Form::e($job->getTitle()) ?></a></td>
        <td>
            <?php echo $job->getQuestions() ?>
            (<?php echo $job->getQuestions() * $question->lodVariationCount() ?> questions)
        </td>
        <td><?php echo $job->getMaxAssignments() ?></td>
        <td><?php echo $progressPercent ?>%</td>
        <td>
            <?php if ($job->getQuizQuestionCount() > 0): ?>
            <?php
            $rate = $job->getQuizPassRate();
            echo min([100.0, round($rate->ratio * 100, 2)]);
            ?>%
            (<?php echo $rate->pass ?> / <?php echo $rate->total ?>)
            <?php else: ?>
            N/A
            <?php endif ?>
        </td>
        <td><?php echo $job->createdOn()->format("Y/m/d H:i:s") ?></td>
        <td>
            <ul class="nav">
                <li class="nav-item"><a class="nav-link" href="https://make.crowdflower.com/jobs/<?php echo $job->getCrowdFlowerJobId() ?>" target="_blank">CrowdFlower Job Page</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo Router::Path("download") ?>?jobId=<?php echo $job->getJobId() ?>">Download CSV</a></li>
                <li class="nav-item"><a class="nav-link launch-job" href="<?php echo Router::Path("admin/jobs/launch") ?>?jobId=<?php echo $job->getJobId() ?>&amp;channel[]=cf_internal">Launch Job (Internal)</a></li>
                <li class="nav-item"><a class="nav-link launch-job" href="<?php echo Router::Path("admin/jobs/launch") ?>?jobId=<?php echo $job->getJobId() ?>&amp;channel[]=cf_internal&amp;channel[]=on_demand">Launch Job (External &amp; Internal)</a></li>
                <li class="nav-item">
                    <form method="post" class="form-delete-job-page" action="<?php echo Router::Path("admin/jobs/delete") ?>">
                        <?php Form::enableCSRF() ?>
                        <input type="hidden" name="jobTitle" value="<?php echo $job->getTitle() ?>"> 
                        <input type="hidden" name="jobId" value="<?php echo $job->getJobId() ?>">
                        <button style="color:red; font-weight: bold">Delete Job</button>
                    </form>
                </li>
            </ul>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>

<h2><a href="<?php echo Router::Path("download") ?>" target="_blank">Download CSV (All)</a></h2>

<h2>Create New Job</h2>

<?php
$jobForm = Form::session("job");
if (!is_array($jobForm)) {
    $jobForm = [];
}
$jobForm = array_merge($jobForm, [
    "title" => "",
    "instructions" => "",
    "question_instructions" => $questionPage["instructions"],
    "questions" => "10",
    "max_assignments" => "10",
    "reward_amount_usd" => "0.10",
    "bonus_amount_usd" => "0.00",
    "quiz_accuracy_rate" => "70",
    "quiz_question_count" => "10",
    "task_type" => Job::TaskType_Choice,
]);
?>

<form method="post" enctype="multipart/form-data" id="form-create-new-job" action="<?php echo Router::Path("admin/jobs") ?>">
    <?php Form::enableCSRF() ?>

    <h3>Summary</h3>

    <div class="form-group">
        <label for="new-job-title">Title</label>
        <input type="text" class="form-control longfield" id="new-job-title" name="job[title]" value="<?php Form::e($jobForm["title"]) ?>">
        <label for="new-job-title" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <label for="new-job-instructions">Instructions(HTML)</label>
        <textarea class="form-control longfield" id="new-job-instructions" name="job[instructions]"><?php Form::e($jobForm["instructions"]) ?></textarea>
        <label for="new-job-instructions" class="form-control-label validate"></label>
    </div>

    <h3>Question Page</h3>

    <div class="form-group">
        <label for="new-job-question-instructions">Question Instructions (HTML)</label>
        <textarea class="form-control longfield" id="new-job-question-instructions" name="job[question_instructions]"><?php Form::e($jobForm["question_instructions"]) ?></textarea>
        <label for="new-job-question-instructions" class="form-control-label validate"></label>
    </div>

    <h3>Order</h3>

    <div class="form-group">
        <label for="new-job-task-type">Type</label>
        <select class="form-control" id="new-job-task-type" style="width:auto" name="job[task_type]">
            <option value="<?php echo Job::TaskType_Choice ?>"<?php if ($jobForm["task_type"] == Job::TaskType_Choice): ?> selected=""<?php endif ?>>Choice</option>
            <option value="<?php echo Job::TaskType_Painting ?>"<?php if ($jobForm["task_type"] == Job::TaskType_Painting): ?> selected=""<?php endif ?>>Painting</option>
        </select>
        <label for="new-job-task-type" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <label for="new-job-num-question"># of Scenes</label>
        <input type="text" class="form-control numeric" id="new-job-num-question" name="job[questions]" value="<?php Form::e($jobForm["questions"]) ?>">
        <label for="new-job-num-question" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <label for="new-job-num-assignments"># of Workers</label>
        <input type="text" class="form-control numeric" id="new-job-num-assignments" name="job[max_assignments]" value="<?php Form::e($jobForm["max_assignments"]) ?>">
        <label for="new-job-num-assignments" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <label for="new-job-reward-amount">Reward Cost</label>
        <input type="text" class="form-control numeric" id="new-job-reward-amount" name="job[reward_amount_usd]" style="width:6em;display:inline-block" value="<?php Form::e($jobForm["reward_amount_usd"]) ?>">
        &nbsp; USD/Worker
        <label for="new-job-reward-amount" class="form-control-label validate"></label>
    </div>

<?php /*
    <div class="form-group">
        <label for="new-job-bonus-amount">Bonus Reward Cost (Per Painting)</label>
        <input type="text" class="form-control numeric" id="new-job-bonus-amount" name="job[bonus_amount_usd]" style="width:6em;display:inline-block" value="<?php Form::e($jobForm["bonus_amount_usd"]) ?>">
        &nbsp; USD/Painting
        <label for="new-job-bonus-amount" class="form-control-label validate"></label>
    </div>
*/ ?>

    <div class="form-group">
        <label>Estimated Total Reward Cost</label>
        <div>
            <b><span id="total-job-cost" style="font-size:120%">0.00</span></b> USD
            <?php /*
            + 
            <b><span id="total-job-bonus-cost" style="font-size:120%">0.00</span></b> USD (Maximum Bonus)
            */ ?>
        </div>
    </div>
    <div class="form-group">
        <label>Estimated Total Answer Count</label>
        <div>
            <b><span id="total-questions" style="font-size:120%">0</span></b>
            (<span id="questions-per-worker">0</span> answers/worker)
        </div>
    </div>

    <h3>Quiz</h3>

    <div class="form-group">
        <label for="new-job-quiz-questions">Quiz Questions Dataset</label>
        <input type="file" id="new-job-quiz-questions" name="quiz_questions">
        <div style="margin:0.5em 1em">
        CSV File Examples ('filename' and 'Model is better than reference (1=true, 0=false)' list):<br>
        <pre style="font-size:90%;border:1px solid #606060;padding:0.5em">
00776_1_4_05028_09_0_104_099.jpg,0
00763_3_1_05022_01_1_351_011.jpg,1
</pre>
        </div>
    </div>

    <div class="form-group">
        <label for="new-job-quiz-question-count"># of Quiz Questions</label>
        <input type="text" class="form-control numeric" id="new-job-quiz-question-count" name="job[quiz_question_count]" style="width:6em;display:inline-block" value="<?php Form::e($jobForm["quiz_question_count"]) ?>">
        <label for="new-job-quiz-question-count" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <label for="new-job-quiz-accuracy-rate">Minimum Quiz Accuracy Rate</label>
        <input type="text" class="form-control numeric" id="new-job-quiz-accuracy-rate" name="job[quiz_accuracy_rate]" style="width:6em;display:inline-block" value="<?php Form::e($jobForm["quiz_accuracy_rate"]) ?>">
        %
        <label for="new-job-quiz-accuracy-rate" class="form-control-label validate"></label>
    </div>

    <div class="form-group">
        <input id="submit-create-new-job" type="submit">
    </div>
</form>

<h2>Scene Information</h2>

<h3>Summary</h3>

<table class="table table-hover">
<tbody>
    <tr>
        <th>Test available scene count</th>
        <td><?php echo $question->availableModelCount() ?></td>
    </tr>
</tbody>
</table>

<h3>File Operations</h3>

<form method="post" id="form-remove-question-images" action="<?php echo Router::Path("admin/question/remove") ?>">
    <?php Form::enableCSRF() ?>

    <div class="form-group">
        <label for="remove-file-pattern">Remove File Pattern (Available wildcards: '*' or '?')</label>
        <input class="form-control longfield" id="remove-file-pattern" name="remove_file_pattern" value="">
    </div>

    <div class="form-group">
        <input id="submit-update-question-page" type="submit" value="Delete images">
    </div>
</form>

<?php if (count($invalidModelInfos) > 0): ?>

<h3>Invalid DataSet (Not appear in question page)</h3>

<p>
<form method="post" action="<?php echo Router::Path("admin/question/remove") ?>">
    <?php Form::enableCSRF() ?>
    <input type="hidden" name="cleanup_invalid_dataset" value="1">
    <button>Delete invalid DataSet</button>
</form>
</p>

<table class="table table-hover">
<thead class="thead-inverse">
    <tr>
        <th>No.</th>
        <th>File</th>
        <th>Reason</th>
    </tr>
</thead>
<tbody>
<?php foreach ($invalidModelInfos as $i => $reason): ?>
    <tr>
        <td><?php echo ($i + 1) ?></td>
        <td>
            <ul>
            <?php foreach($reason["files"] as $file): ?>
            <li><strong><?php echo $file ?></strong></li>
            <?php endforeach ?>
            </ul>
        </td>
        <td><?php echo $reason["message"] ?></td>
    </tr>
<?php endforeach ?>
</tbody>
</table>

<?php endif ?>

</div>

<script type="text/javascript">
(function ($) {

function formatNumber(num) {
    return num.toString().replace(/(\d)(?=(\d{3})+$)/g , '$1,');
}

function onError($el, text) {
    $el.siblings(".validate:first").show().text(text);
    $el.parent().addClass("has-danger");
}

function clearError($el) {
    $el.siblings(".validate:first").hide();
    $el.parent().removeClass("has-danger");
}

var lodVariationCount = <?php echo $question->lodVariationCount() ?>;

var validateRules = {
    "#new-job-title": function () {
        var $el = $("#new-job-title");
        var titleLength = ($el.val() || "").length;
        if (titleLength < 5) {
            onError($el, "Enter at least 5 characters.");
            return false;
        } else if (titleLength > 255) {
            onError($el, "Enter less than 255 characters.");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#new-job-instructions": function () {
        var $el = $("#new-job-instructions");
        if ($el.val().length === 0) {
            onError($el, "Enter the job instructions.");
            return false;
        }
        clearError($el);
        return true;
    },
    "#new-job-question-instructions": function () {
        var $el = $("#new-job-question-instructions");
        if ($el.val().length === 0) {
            onError($el, "Enter the question page instructions.");
            return false;
        }
        clearError($el);
        return true;
    },
    "#new-job-num-question": function () {
        var $el = $("#new-job-num-question");
        var num = parseInt($el.val(), 10);
        var rowPerPage = <?php echo Job::crowdFlowerRowPerPage ?>;
        var availableSceneCount = <?php echo $question->availableModelCount() ?>;
        if (num % rowPerPage !== 0) {
            onError($el, "Number of scenes must be a multiple of " + rowPerPage + ".");
            return false;
        }
        if (num <= 0) {
            onError($el, "One or more the number of scenes.");
            return false;
        } else if (num > availableSceneCount) {
            onError($el, "Number of scenes must be less than " + availableSceneCount + ".");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#max_assignments": function () {
        var $el = $("#max_assignments");
        var num = parseInt($el.val(), 10);
        if (num <= 0) {
            onError($el, "One or more the assignments.");
            return false;
        } else if (num > 1000) {
            onError($el, "Less than 1,000 is the assignments.");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#new-job-reward-amount": function () {
        var $el = $("#new-job-reward-amount");
        var maxAssignments = parseInt($("#max_assignments").val(), 10);
        var amount = parseFloat($el.val());
        if (amount < 0.1) {
            onError($el, "Reward amount must be above 10 cents.");
            return false;
        } else if (amount * maxAssignments > 100) {
            onError($el, "Reward amount limit exceeded: 100 dollars");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#new-job-bonus-amount": function () {
        var $el = $("#new-job-bonus-amount");
        var maxAssignments = parseInt($("#max_assignments").val(), 10);
        var amount = parseFloat($el.val());
        if (amount < 0.01) {
            onError($el, "Bonus amount must be above 1 cents.");
            return false;
        } else if (amount > 10) {
            onError($el, "Bonus amount limit exceeded: 10 dollars");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#new-job-quiz-accuracy-rate": function () {
        var $el = $("#new-job-quiz-accuracy-rate");
        var accuracyRate = parseFloat($el.val());
        if (accuracyRate <= 0) {
            onError($el, "Quiz accuracy rate must be above 1%");
            return false;
        } else if (accuracyRate > 100) {
            onError($el, "Quiz accuracy rate less than or equal to 100%");
            return false;
        } else {
            clearError($el);
            return true;
        }
    },
    "#new-job-quiz-question-count": function () {
        var $el = $("#new-job-quiz-question-count");
        var num = parseInt($el.val(), 10);
        var rowPerPage = <?php echo Job::crowdFlowerRowPerPage ?>;
        if (num % rowPerPage !== 0) {
            onError($el, "Number of questions must be a multiple of " + rowPerPage + ".");
            return false;
        }
        clearError($el);
        return true;
    },
};

for (var elemId in validateRules) {
    $(elemId).change(validateRules[elemId]);
}

$("#form-create-new-job").submit(function () {
    for (var elemId in validateRules) {
        var ok = (validateRules[elemId])();
        if (!ok) {
            return false;
        }
    }
    // 二重 submit 防止
    $("#submit-create-new-job").prop("disabled", "disabled");
});

$(".job-list-table").on("click", ".launch-job", function () {
    var scope = "";
    var matches = $(this).text().match(/\([^\)]+\)/);
    if (matches !== null) {
        scope = matches[0];
    }
    return confirm("Are you want to launch the job? " + scope);
});

$("#form-remove-question-images").submit(function (e) {
    if (!confirm("Do you really want to delete images?")) {
        return false;
    }
});

$(".form-delete-job-page").submit(function (e) {
    var jobTitle = $(e.target).find('[name="jobTitle"]').val();
    if (!confirm("Do you really want to delete job '" + jobTitle + "' ?")) {
        return false;
    }
    if (!confirm("Deleted job data cannot be UNDO. Do you reaally want to delete job?")) {
        return false;
    }
});

function updateTotalCost() {
    var maxAssignments = parseInt($("#new-job-num-assignments").val(), 10);
    var totalCostUSD = (parseFloat($("#new-job-reward-amount").val(), 10) * maxAssignments);
    $("#total-job-cost").text(totalCostUSD.toFixed(2));

    //var maximumBonusCostUSD = (parseFloat($("#new-job-bonus-amount").val(), 10) * lodVariationCount * maxAssignments);
    //$("#total-job-bonus-cost").text(maximumBonusCostUSD.toFixed(2));
}

function updateTotalQuestions() {
    var questionsPerWorker = $("#new-job-num-question").val() * lodVariationCount;
    $("#questions-per-worker").text(formatNumber(questionsPerWorker));
    $("#total-questions").text(formatNumber(questionsPerWorker * parseInt($("#new-job-num-assignments").val(), 10)));
}

function refreshEtimsateNumbers() {
    updateTotalCost();
    updateTotalQuestions();
}

setInterval(refreshEtimsateNumbers, 500);
refreshEtimsateNumbers();

$(".table-sorter").sortableTable();

})(jQuery);
</script>

</body>
</html>
