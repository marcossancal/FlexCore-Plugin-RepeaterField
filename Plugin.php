<?php

declare(strict_types=1);

namespace FlexCoreRepeater;

// ── Carrega os arquivos do plugin (autoloader do core não cobre FlexCoreRepeater\) ──
require_once __DIR__ . '/RepeaterRepository.php';

use FlexCore\Modules\Plugins\PluginInterface;
use FlexCore\Modules\Plugins\PluginManifest;
use FlexCore\Core\Hooks\Hooks;

/**
 * FlexCore Repeater Field Plugin
 *
 * Adiciona o tipo de campo "repeater" via hooks — zero acoplamento com o core.
 *
 * Hooks utilizados:
 *   field.types                  — registra o tipo "repeater" em allFieldTypes()
 *   field.options_build          — serializa config dos subcampos em options_json
 *   field.render_config          — UI de configuração na tela de campos da entidade
 *   field.render_form            — input do campo no formulário de registro
 *   field.render_value           — exibição no show e na listagem
 *   record.input                 — injeta repeater_* no $input antes do RecordService
 *   record.values_loaded         — injeta linhas do repeater no array de valores
 *   record.created               — persiste linhas após criação
 *   record.updated               — re-persiste linhas após atualização
 *   record.deleted               — apaga linhas ao deletar registro
 *   layout.head                  — injeta CSS
 *   layout.footer_scripts        — injeta JS
 *   records.list.columns.header  — coluna de contagem na tabela de listagem
 *   records.list.columns.cell    — célula com contagem por registro
 *   router.register              — endpoint REST de busca nos subcampos
 */
class Plugin implements PluginInterface
{
    public function manifest(): PluginManifest
    {
        return PluginManifest::fromJson(__DIR__ . '/plugin.json');
    }

    public function boot(): void
    {
        $repo    = new RepeaterRepository();
        $baseDir = __DIR__;

        // ── Instala tabela na primeira execução ───────────────────────
        if (!\DB::setting('repeater_installed')) {
            $repo->createTable();
            \DB::setSetting('repeater_installed', '1', 'Repeater instalado', 'repeater');
        }

        // ─────────────────────────────────────────────────────────────
        // 0. translations.loaded
        // Injeta as traducoes do plugin sem editar os JSONs do core.
        // Assinatura: filter(array $trans, string $lang): array
        Hooks::filter('translations.loaded', function (array $trans, string $lang) use ($baseDir): array {
            $file = $baseDir . '/translates/' . $lang . '.json';
            if (!file_exists($file)) {
                $file = $baseDir . '/translates/pt_BR.json';
            }
            if (!file_exists($file)) return $trans;
            $plugin = json_decode(file_get_contents($file), true) ?? [];
            return array_replace_recursive($trans, $plugin);
        });

        // 1. field.types
        // Registra "repeater" em allFieldTypes() para que o sistema
        // reconheça o tipo em todo lugar (ícone, label, select de campos).
        //
        // Assinatura: filter(array $types): array
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('field.types', function (array $types): array {
            $types['repeater'] = ['icon' => '🔁', 'storage' => 'repeater_values'];
            return $types;
        });

        // ─────────────────────────────────────────────────────────────
        // 2. field.options_build
        // Serializa a configuração dos subcampos em options_json
        // quando o admin salva um campo do tipo repeater.
        //
        // Assinatura: filter(?string $json, array $ctx): ?string
        //   $ctx['field_type'], $ctx['post']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('field.options_build', function (?string $json, array $ctx): ?string {
            if ($ctx['field_type'] !== 'repeater') return $json;

            $post     = $ctx['post'];
            $slugs    = array_map('trim', (array) ($post['rp_slug']    ?? []));
            $labels   = array_map('trim', (array) ($post['rp_label']   ?? []));
            $types    = (array) ($post['rp_type']     ?? []);
            $options  = (array) ($post['rp_options']  ?? []);
            $required = (array) ($post['rp_required'] ?? []);

            $subfields = [];
            foreach ($slugs as $i => $slug) {
                if ($slug === '') continue;
                $sf = [
                    'slug'     => $slug,
                    'label'    => $labels[$i] ?? $slug,
                    'type'     => $types[$i]  ?? 'text',
                    'required' => in_array((string) $i, $required, true),
                    'options'  => [],
                ];
                if ($sf['type'] === 'select' && !empty($options[$i])) {
                    $sf['options'] = array_values(
                        array_filter(array_map('trim', explode(',', $options[$i])))
                    );
                }
                $subfields[] = $sf;
            }

            return json_encode([
                'subfields' => $subfields,
                'min_rows'  => max(0, (int) ($post['rp_min_rows'] ?? 1)),
                'max_rows'  => max(0, (int) ($post['rp_max_rows'] ?? 0)),
            ]);
        });

        // ─────────────────────────────────────────────────────────────
        // 3. field.render_config
        // Injeta o HTML de configuração dos subcampos na tela de campos.
        // O painel é mostrado/ocultado via evento flexcore:fieldTypeChange.
        //
        // Assinatura: filter(string $html, array $ctx): string
        //   $ctx['entity'], $ctx['field'], $ctx['entities']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('field.render_config', function (string $html, array $ctx) use ($baseDir): string {
            ob_start();
            $entity       = $ctx['entity']   ?? [];
            $field        = $ctx['field']    ?? null;
            $all_entities = $ctx['entities'] ?? [];
            include $baseDir . '/views/field_config.php';
            return $html . ob_get_clean();
        });

        // ─────────────────────────────────────────────────────────────
        // 4. field.render_form
        // Renderiza o campo no formulário de criação/edição de registro.
        // $currentVal já é o array de linhas injetado pelo record.values_loaded.
        //
        // Assinatura do applyFilter no core:
        //   applyFilter('field.render_form', null, [$f, $name, $currentVal, $required])
        // O dispatcher faz: call_user_func_array($fn, array_merge([null], [$f, $name, $currentVal, $required]))
        // Portanto a assinatura do listener é:
        //   fn(?string $html, array $f, string $name, mixed $currentVal, string $required)
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('field.render_form', function (
            ?string $html,
            array   $f,
            string  $name,
            mixed   $currentVal,
            string  $required
        ) use ($baseDir): ?string {
            if ($f['field_type'] !== 'repeater') return $html;

            $rows = is_array($currentVal) ? $currentVal : [];

            ob_start();
            include $baseDir . '/views/field_form.php';
            return ob_get_clean();
        });

        // ─────────────────────────────────────────────────────────────
        // 5. field.render_value
        // Renderiza o valor no show do registro e na listagem.
        //
        // Assinatura do applyFilter no core:
        //   applyFilter('field.render_value', null, [$field, $val, $full])
        // Listener: fn(?string $html, array $field, mixed $val, bool $full)
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('field.render_value', function (
            ?string $html,
            array   $field,
            mixed   $val,
            bool    $full
        ) use ($baseDir): ?string {
            if ($field['field_type'] !== 'repeater') return $html;

            $rows = is_array($val) ? $val : [];
            $meta = json_decode($field['options_json'] ?? '{}', true) ?: [];

            ob_start();
            include $baseDir . '/views/field_value.php';
            return ob_get_clean();
        });

        // ─────────────────────────────────────────────────────────────
        // 6. record.input
        // Injeta repeater_{fieldId} no $input antes do RecordService.
        //
        // Assinatura: filter(array $input, array $ctx): array
        //   $ctx['fields'], $ctx['post'], $ctx['files']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('record.input', function (array $input, array $ctx): array {
            foreach ($ctx['fields'] as $f) {
                if ($f['field_type'] !== 'repeater') continue;
                $key         = 'repeater_' . $f['id'];
                $input[$key] = $ctx['post'][$key] ?? [];
            }
            return $input;
        });

        // ─────────────────────────────────────────────────────────────
        // 7. record.values_loaded
        // Injeta as linhas do repeater no array $values antes da view.
        // Convenção: $values[field_id] = [ group_index => [ subfield => value ] ]
        //
        // Assinatura: filter(array $values, array $ctx): array
        //   $ctx['record_id'], $ctx['fields']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('record.values_loaded', function (array $values, array $ctx) use ($repo): array {
            $hasRepeater = false;
            foreach ($ctx['fields'] as $f) {
                if ($f['field_type'] === 'repeater') { $hasRepeater = true; break; }
            }
            if (!$hasRepeater) return $values;

            $repeaterData = $repo->loadForRecord((int) $ctx['record_id']);

            foreach ($ctx['fields'] as $f) {
                if ($f['field_type'] !== 'repeater') continue;
                $values[(int) $f['id']] = $repeaterData[(int) $f['id']] ?? [];
            }

            return $values;
        });

        // ─────────────────────────────────────────────────────────────
        // 8. record.created
        // Persiste as linhas após criação do registro.
        //
        // Assinatura action: (int $recordId, int $entityId, array $input)
        // ─────────────────────────────────────────────────────────────
        Hooks::on('record.created', function (int $recordId, int $entityId, array $input) use ($repo): void {
            $fields = \DB::q(
                "SELECT * FROM entity_fields WHERE entity_id = ? AND field_type = 'repeater'",
                [$entityId]
            );
            if (empty($fields)) return;
            $repo->save($recordId, $fields, $input);
        });

        // ─────────────────────────────────────────────────────────────
        // 9. record.updated
        // Re-persiste as linhas após atualização.
        // ─────────────────────────────────────────────────────────────
        Hooks::on('record.updated', function (int $recordId, int $entityId, array $input) use ($repo): void {
            $fields = \DB::q(
                "SELECT * FROM entity_fields WHERE entity_id = ? AND field_type = 'repeater'",
                [$entityId]
            );
            if (empty($fields)) return;
            $repo->save($recordId, $fields, $input);
        });

        // ─────────────────────────────────────────────────────────────
        // 10. record.deleted
        // Apaga as linhas ao deletar registro.
        //
        // Assinatura action: (int $recordId, int $entityId)
        // ─────────────────────────────────────────────────────────────
        Hooks::on('record.deleted', function (int $recordId, int $entityId) use ($repo): void {
            $repo->deleteForRecord($recordId);
        });

        // ─────────────────────────────────────────────────────────────
        // 11. layout.head — injeta CSS
        // Assinatura: filter(string $html): string
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('layout.head', function (string $html) use ($baseDir): string {
            ob_start();
            include $baseDir . '/views/assets_head.php';
            return $html . ob_get_clean();
        });

        // ─────────────────────────────────────────────────────────────
        // 12. layout.footer_scripts — injeta JS
        // Assinatura: filter(string $html): string
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('layout.footer_scripts', function (string $html) use ($baseDir): string {
            ob_start();
            include $baseDir . '/views/assets_footer.php';
            return $html . ob_get_clean();
        });

        // ─────────────────────────────────────────────────────────────
        // 13. records.list.columns.header
        // Assinatura: filter(string $html, array $ctx): string
        //   $ctx['entity'], $ctx['fields']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('records.list.columns.header', function (string $html, array $ctx): string {
            foreach ($ctx['fields'] as $f) {
                if ($f['field_type'] === 'repeater') {
                    $html .= '<th style="width:60px;text-align:center;font-size:.72rem">🔁</th>';
                    break;
                }
            }
            return $html;
        });

        // ─────────────────────────────────────────────────────────────
        // 14. records.list.columns.cell
        // Assinatura: filter(string $html, array $ctx): string
        //   $ctx['entity'], $ctx['record'], $ctx['fields']
        // ─────────────────────────────────────────────────────────────
        Hooks::filter('records.list.columns.cell', function (string $html, array $ctx): string {
            $repeaterFields = array_filter(
                $ctx['fields'],
                fn($f) => $f['field_type'] === 'repeater'
            );
            if (empty($repeaterFields)) return $html;

            $totalRows = 0;
            foreach ($repeaterFields as $f) {
                $val = $ctx['record']['values'][$f['id']] ?? [];
                if (is_array($val)) $totalRows += count($val);
            }

            $html .= '<td style="text-align:center;font-size:.75rem;color:var(--mt)">';
            $html .= $totalRows > 0
                ? '<span style="background:var(--sf2);border:1px solid var(--bd);border-radius:10px;padding:1px 7px">' . $totalRows . '</span>'
                : '<span style="color:var(--mt)">—</span>';
            $html .= '</td>';

            return $html;
        });

        // ─────────────────────────────────────────────────────────────
        // 15. router.register — endpoint REST de busca nos subcampos
        // GET /api/v1/repeater/{field_id}/search?subfield=X&q=Y[&exact=1]
        //
        // Assinatura action: ($router)
        // ─────────────────────────────────────────────────────────────
        Hooks::on('router.register', function ($router) use ($repo): void {
            $router->get(
                '/api/v1/repeater/{field_id}/search',
                function (int $fieldId) use ($repo): void {
                    $field = \DB::one(
                        "SELECT id FROM entity_fields WHERE id = ? AND field_type = 'repeater'",
                        [$fieldId]
                    );
                    if (!$field) {
                        http_response_code(404);
                        echo json_encode(['data' => null, 'errors' => ['Campo não encontrado.']]);
                        return;
                    }

                    $subfield = trim($_GET['subfield'] ?? '');
                    $q        = trim($_GET['q']        ?? '');
                    $exact    = !empty($_GET['exact']);

                    if ($subfield === '' || $q === '') {
                        http_response_code(422);
                        echo json_encode(['data' => null, 'errors' => ['Parâmetros obrigatórios: subfield, q']]);
                        return;
                    }

                    $ids = $repo->searchBySubfield($fieldId, $subfield, $q, $exact);
                    echo json_encode([
                        'data'   => $ids,
                        'meta'   => ['total' => count($ids)],
                        'errors' => null,
                    ]);
                }
            )->middleware(new \FlexCore\Api\Middleware\ApiAuthMiddleware());
        });
    }

    public function uninstall(): void
    {
        (new RepeaterRepository())->dropTable();
        \DB::run("DELETE FROM settings WHERE skey = 'repeater_installed'");
    }
}
