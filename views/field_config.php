<?php
/**
 * views/field_config.php
 *
 * Renderizado pelo hook field.render_config.
 * Exibido na tela de campos quando o tipo selecionado for "repeater".
 */

$meta      = isset($field) && $field ? (json_decode($field['options_json'] ?? '{}', true) ?: []) : [];
$subfields = $meta['subfields'] ?? [['slug' => '', 'label' => '', 'type' => 'text', 'required' => false, 'options' => []]];
$minRows   = (int) ($meta['min_rows'] ?? 1);
$maxRows   = (int) ($meta['max_rows'] ?? 0);

// Array simples para o JS — chave numérica para json_encode gerar array JS
$subfieldTypeList = [
    ['value' => 'text',     'label' => '🔤 Texto'],
    ['value' => 'textarea', 'label' => '📝 Texto longo'],
    ['value' => 'number',   'label' => '🔢 Número'],
    ['value' => 'currency', 'label' => '💰 Moeda'],
    ['value' => 'percent',  'label' => '% Percentual'],
    ['value' => 'select',   'label' => '▼ Seleção'],
    ['value' => 'date',     'label' => '📅 Data'],
    ['value' => 'email',    'label' => '✉️ E-mail'],
    ['value' => 'phone',    'label' => '📞 Telefone'],
    ['value' => 'url',      'label' => '🔗 URL'],
    ['value' => 'checkbox', 'label' => '✅ Checkbox'],
];
$subfieldTypeMap = array_column($subfieldTypeList, 'label', 'value');
?>

<div id="rp-config-wrap" style="display:none;margin-top:4px">

  <div style="font-size:.78rem;font-weight:700;color:var(--mt2);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">
    🔁 Subcampos do Repetidor
  </div>

  <div id="rp-subfields">
    <?php foreach ($subfields as $i => $sf): ?>
    <div class="rp-cfg-row field" data-idx="<?= $i ?>">

      <input type="text"
             name="rp_slug[]"
             value="<?= h($sf['slug'] ?? '') ?>"
             placeholder="slug_campo"
             pattern="[a-z0-9_]+"
             title="Minúsculas, números e _"
             required>

      <input type="text"
             name="rp_label[]"
             value="<?= h($sf['label'] ?? '') ?>"
             placeholder="Rótulo"
             required>

      <select name="rp_type[]" onchange="rpCfgToggleOpts(this, <?= $i ?>)">
        <?php foreach ($subfieldTypeList as $st): ?>
        <option value="<?= h($st['value']) ?>" <?= ($sf['type'] ?? 'text') === $st['value'] ? 'selected' : '' ?>>
          <?= $st['label'] ?>
        </option>
        <?php endforeach ?>
      </select>

      <div id="rp-cfg-opts-<?= $i ?>"
           style="<?= ($sf['type'] ?? '') !== 'select' ? 'display:none;' : '' ?>flex:1;min-width:0">
        <input type="text"
               name="rp_options[]"
               value="<?= h(implode(',', $sf['options'] ?? [])) ?>"
               placeholder="Op1,Op2,Op3">
        <div class="hint" style="font-size:.7rem;margin-top:2px">Separadas por vírgula</div>
      </div>

      <label style="display:flex;align-items:center;gap:4px;font-size:.8rem;white-space:nowrap;font-weight:400;cursor:pointer;flex-shrink:0">
        <input type="checkbox"
               name="rp_required[]"
               value="<?= $i ?>"
               <?= !empty($sf['required']) ? 'checked' : '' ?>
               style="accent-color:var(--ac);width:auto">
        Obrigatório
      </label>

      <button type="button"
              class="btn btn-danger btn-xs"
              onclick="rpCfgRemove(this)"
              title="Remover subcampo"
              style="flex-shrink:0">✕</button>

    </div>
    <?php endforeach ?>
  </div>

  <button type="button"
          class="btn btn-ghost btn-sm"
          style="margin-top:8px;width:100%"
          onclick="rpCfgAdd()">+ Adicionar subcampo</button>

  <div style="display:flex;gap:12px;margin-top:14px">
    <div class="field" style="flex:1;margin:0">
      <label style="font-size:.78rem">Mínimo de linhas</label>
      <input type="number" name="rp_min_rows" value="<?= $minRows ?>" min="0" max="50" style="width:80px">
    </div>
    <div class="field" style="flex:1;margin:0">
      <label style="font-size:.78rem">Máximo <span style="color:var(--mt);font-weight:400">(0 = sem limite)</span></label>
      <input type="number" name="rp_max_rows" value="<?= $maxRows ?>" min="0" max="500" style="width:80px">
    </div>
  </div>

</div>

<script>
(function () {

  // Mostra/oculta o painel quando o tipo de campo muda
  document.addEventListener('flexcore:fieldTypeChange', function (e) {
    var wrap = document.getElementById('rp-config-wrap');
    if (wrap) wrap.style.display = e.detail.type === 'repeater' ? 'block' : 'none';
  });

  // Lista de tipos de subcampo — gerada pelo PHP, sem manipulação de chaves
  var RP_TYPES = <?= json_encode(array_values($subfieldTypeList)) ?>;

  // Monta o <select> de tipos
  function rpCfgMakeSelect(idx) {
    var html = '<select name="rp_type[]" onchange="rpCfgToggleOpts(this,' + idx + ')">';
    RP_TYPES.forEach(function (t) {
      html += '<option value="' + t.value + '">' + t.label + '</option>';
    });
    html += '</select>';
    return html;
  }

  // Adiciona nova linha de subcampo
  window.rpCfgAdd = function () {
    var wrap = document.getElementById('rp-subfields');
    var idx  = wrap.querySelectorAll('.rp-cfg-row').length;

    var div = document.createElement('div');
    div.className   = 'rp-cfg-row field';
    div.dataset.idx = idx;
    div.innerHTML =
      '<input type="text" name="rp_slug[]" placeholder="slug_campo" pattern="[a-z0-9_]+" title="Minúsculas, números e _" required>' +
      '<input type="text" name="rp_label[]" placeholder="Rótulo" required>' +
      rpCfgMakeSelect(idx) +
      '<div id="rp-cfg-opts-' + idx + '" style="display:none;flex:1;min-width:0">' +
        '<input type="text" name="rp_options[]" placeholder="Op1,Op2,Op3">' +
        '<div class="hint" style="font-size:.7rem;margin-top:2px">Separadas por vírgula</div>' +
      '</div>' +
      '<label style="display:flex;align-items:center;gap:4px;font-size:.8rem;white-space:nowrap;font-weight:400;cursor:pointer;flex-shrink:0">' +
        '<input type="checkbox" name="rp_required[]" value="' + idx + '" style="accent-color:var(--ac);width:auto"> Obrigatório' +
      '</label>' +
      '<button type="button" class="btn btn-danger btn-xs" onclick="rpCfgRemove(this)" title="Remover" style="flex-shrink:0">✕</button>';

    wrap.appendChild(div);
    div.querySelector('input[name="rp_slug[]"]').focus();
  };

  // Remove linha (mantém mínimo de 1)
  window.rpCfgRemove = function (btn) {
    var rows = document.querySelectorAll('#rp-subfields .rp-cfg-row');
    if (rows.length <= 1) { alert('O repetidor precisa de pelo menos 1 subcampo.'); return; }
    btn.closest('.rp-cfg-row').remove();
  };

  // Mostra/oculta input de opções quando tipo = select
  window.rpCfgToggleOpts = function (sel, idx) {
    var wrap = document.getElementById('rp-cfg-opts-' + idx);
    if (wrap) wrap.style.display = sel.value === 'select' ? '' : 'none';
  };

})();
</script>
