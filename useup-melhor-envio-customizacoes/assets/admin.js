document.addEventListener('DOMContentLoaded', function () {
  var container = document.getElementById('useup-me-rules');
  var addButton = document.getElementById('useup-me-add-rule');
  var template = document.getElementById('useup-me-rule-template');

  if (!container || !addButton || !template) {
    return;
  }

  addButton.addEventListener('click', function () {
    var nextIndex = parseInt(container.dataset.nextIndex || '0', 10);
    var markup = template.innerHTML.replace(/__index__/g, String(nextIndex));
    container.insertAdjacentHTML('beforeend', markup);
    container.dataset.nextIndex = String(nextIndex + 1);
  });

  container.addEventListener('click', function (event) {
    var target = event.target;

    if (!target.classList.contains('useup-me-remove-rule')) {
      return;
    }

    var rule = target.closest('.useup-me-rule');

    if (rule) {
      rule.remove();
    }
  });
});
