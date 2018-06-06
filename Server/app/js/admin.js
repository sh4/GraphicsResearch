!function ($) {

if (window.GS === undefined) {
    window.GS = {};
}
if (window.GS.admin === undefined) {
    window.GS.admin = {};
}

function onError($el, text) {
    $el.siblings(".validate:first").show().text(text);
    $el.parent().addClass("has-danger");
}

function clearError($el) {
    $el.siblings(".validate:first").hide();
    $el.parent().removeClass("has-danger");
}

window.GS.admin.validateRules = {
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
        if (num % window.GS.admin.rowPerPage !== 0) {
            onError($el, "Number of scenes must be a multiple of " + window.GS.admin.rowPerPage + ".");
            return false;
        }
        if (num <= 0) {
            onError($el, "One or more the number of scenes.");
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
        if (num > 0 && num % window.GS.admin.rowPerPage !== 0) {
            onError($el, "Number of questions must be a multiple of " + window.GS.admin.rowPerPage + ".");
            return false;
        }
        clearError($el);
        return true;
    },
};

window.GS.admin.activateValidateRules = function () {
    for (var elemId in window.GS.admin.validateRules) {
        $(elemId).change(window.GS.admin.validateRules[elemId]);
    }    
};

}(jQuery);