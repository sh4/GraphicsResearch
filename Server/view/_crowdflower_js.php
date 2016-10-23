require(['jquery-noconflict'], function() {

var jQuery = window.jQuery;

var isSecure = /https/i.test(location.href);
var verifyUrl = "<?php echo $url ?>".replace(/^https?/i, isSecure ? "https" : "http");

var message = "Checking survey code, Please wait a moment..";
var isSurveyCodeConfirmed = false;

jQuery(function () {
    var linkEl = jQuery("#external-survey-site-link");
    var workerId = Math.random().toString(16).substr(2) + (+new Date()).toString(36);
    linkEl.attr("href", linkEl.attr("href") + "&workerId=" + workerId);
});

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
        if (isSurveyCodeConfirmed) {
          return true;
        }
        var unitId = jQuery("#external-survey-site-link").data("unit-id");
        jQuery.getJSON(verifyUrl + "/verify?unitId=" + unitId + "&verificationCode=" + elem.value).then(function (r) {
          if (r.ok) {
            isSurveyCodeConfirmed = true;
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
