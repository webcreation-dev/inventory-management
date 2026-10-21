(function () {
  "use strict";
  if (document.body.getAttribute("data-lp") !== "BD") return;
  var pageTitle = document.title || "";
  document.title = "বিপনী - দোকানের সকল প্রকার হিসাব ব্যবস্থাপনা সফটওয়্যার। | " + pageTitle;
  var headerEl = document.getElementById("install-header-text");
  if (headerEl) headerEl.textContent = "বিপনী - দোকানের সকল প্রকার হিসাব ব্যবস্থাপনা সফটওয়্যার।";
  var el = document.getElementById("app-footer-text");
  if (el) el.textContent = "© " + new Date().getFullYear() + ", বিপণী - ডোর সফট কর্তৃক সর্বস্বত্ত্ব সংরক্ষিত";
})();
