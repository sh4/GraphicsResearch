!function ($) {

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

            $questionEl.find(".index-button input").remove();
            var $activeLabelEl = null;
            var $oldLabelEl = $questionEl.find(".index-button label");
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
                $oldLabelEl.remove();
                showNewQuestion();
            }
        });
    });
    return updateCompleted.promise();
}

function updateAnsweredLods(answerContext) {
    var lastAnswer = answerContext.lastAnswer;
    var answeredLods = answerContext.answeredLods || [];
    if (!lastAnswer || answeredLods.length === 0) {
        return;
    }
    var $formAnsweredLods = $(".form-answered-lods").empty();
    var modelId = lastAnswer.model_id;
    answeredLods.forEach(function (lod) {
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
            if (paintingCanvasUI === null && window.GS.paint.enabled) {
                paintingCanvasUI = window.GS.paint.UI($(".right-test-item > .index-button"));
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

var paintingCanvasUI = null;
var bufferedQuestions = [];

$("#answer-form").submit(function (event) {
    function update() {
        updateQuestions($.ajax({
            url: "index.php/api/question?fetchLods=" + (isFetchLods ? 1 : 0),
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
        }));
    }

    event.preventDefault();

    var form = this;
    var isFetchLods = false;
    if (bufferedQuestions.length <= 1) {
        isFetchLods = true;
    }
    var formData = new FormData(form);

    if (window.GS.paint.enabled) {
        var imageEl = $(".question-image")[1];
        var grayscale = paintingCanvasUI.toGrayScale(
            imageEl.naturalWidth,
            imageEl.naturalHeight);
        // グレースケールに占めるペイント率が 1% 以下ならちゃんと塗ってないとみなす
        if (grayscale.fillRatio <= 0.01) {
            alert("Please paint the character difference.");
            return;
        }
        PaintingCanvas.toBlob(grayscale.canvas, function (blob) {
            paintingCanvasUI.clear();
            formData.append("answer[0]", $(".right-test-item input").val());
            formData.append("paint[0][name]", "painting");
            formData.append("painting", blob, "paint.png");
            update();
        }, "image/png");
    } else {
        update();
    }

    return false;
});

if (window.GS.num == 1 && window.GS.paint.enabled == false) {
    $(".index-button").change(function () {
        $(this).addClass("active");
        $("#question-submit-button").click();
    });
} else {
    $("#question-submit").show();
}

updateQuestions($.getJSON("index.php/api/question", fetchParams({ isFetchLods: true })));

}(jQuery);
