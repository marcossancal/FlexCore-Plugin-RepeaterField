<script>
(function () {
  'use strict';

  /**
   * Adiciona uma nova linha ao repetidor.
   *
   * @param {number} fid       — ID do campo
   * @param {Array}  subfields — definições dos subcampos (do options_json)
   * @param {number} maxRows   — limite de linhas (0 = sem limite)
   */
  window.rpAddRow = function (fid, subfields, maxRows) {
    var rowsEl = document.getElementById('rp-rows-' + fid);
    var tpl    = document.getElementById('rp-tpl-' + fid);
    if (!rowsEl || !tpl) return;

    var current = rowsEl.querySelectorAll('.rp-row').length;
    if (maxRows > 0 && current >= maxRows) {
      var hint = document.getElementById('rp-hint-' + fid);
      if (hint) { hint.style.color = 'var(--rd)'; setTimeout(function () { hint.style.color = ''; }, 1800); }
      return;
    }

    var html = tpl.innerHTML.replace(/__IDX__/g, String(current));
    var tmp  = document.createElement('div');
    tmp.innerHTML = html;
    var row = tmp.firstElementChild;
    rowsEl.appendChild(row);
    var first = row.querySelector('input, select, textarea');
    if (first) first.focus();

    rpUpdateAddBtn(fid, maxRows, current + 1);
  };

  /**
   * Remove uma linha e renumera os name[] dos inputs restantes.
   *
   * @param {HTMLElement} btn — botão ✕ clicado
   * @param {number}      fid — ID do campo
   */
  window.rpRemoveRow = function (btn, fid) {
    var rowsEl = document.getElementById('rp-rows-' + fid);
    var row    = btn.closest('.rp-row');
    if (!rowsEl || !row) return;

    row.remove();

    // Re-numera os índices nos atributos name
    var rows = rowsEl.querySelectorAll('.rp-row');
    rows.forEach(function (r, newIdx) {
      r.setAttribute('data-gi', newIdx);
      r.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace(/\[\d+\](\[[^\]]+\])$/, '[' + newIdx + ']$1');
      });
    });

    var wrap   = document.getElementById('rp-' + fid);
    var maxRows = wrap ? parseInt(wrap.dataset.max || '0') : 0;
    rpUpdateAddBtn(fid, maxRows, rows.length);
  };

  /**
   * Habilita/desabilita o botão de adicionar linha conforme max_rows.
   */
  function rpUpdateAddBtn(fid, maxRows, current) {
    var btn = document.querySelector('#rp-' + fid + ' .rp-add-btn');
    if (!btn || maxRows <= 0) return;
    btn.disabled      = current >= maxRows;
    btn.style.opacity = current >= maxRows ? '0.4' : '';
  }
})();
</script>
