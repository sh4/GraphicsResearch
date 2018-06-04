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
            if (!models[0].paint) {
                bufferedQuestions.push(models);
            }
            return;
        }
        var loadImages = models.map(function (m) {
            return preloadImage(m.path).then(function (img) {
                if (!m.mask) {
                    return { img: img, model: m };
                }
                // マスク画像が存在する場合は、そのフェッチも含めて質問画像のフェッチ処理の完了とみなす
                return preloadImage(m.mask).then(function (maskImg) {
                    return { img: img, model: m, maskImg: maskImg };
                });
            });
        });
        $.when.apply($, loadImages).then(function () {
            function showNewQuestion() {
                var dlist = [];
                $questionEl.find(".index-button").each(function (i, buttonEl) {
                    var preload = preloadModels[i];

                    if (preload.model.paint) {
                        window.GS.paint.onceEnabled = true;
                    }

                    var $img = $(preload.img).addClass("question-image").hide();
                    var $labelEl = $($.parseHTML('<label />'))
                        .prop("for", preload.model.formId)
                        .append($img);

                    $(buttonEl)
                        .append(createQuestionInput(preload.model, index))
                        .append($labelEl);

                    var d = new $.Deferred();
                    
                    $img.fadeIn(300, function () {
                        $questionEl.find(".question-no").text(progress.answered + index + 1);
                        d.resolve();
                    });

                    if (preload.maskImg) {
                        // ペイント UI からマスクデータを触るため DOM ツリーにはぶら下げておく
                        $labelEl.append($(preload.maskImg).addClass("paint-mask-image").hide());
                    }

                    dlist.push(d.promise());
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

            var $noDifferentEl = $questionEl.find(".test-item-no-different");
            if ($noDifferentEl.length > 0) {
                var differentId = "question-no-different-" + index;
                $noDifferentEl.find("input, label").remove();
                $noDifferentEl.append(createQuestionInput({
                    formId: differentId,
                    // 比較対象のモデルデータのうち、末尾(差異の有無. 差異が認められれば 1) フラグを 0 に変更
                    formValue: preloadModels[0].model.formValue.replace(/,[01],1$/, ",0,0"),
                }, index));
                $noDifferentEl.append($($.parseHTML('<label />')).prop("for", differentId).text("Looks same"));
            }

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
    lastAnswer = answerContext.lastAnswer;
    answeredLods = answerContext.answeredLods || [];

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
        // バッファ済みの質問がある場合は、その先頭を次の質問に加える
        if (isPaintContinue) {
            // ペイントを継続している場合、受信した質問を必ず次の質問に差し込む
            questions = r.questions;
            // 最大 LOD 数まで来ている場合、Submit しかさせない
            if (questions[0][0].lod >= window.GS.lodCount) {
                $("#question-continue-paint-button").attr("disabled", "disabled");
            } else {
                $("#question-continue-paint-button").removeAttr("disabled");
            }
        } else if (bufferedQuestions.length > 0) {
            questions.push(bufferedQuestions.shift());
            r.questions.forEach(function (q) {
                bufferedQuestions.push(q);
            });
        } else {
            questions = r.questions;
        }
        updateQuestionItems(questions, r.progress).then(function () {
            updateProgress(r.progress);
            if (window.GS.paint.onceEnabled) {
                window.GS.paint.enabled = true;
            }
            // ペイントUI を有効化
            if (window.GS.paint.enabled) {
                if (paintingCanvasUI === null) {
                    $(".index-button").addClass("painting");
                    paintingCanvasUI = window.GS.paint.UI($(".left-test-item > .index-button"));
                }
            } else {
                $(".index-button").removeClass("painting");                
            }
            reloadSubmitButtons();
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

function onIndexButtonChange() {
    $(this).addClass("active");
    $("#question-submit-button").click();
}

function onNoDifferentButtonChange() {
    $("#question-submit-button").click();
}

function reloadSubmitButtons() {
    $(".index-button").unbind("change", onIndexButtonChange);
    $(".test-item-no-different").unbind("change", onNoDifferentButtonChange);
    
    if (window.GS.num == 1 && window.GS.paint.enabled == false) {
        if (paintingCanvasUI != null) {
            paintingCanvasUI.dispose();
            paintingCanvasUI = null;
        }
        $(".index-button").bind("change", onIndexButtonChange);
        $(".test-item-no-different").show();
        $(".test-item-no-different").bind("change", onNoDifferentButtonChange);
        $("#question-submit").hide();
    } else {
        $(".test-item-no-different").hide();
        $("#question-submit").show();
    }

    // ペイント継続状態を解除
    isPaintContinue = false;
}

var paintingCanvasUI = null;
var bufferedQuestions = [];
var answeredLods = [];
var lastAnswer = null;
var isPaintContinue = false;

$("#answer-form").submit(function (event) {
    function update() {
        var url = "index.php/api/question?fetchLods=" + (isFetchLods ? 1 : 0);
        if (isPaintContinue) {
            url += "&paintContinue=1";
        }
        if (isFetchLods) {
            url += "&skipLods=" + bufferedQuestions.length;
        }
        updateQuestions($.ajax({
            url: url,
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
        }));
    }

    event.preventDefault();

    var form = this;

    var isFetchLods = false;
    if (!isPaintContinue && bufferedQuestions.length <= 0) {
        isFetchLods = true;
    }

    var formData = new FormData(form);

    if (window.GS.paint.enabled) {
        var imageEl = $(".question-image")[1];
        var grayscale = paintingCanvasUI.toGrayScaleAndMaskTest(
            imageEl.naturalWidth,
            imageEl.naturalHeight);
        // グレースケール時のチェックに失敗したか
        if (grayscale.ok === false) {
            alert("Please paint the character difference.");
            return;
        }
        PaintingCanvas.toBlob(grayscale.canvas, function (blob) {
            if (!isPaintContinue) {
                paintingCanvasUI.clear(); // ペイントを継続する場合はクリアしない
            }
            formData.append("answer[0]", $(".right-test-item input").val());
            formData.append("paint[0][name]", "painting");
            formData.append("painting", blob, "paint.png");
            update();
        }, "image/png");

        if (isPaintContinue) {
            return;            
        }
        // 一度だけペイントを有効化する
        if (window.GS.paint.onceEnabled) {
            window.GS.paint.enabled = false;
            window.GS.paint.onceEnabled = false;
        }
    } else {
        update();
    }

    return false;
});

$("#question-continue-paint-button").click(function () {
    isPaintContinue = true;
});

reloadSubmitButtons();
updateQuestions($.getJSON("index.php/api/question", fetchParams({ isFetchLods: true })));

}(jQuery);
