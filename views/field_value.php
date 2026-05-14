<?php
/**
 * views/field_value.php
 *
 * Renderizado pelo hook field.render_value.
 *
 * Variáveis disponíveis:
 *   $field  — array do campo (options_json, field_type, name)
 *   $rows   — array de linhas [ group_index => [ subfield_slug => value ] ]
 *   $full   — bool — true no show do registro, false na listagem
 *
 * Na listagem ($full = false): exibe só a contagem de linhas (compacto).
 * No show ($full = true): exibe a tabela completa.
 */

$meta      = json_decode($field['options_json'] ?? '{}', true) ?: [];
$subfields = $meta['subfields'] ?? [];

if (empty($rows)) {
    echo '<span style="color:var(--mt)">—</span>';
    return;
}

// Modo compacto (listagem)
if (!$full) {
    $count = count($rows);
    echo '<span style="background:var(--sf2);border:1px solid var(--bd);border-radius:10px;padding:1px 8px;font-size:.75rem">'
        . $count . ' linha' . ($count !== 1 ? 's' : '')
        . '</span>';
    return;
}

// Modo completo (show do registro)
?>
<div style="overflow-x:auto;margin-top:2px">
  <table style="width:100%;border-collapse:collapse;font-size:.85rem">
    <thead>
      <tr>
        <?php foreach ($subfields as $sf): ?>
        <th style="text-align:left;font-size:.72rem;font-weight:700;color:var(--mt);text-transform:uppercase;letter-spacing:.04em;padding:6px 10px;border-bottom:1px solid var(--bd2)">
          <?= h($sf['label'] ?? $sf['slug']) ?>
        </th>
        <?php endforeach ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $gi => $rowValues): ?>
      <tr>
        <?php foreach ($subfields as $sf):
          $slug = $sf['slug'] ?? '';
          $type = $sf['type'] ?? 'text';
          $val  = $rowValues[$slug] ?? '';
        ?>
        <td style="padding:8px 10px;border-bottom:1px solid var(--bd);vertical-align:top;color:var(--tx)">
          <?php if ($val === '' || $val === null): ?>
            <span style="color:var(--mt)">—</span>
          <?php elseif ($type === 'url'): ?>
            <a href="<?= h($val) ?>" target="_blank" rel="noopener" style="color:var(--ac)"><?= h($val) ?></a>
          <?php elseif ($type === 'email'): ?>
            <a href="mailto:<?= h($val) ?>" style="color:var(--ac)"><?= h($val) ?></a>
          <?php elseif ($type === 'phone'): ?>
            <a href="tel:<?= h(preg_replace('/\D/', '', $val)) ?>" style="color:var(--ac)"><?= h($val) ?></a>
          <?php elseif ($type === 'checkbox'): ?>
            <?= $val === '1' ? '✅' : '☐' ?>
          <?php elseif ($type === 'currency'): ?>
            R$ <?= number_format((float) $val, 2, ',', '.') ?>
          <?php elseif ($type === 'percent'): ?>
            <?= h($val) ?>%
          <?php elseif ($type === 'date'): ?>
            <?= $val ? date('d/m/Y', strtotime($val)) : '—' ?>
          <?php else: ?>
            <?= h($val) ?>
          <?php endif ?>
        </td>
        <?php endforeach ?>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
