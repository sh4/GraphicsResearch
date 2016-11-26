!function ($) {

$(function () {

});

function preloadImage(url) {
    var d = $.Deferred();
    var img = new Image();
    img.onload = function () {
        d.resolve(img);
    };
    img.onerror = function () {
        d.reject();
    };
    img.src = url;
    return d.promise();
}

function updateProgress(progress) {
    $(".progress-label").text(progress.remain + " left");
    $(".progress-bar-content").css("width", (progress.answered / progress.total * 100).toFixed(3) + "%");
}

function createQuestionInput(model, index) {
    var $input = $($.parseHTML('<input autocomplete="off" type="radio">'));
    $input.prop({
        id: model.formId,
        name: "answer[" + index + "]",
        value: model.formValue,
    });
    return $input;
}

function updateQuestionItems(questions, progress) {
    var updateCompleted = new $.Deferred();
    var questionEls = Array.prototype.slice.call(document.querySelectorAll(".question-item"));

    questions.forEach(function (models, index) {
        if (index >= questionEls.length) {
            bufferedQuestions.push(models);
            return;
        }
        $.when.apply($, models.map(function (m) {
            return preloadImage(m.path).then(function (img) {
                return { img: img, model: m };                
            });
        })).then(function () {
            function showNewQuestion() {
                var dlist = [];
                $questionEl.find(".index-button").each(function (i, el) {
                    var preload = preloadModels[i];
                    var $img = $(preload.img).addClass("question-image").hide();
                    var $labelEl = $($.parseHTML('<label />'))
                        .prop("for", preload.model.formId)
                        .append($img);
                    $(el)
                        .append(createQuestionInput(preload.model, index))
                        .append($labelEl);
                    var d = new $.Deferred();
                    dlist.push(d.promise());
                    $img.fadeIn(300, function () {
                        $questionEl.find(".question-no").text(progress.answered + index + 1);
                        d.resolve();
                    });
                });
                $.when.apply($, dlist).then(function () {
                    updateCompleted.resolve();
                });
            }

            var preloadModels = Array.prototype.slice.call(arguments);
            var $questionEl = $(questionEls[index]);
            var $testItemEl = $questionEl.find(".test-item");
            $questionEl.find(".question-loading").hide();

            $questionEl.find(".index-button > input").remove();
            var $activeLabelEl = null;
            var $oldLabelEl = $questionEl.find(".index-button > label");
            $oldLabelEl.each(function (i, el) {
                var $labelEl = $(el);
                if ($labelEl.parents(".index-button:first").hasClass("active")) {
                    $activeLabelEl = $labelEl;
                } else {
                    $labelEl.animate({ opacity: 0.0 }, { duration: 300 });
                }
            });
            if ($activeLabelEl !== null) {
                setTimeout(function () {
                    $activeLabelEl.parents(".index-button:first").removeClass("active");
                    $activeLabelEl.animate({ opacity: 0.0 }, {
                        duration: 250,
                        complete: function () {
                            $oldLabelEl.remove();
                            showNewQuestion();
                        }
                    });
                }, 600);
            } else {
                showNewQuestion();
            }
        });
    });
    return updateCompleted.promise();
}

function updateAnsweredLods(answerContext) {
    if (!answerContext) {
        return;
    }
    var $formAnsweredLods = $(".form-answered-lods").empty();
    var modelId = answerContext.lastAnswer.model_id;
    answerContext.answeredLods.forEach(function (lod) {
        var $lodEl = $($.parseHTML('<input type="hidden" name="answeredLods[]">'));
        $lodEl.val([
            modelId,
            lod,
        ].join(","));
        $formAnsweredLods.append($lodEl);
    });

    var unitId = answerContext.lastAnswer.unit_id;
    if (window.GS.params.unitId != unitId) {
        // UnitId を最新の状態に更新
        window.GS.params.unitId = unitId;
        $('[name="unitId"]').val(window.GS.params.unitId);
    }
}

function toQueryString(params) {
    return Object.keys(params).map(function (key) {
        return [key, encodeURIComponent(params[key])].join("=");
    }).join("&");
}

function updateQuestions(questionRequest) {
    return questionRequest.then(function (r) {
        if (r.progress.completed) {
            var doneUrl = "index.php/done?" + toQueryString(fetchParams({
                isFetchLods: false,
            }));
            window.location.href = doneUrl;
            return;
        }
        var questions = [];
        if (bufferedQuestions.length > 0) {
            questions.push(bufferedQuestions.shift());
            r.questions.forEach(function (q) {
                bufferedQuestions.push(q);
            });
        } else {
            questions = r.questions;
        }
        updateQuestionItems(questions, r.progress).then(function () {
            updateProgress(r.progress);
            // ペイントUI を有効化
            if (window.GS.paint.enabled) {
                window.GS.paint.UI($(".right-test-item .index-button > label"));
            }
        });
        updateAnsweredLods(r.answerContext);
        // 画像の先読みを開始
        if (bufferedQuestions.length > 0) {
            bufferedQuestions[0].forEach(function (m) {
                new Image().src = m.path;
            });
        }
    });
}

function fetchParams(params) {
    return {
        fetchLods: params.isFetchLods ? 1 : 0,
        quizMode: window.GS.params.quizMode ? 1: 0,
        unitId: window.GS.params.unitId,
        quizSid: window.GS.params.quizSid || "",
        quizUnitId: window.GS.params.quizUnitId || "",
    };
}

var bufferedQuestions = [];

$("#answer-form").submit(function (event) {
    var form = this;
    var isFetchLods = false;
    if (bufferedQuestions.length <= 1) {
        isFetchLods = true;
    }
    updateQuestions($.ajax({
        url: "index.php/api/question?fetchLods=" + (isFetchLods ? 1 : 0),
        method: "POST",
        data: new FormData(form),
        processData: false,
        contentType: false,
    }));
    return false;
});

if (window.GS.num == 1) {
    $(".index-button").change(function () {
        $(this).addClass("active");
        $("#question-submit").click();
    });
}

updateQuestions($.getJSON("index.php/api/question", fetchParams({ isFetchLods: true })));

}(jQuery);
