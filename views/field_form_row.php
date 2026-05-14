<?php
/**
 * views/field_form_row.php
 *
 * Renderiza UMA linha do repetidor.
 * Incluído por field_form.php para linhas existentes e para o <template> JS.
 *
 * Variáveis esperadas do contexto pai:
 *   $gi         — int|string — índice do grupo ou '__IDX__' no template
 *   $rowValues  — array [ subfield_slug => value ] para pré-preenchimento
 *   $subfields  — array de definições dos subcampos (do options_json)
 *   $prefix     — "repeater_{fieldId}"
 *   $fid        — int — ID do campo
 */
?>
<div class="rp-row" data-gi="<?= h((string) $gi) ?>">

  <?php foreach ($subfields as $sf):
    $slug  = $sf['slug']   ?? '';
    $type  = $sf['type']   ?? 'text';
    $label = $sf['label']  ?? $slug;
    $opts  = $sf['options'] ?? [];
    $req   = !empty($sf['required']) ? 'required' : '';
    $iname = $prefix . '[' . $gi . '][' . $slug . ']';
    $val   = $rowValues[$slug] ?? '';
  ?>
  <div class="rp-cell field">
    <?php switch ($type):

      case 'textarea': ?>
        <textarea name="<?= h($iname) ?>" placeholder="<?= h($label) ?>" rows="2" <?= $req ?>><?= h($val) ?></textarea>
        <?php break;

      case 'select': ?>
        <select name="<?= h($iname) ?>" <?= $req ?>>
          <option value="">—</option>
          <?php foreach ($opts as $o): ?>
          <option value="<?= h($o) ?>" <?= $val === $o ? 'selected' : '' ?>><?= h($o) ?></option>
          <?php endforeach ?>
        </select>
        <?php break;

      case 'number': ?>
        <input type="number" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" step="1" <?= $req ?>>
        <?php break;

      case 'currency': ?>
        <input type="number" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" step="0.01" min="0" <?= $req ?>>
        <?php break;

      case 'percent': ?>
        <input type="number" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" step="0.01" min="0" max="100" <?= $req ?>>
        <?php break;

      case 'date': ?>
        <input type="date" name="<?= h($iname) ?>" value="<?= h($val) ?>" <?= $req ?>>
        <?php break;

      case 'email': ?>
        <input type="email" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" <?= $req ?>>
        <?php break;

      case 'phone': ?>
        <input type="tel" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" <?= $req ?>>
        <?php break;

      case 'url': ?>
        <input type="url" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="https://" <?= $req ?>>
        <?php break;

      case 'checkbox': ?>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 0">
          <input type="checkbox" name="<?= h($iname) ?>" value="1"
                 <?= $val === '1' ? 'checked' : '' ?>
                 style="accent-color:var(--ac);width:auto">
          <?= h($label) ?>
        </label>
        <?php break;

      default: /* text, password, ip, uuid, etc. */ ?>
        <input type="text" name="<?= h($iname) ?>" value="<?= h($val) ?>"
               placeholder="<?= h($label) ?>" <?= $req ?>>
        <?php break;

    endswitch ?>
  </div>
  <?php endforeach ?>

  <div class="rp-cell-rm">
    <button type="button"
            class="btn btn-danger btn-xs"
            onclick="rpRemoveRow(this, <?= $fid ?>)"
            title="Remover linha">✕</button>
  </div>

</div>
