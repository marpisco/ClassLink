document.addEventListener('DOMContentLoaded', function () {
  function disableSubmitButtons(form) {
    var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    buttons.forEach(function (btn) {
      // store original content for restoration (if needed)
      if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML || btn.value || '';
      // set disabled state
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      // replace content with spinner if using Bootstrap
      try {
        if (btn.tagName.toLowerCase() === 'button') {
          btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> A processar...';
        } else if (btn.tagName.toLowerCase() === 'input') {
          btn.value = 'A processar...';
        }
      } catch (e) {}
    });
  }

  document.querySelectorAll('form[data-prevent-double-submit]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      // prevent double processing if already submitted
      if (form.dataset.submitted === 'true') {
        ev.preventDefault();
        return false;
      }
      form.dataset.submitted = 'true';
      disableSubmitButtons(form);
      // allow submission to continue
    });
  });
});
