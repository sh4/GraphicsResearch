<?php
use GraphicsResearch\Page\Index;
use GraphicsResearch\Job;
use GraphicsResearch\Crypto;

$unit = Index::loadUnit();
$verificationCode = $unit->getVerificationCode();

// クイズモードならジョブの正答率に基づいて正しい答えを返すべきか判定
if (is_a($unit, "GraphicsResearch\\QuizUnit") 
    && ($jobId = $unit->getJobId())
    && ($job = Job::loadFromId($jobId)))
{
    // 正答率がジョブの要求水準に満たなければ偽の SurveryCode を返す
    if (!$unit->isTestPassed($job)) {
        $verificationCode = Crypto::createUniqueNumber(10);
    }
}

?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Test Completed!</title>
    <style type="text/css">
        body {
            margin: 1em;
            font-size: 130%;
            font-family: Arial;
        }
        h2 {
            font-size: 2em;
            margin: 0.5em;
        }
        #survey-code {
            border: 1px solid #aaa;
            padding: 0.2em;
            font-size: 250%;
            text-align: center;
        }
    </style>
</head>
<body>

<h1>Test Completed!</h1>

<?php if ($unit): ?>
<p>Copy &amp; Paste below survey code to CrowdFlower question page.</p>

<div style="text-align: center; margin: 0em auto; padding-bottom: 2em; border: 4px solid #606060; background: #eee">
    <h2>Survey Code</h2>
    <input type="text" id="survey-code" value="<?php echo $verificationCode ?>" onfocus="this.select()">
</div>
<?php else: ?>
<p>Invalid test state, Please contact website administrator.</p>
<?php endif ?>

</body>
</html>
