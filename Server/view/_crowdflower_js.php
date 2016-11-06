require(['jquery-noconflict'], function() {

var jQuery = window.jQuery;

var isSecure = /https/i.test(location.href);
var verifyUrl = "<?php echo $url ?>".replace(/^https?/i, isSecure ? "https" : "http");

var message = "Checking survey code, Please wait a moment..";
var workerIdKey = "GraphicsResearch.Survery.workerId";
var workerId = window.sessionStorage.getItem(workerIdKey);
if (workerId === null) {
  workerId = Math.random().toString(16).substr(2) + (+new Date()).toString(36);
  window.sessionStorage.setItem(workerIdKey, workerId);
}

var surveyCodeConfirmed = {};
var surveyUnits = [];

!function () {
  jQuery(".cml").each(function (i, el) {
    var cmlInput = jQuery(".cml_row > input", el);
    var cmlField = jQuery(".cml_field", el);
    var linkEl = jQuery(".external-survey-site-link", el);
    var unit = { link: linkEl, input: cmlInput, field: cmlField };
    surveyUnits.push(unit);
  })
}();

!function () {
  surveyUnits.forEach(function (unit) {
    var href = unit.link.attr("href");
    href += "&workerId=" + workerId;
    unit.link.attr("href", href);
  });
}();

CMLFormValidator.addAllThese([
  ['ss-required', {
    errorMessage: function (elem) {
      return message;
    },
    validate: function(elem, props) {
      function pass() {
        var validator = elem.getParentForm().retrieve("validator");
        if (validator) {
          validator.validateField.pass([{type:"blur"}, elem], validator)();
        }
      }
      if (elem.retrieve("verifyFlash") == 1) {
        elem.store("verifyFlash", 0);
        return false;
      }
      if (!/^[0-9]+$/.test(elem.value)) {
        message = "Survey code is number sequence.";
        return false;
      }
      var $surveryCodeEl = jQuery(elem).parents("[data-unit-id]:first");
      var unitId = $surveryCodeEl.data("unit-id");
      if (surveyCodeConfirmed[unitId]) {
        return true;
      }
      var isGolden = $surveryCodeEl.data("is-golden");
      if (isGolden) {
        return true;
      }
      jQuery.getJSON(verifyUrl + "/verify?unitId=" + unitId + "&verificationCode=" + elem.value + "&workerId=" + workerId).then(function (r) {
        if (r.ok) {
          surveyCodeConfirmed[unitId] = true;
          pass();
        } else {
          elem.store("verifyFlash", 1);
          message = "Survey code is mismatch, Please check your input.";
          pass();
        }
      }, function () {
        elem.store("verifyFlash", 1);
        message = "Survey code check failed, Please retry later.";
        pass();
      });
      return false;
    }
  }]
]);

});
