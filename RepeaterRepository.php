<?php

declare(strict_types=1);

namespace FlexCoreRepeater;

/**
 * RepeaterRepository
 *
 * Responsável por TODA persistência em repeater_values.
 * A escrita é acionada pelos hooks record.created / record.updated.
 * A leitura é acionada pelo hook record.values_loaded.
 *
 * Estrutura da tabela:
 *   record_id   INT  — FK entity_records.id
 *   field_id    INT  — FK entity_fields.id
 *   group_index INT  — índice da linha (0, 1, 2 …)
 *   subfield    VARCHAR — slug do subcampo (ex: "telefone", "tipo")
 *   val_text    TEXT
 *   val_num     DECIMAL(20,4)
 *   val_date    DATE
 *
 * Busca eficiente via índices:
 *   idx_field_subfield + idx_field_val_text + idx_field_val_num + idx_field_val_date
 */
class RepeaterRepository
{
    // ── Escrita ───────────────────────────────────────────────────────

    /**
     * Salva todas as linhas de todos os campos repeater de um registro.
     * Apaga primeiro (DELETE) e reinsere — idempotente para create e update.
     *
     * Formato esperado em $input:
     *   repeater_{fieldId}[ groupIndex ][ subfieldSlug ] = valor
     *
     * @param int   $recordId
     * @param array $fields    Todos os campos da entidade (array de linhas da entity_fields)
     * @param array $input     Array montado pelo hook record.input (vem do $_POST enriquecido)
     */
    public function save(int $recordId, array $fields, array $input): void
    {
        foreach ($fields as $field) {
            if ($field['field_type'] !== 'repeater') continue;

            $fieldId   = (int) $field['id'];
            $inputKey  = 'repeater_' . $fieldId;

            // Remove linhas antigas deste campo
            \DB::run(
                'DELETE FROM repeater_values WHERE record_id = ? AND field_id = ?',
                [$recordId, $fieldId]
            );

            if (empty($input[$inputKey]) || !is_array($input[$inputKey])) continue;

            $meta      = json_decode($field['options_json'] ?? '{}', true) ?: [];
            $subfields = $meta['subfields'] ?? [];
            $maxRows   = (int) ($meta['max_rows'] ?? 0);

            // Reconstrói linhas descartando as completamente vazias
            $cleanRows = [];
            foreach ($input[$inputKey] as $rowData) {
                if (!is_array($rowData)) continue;
                $hasValue = false;
                foreach ($rowData as $v) {
                    if ($v !== null && $v !== '') { $hasValue = true; break; }
                }
                if ($hasValue) $cleanRows[] = $rowData;
            }

            if ($maxRows > 0) {
                $cleanRows = array_slice($cleanRows, 0, $maxRows);
            }

            foreach ($cleanRows as $groupIndex => $rowData) {
                foreach ($subfields as $sf) {
                    $slug  = $sf['slug'] ?? '';
                    $type  = $sf['type'] ?? 'text';
                    $value = $rowData[$slug] ?? null;

                    if ($slug === '' || ($value === null || $value === '')) continue;

                    if (is_array($value)) $value = json_encode($value);

                    [$valText, $valNum, $valDate] = $this->distribute($type, $value);

                    \DB::exec(
                        'INSERT INTO repeater_values
                            (record_id, field_id, group_index, subfield, val_text, val_num, val_date)
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                             val_text = VALUES(val_text),
                             val_num  = VALUES(val_num),
                             val_date = VALUES(val_date)',
                        [$recordId, $fieldId, $groupIndex, $slug, $valText, $valNum, $valDate]
                    );
                }
            }
        }
    }

    /**
     * Remove todas as linhas de um registro (usado no hook record.deleted).
     */
    public function deleteForRecord(int $recordId): void
    {
        \DB::run('DELETE FROM repeater_values WHERE record_id = ?', [$recordId]);
    }

    // ── Leitura ───────────────────────────────────────────────────────

    /**
     * Carrega todas as linhas de todos os campos repeater de um registro.
     *
     * Retorno:
     *   [ field_id => [ group_index => [ subfield_slug => value ] ] ]
     */
    public function loadForRecord(int $recordId): array
    {
        $rows = \DB::q(
            'SELECT field_id, group_index, subfield, val_text, val_num, val_date
               FROM repeater_values
              WHERE record_id = ?
           ORDER BY field_id, group_index, subfield',
            [$recordId]
        );

        $out = [];
        foreach ($rows as $row) {
            $fid   = (int) $row['field_id'];
            $gi    = (int) $row['group_index'];
            $sub   = $row['subfield'];
            $value = $row['val_text'] ?? ($row['val_num'] !== null ? (string) $row['val_num'] : $row['val_date']);
            $out[$fid][$gi][$sub] = $value;
        }

        return $out;
    }

    /**
     * Busca record_ids onde um subcampo específico contém um valor.
     * Usado pelo endpoint de API de busca.
     *
     * @param int    $fieldId
     * @param string $subfield  Slug do subcampo
     * @param string $value     Valor a buscar
     * @param bool   $exact     true = igualdade, false = LIKE %valor%
     * @return int[]
     */
    public function searchBySubfield(int $fieldId, string $subfield, string $value, bool $exact = false): array
    {
        if ($exact) {
            $rows = \DB::q(
                'SELECT DISTINCT record_id FROM repeater_values
                  WHERE field_id = ? AND subfield = ?
                    AND (val_text = ? OR CAST(val_num AS CHAR) = ?)',
                [$fieldId, $subfield, $value, $value]
            );
        } else {
            $like = '%' . $value . '%';
            $rows = \DB::q(
                'SELECT DISTINCT record_id FROM repeater_values
                  WHERE field_id = ? AND subfield = ?
                    AND (val_text LIKE ? OR CAST(val_num AS CHAR) LIKE ?)',
                [$fieldId, $subfield, $like, $like]
            );
        }

        return array_column($rows, 'record_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Distribui o valor nas colunas val_text / val_num / val_date
     * com base no tipo do subcampo.
     *
     * @return array [val_text, val_num, val_date]
     */
    private function distribute(string $type, mixed $value): array
    {
        if ($value === null || $value === '') return [null, null, null];

        $numTypes  = ['number', 'currency', 'percent', 'rating', 'progress'];
        $dateTypes = ['date'];

        if (in_array($type, $numTypes, true)) {
            return [null, is_numeric($value) ? (float) $value : null, null];
        }
        if (in_array($type, $dateTypes, true)) {
            return [null, null, (string) $value];
        }
        return [(string) $value, null, null];
    }

    // ── Instalação ────────────────────────────────────────────────────

    public function createTable(): void
    {
        \DB::run("
            CREATE TABLE IF NOT EXISTS repeater_values (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                record_id   INT UNSIGNED    NOT NULL,
                field_id    INT UNSIGNED    NOT NULL,
                group_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                subfield    VARCHAR(80)     NOT NULL,
                val_text    TEXT            NULL,
                val_num     DECIMAL(20,4)   NULL,
                val_date    DATE            NULL,

                UNIQUE KEY uq_repeater (record_id, field_id, group_index, subfield),

                INDEX idx_field_subfield (field_id, subfield),
                INDEX idx_field_val_text (field_id, subfield, val_text(100)),
                INDEX idx_field_val_num  (field_id, subfield, val_num),
                INDEX idx_field_val_date (field_id, subfield, val_date),
                INDEX idx_record         (record_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function dropTable(): void
    {
        \DB::run('DROP TABLE IF EXISTS repeater_values');
    }
}
