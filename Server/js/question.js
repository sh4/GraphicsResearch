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
    var d = new $.Deferred();
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
                $questionEl.find(".index-button").each(function (i, el) {
                    var preload = preloadModels[i];
                    var $img = $(preload.img).addClass("question-image").hide();
                    var $labelEl = $($.parseHTML('<label />'))
                        .prop("for", preload.model.formId)
                        .append($img);
                    $(el)
                        .append(createQuestionInput(preload.model, index))
                        .append($labelEl);
                    $img.fadeIn(300, function () {
                        $questionEl.find(".question-no").text(progress.answered + index);
                        d.resolve();
                    });
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
    return d.promise();
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

    // UnitId を最新の状態に更新
    $('[name="unitId"]').val(answerContext.lastAnswer.unit_id);
}

function updateQuestions(questionRequest) {
    return questionRequest.then(function (r) {
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
        });
        updateAnsweredLods(r.answerContext);
        if (bufferedQuestions.length > 0) {
            bufferedQuestions[0].forEach(function (m) {
                new Image().src = m.path;
            });
        }
    });
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

updateQuestions($.getJSON("index.php/api/question", {
    fetchLods: 1,
}));

}(jQuery);
