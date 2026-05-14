<?php
/**
 * views/field_form.php
 *
 * Renderizado pelo hook field.render_form.
 *
 * Variáveis disponíveis:
 *   $f           — array do campo (id, name, options_json, required, field_type)
 *   $name        — "field_{id}" — ignorado para o repeater (usa prefixo próprio)
 *   $currentVal  — array de linhas [ group_index => [ subfield_slug => value ] ]
 *                  (injetado pelo hook record.values_loaded)
 *   $required    — string "required" ou ""
 *
 * Input gerado:
 *   repeater_{fieldId}[{groupIndex}][{subfieldSlug}] = valor
 */

$meta      = json_decode($f['options_json'] ?? '{}', true) ?: [];
$subfields = $meta['subfields'] ?? [];
$minRows   = max(1, (int) ($meta['min_rows'] ?? 1));
$maxRows   = (int) ($meta['max_rows'] ?? 0);
$fid       = (int) $f['id'];
$prefix    = 'repeater_' . $fid;

// Garante pelo menos minRows linhas
$rows = is_array($currentVal) ? $currentVal : [];
while (count($rows) < $minRows) {
    $rows[] = [];
}
?>

<div class="rp-wrap" id="rp-<?= $fid ?>"
     data-fid="<?= $fid ?>"
     data-max="<?= $maxRows ?>">

  <?php if (!empty($subfields)): ?>

  <div class="rp-header">
    <?php foreach ($subfields as $sf): ?>
    <div class="rp-col-lbl">
      <?= h($sf['label'] ?? $sf['slug']) ?>
      <?php if (!empty($sf['required'])): ?><span style="color:var(--rd)">*</span><?php endif ?>
    </div>
    <?php endforeach ?>
    <div style="width:34px"></div>
  </div>

  <div class="rp-rows" id="rp-rows-<?= $fid ?>">
    <?php foreach ($rows as $gi => $rowValues):
      include __DIR__ . '/field_form_row.php';
    endforeach ?>
  </div>

  <button type="button"
          class="rp-add-btn"
          onclick="rpAddRow(<?= $fid ?>, <?= h(json_encode($subfields)) ?>, <?= $maxRows ?>)">
    + Adicionar linha
  </button>

  <?php if ($maxRows > 0): ?>
  <div class="hint" id="rp-hint-<?= $fid ?>">Máximo de <?= $maxRows ?> linha<?= $maxRows !== 1 ? 's' : '' ?>.</div>
  <?php endif ?>

  <?php else: ?>
  <div style="color:var(--mt);font-size:.83rem;padding:10px">
    Nenhum subcampo configurado. <a href="<?= url('/entities') ?>" style="color:var(--ac)">Configure os campos da entidade.</a>
  </div>
  <?php endif ?>

</div>

<?php /* Template HTML clonado pelo JS para novas linhas */ ?>
<template id="rp-tpl-<?= $fid ?>">
  <?php
  $gi        = '__IDX__';
  $rowValues = [];
  include __DIR__ . '/field_form_row.php';
  ?>
</template>
