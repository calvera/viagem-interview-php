/*
 * Evidence vlastniku - klientske skripty
 */

(function () {

  // potvrzeni pred smazanim
  document.addEventListener('click', function (e) {
    var el = e.target;
    if (el && el.getAttribute && el.getAttribute('data-confirm')) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    }
  });

  // naseptavac obci
  var inputs = document.querySelectorAll('input[data-autocomplete="municipality"]');

  for (var i = 0; i < inputs.length; i++) {
    initAutocomplete(inputs[i]);
  }

  function initAutocomplete(input) {
    var list = document.createElement('ul');
    var timer = null;
    var xhr = null;

    list.className = 'autocomplete';
    list.style.display = 'none';
    input.parentNode.appendChild(list);

    input.addEventListener('keyup', function (e) {
      if (e.keyCode === 13 || e.keyCode === 27) {
        list.style.display = 'none';
        return;
      }
      clearTimeout(timer);
      timer = setTimeout(load, 250);
    });

    input.addEventListener('blur', function () {
      setTimeout(function () { list.style.display = 'none'; }, 200);
    });

    function load() {
      var q = input.value;
      if (q.trim().length < 2) {
        list.style.display = 'none';
        return;
      }
      if (xhr) {
        xhr.abort();
      }
      xhr = new XMLHttpRequest();
      xhr.open('GET', 'ajax_municipalities.php?q=' + encodeURIComponent(q));
      xhr.onload = function () {
        if (xhr.status !== 200) {
          return;
        }
        var data = [];
        try {
          data = JSON.parse(xhr.responseText);
        } catch (err) {
          data = [];
        }
        render(data);
      };
      xhr.send();
    }

    function render(data) {
      list.innerHTML = '';
      if (!data.length) {
        list.style.display = 'none';
        return;
      }
      for (var j = 0; j < data.length; j++) {
        var li = document.createElement('li');
        li.textContent = data[j].label;
        li.setAttribute('data-value', data[j].value);
        li.addEventListener('mousedown', function () {
          input.value = this.getAttribute('data-value');
          list.style.display = 'none';
        });
        list.appendChild(li);
      }
      list.style.display = 'block';
    }
  }

})();
