jQuery(document).ready(function ($) {
  'use strict';
  CodeMirror.fromTextArea(document.getElementById('sass_compiler_stylesheet'), {
    viewportMargin: Infinity,
    tabSize: 2,
    indentWithTabs: true,
    mode: 'text/x-scss'
  });
  var searchInput = $('#variable-search'),
    searchVariable = function (e) {
      var filter = searchInput.val().replace(/\s+/g, '-'),
        filterRow = function () {
          var varName = $('label', this).text();
          $(this).toggle(varName.search(filter) >= 0);
        };
      e.preventDefault();
      $('tr', '#sass_compiler_sass_variables').each(filterRow);
    };
  searchInput.keyup(searchVariable).on('search', searchVariable);
});
