<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for managing dashboard notices (avisos).
 */
class notices_service {

    /** Valid notice types. */
    const TYPES = ['info', 'warning', 'danger', 'success'];

    /**
     * Get the user's turma (cohort) from the custom profile field.
     *
     * @param int $userid
     * @return string|null The turma value, or null if not found/configured.
     */
    public static function get_user_turma(int $userid): ?string {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => 'turma']);
        if (!$field) {
            return null;
        }

        $data = $DB->get_field('user_info_data', 'data', [
            'userid' => $userid,
            'fieldid' => $field->id,
        ]);

        return !empty($data) ? $data : null;
    }

    /**
     * Return all currently active notices visible to end-users.
     * Active = enabled=1 AND (date_start is null OR date_start <= now)
     *                    AND (date_end   is null OR date_end   >= now)
     *                    AND (turma is null OR turma = user's turma)
     *
     * @param int|null $userid  If provided, filters notices by user's turma.
     * @return array  Array of stdClass rows ordered by sortorder ASC.
     */
    public static function get_active_notices(?int $userid = null): array {
        global $DB;

        $now = time();
        $params = ['now1' => $now, 'now2' => $now];

        // Get user's turma for filtering.
        $userturma = null;
        if ($userid !== null) {
            $userturma = self::get_user_turma($userid);
        }

        if ($userturma !== null) {
            // Filter: notice has no turma (all) OR turma contains user's turma (comma-separated).
            $sql = "SELECT *
                      FROM {local_dashboard_notices}
                     WHERE enabled = 1
                       AND (date_start IS NULL OR date_start <= :now1)
                       AND (date_end   IS NULL OR date_end   >= :now2)
                       AND (turma IS NULL OR turma = :userturma1 OR FIND_IN_SET(:userturma2, turma) > 0)
                  ORDER BY sortorder ASC, id ASC";
            $params['userturma1'] = $userturma;
            $params['userturma2'] = $userturma;
        } else {
            // No turma field configured — show all notices (backward compatible).
            $sql = "SELECT *
                      FROM {local_dashboard_notices}
                     WHERE enabled = 1
                       AND (date_start IS NULL OR date_start <= :now1)
                       AND (date_end   IS NULL OR date_end   >= :now2)
                  ORDER BY sortorder ASC, id ASC";
        }

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Return all notices (for admin listing), ordered by sortorder.
     *
     * @return array
     */
    public static function get_all_notices(): array {
        global $DB;
        return array_values($DB->get_records('local_dashboard_notices', null, 'sortorder ASC, id ASC'));
    }

    /**
     * Get a single notice by ID.
     *
     * @param int $id
     * @return \stdClass|false
     */
    public static function get_notice(int $id) {
        global $DB;
        return $DB->get_record('local_dashboard_notices', ['id' => $id]);
    }

    /**
     * Save (insert or update) a notice.
     *
     * @param \stdClass $data  Object with fields: title, body, type, enabled, sortorder, date_start, date_end.
     *                         If $data->id is set and > 0, performs an update; otherwise inserts.
     * @return int  The id of the saved record.
     */
    public static function save_notice(\stdClass $data): int {
        global $DB, $USER;

        // Whitelist type.
        if (!in_array($data->type, self::TYPES, true)) {
            $data->type = 'info';
        }

        // Sanitize text fields.
        $data->title = clean_param($data->title ?? '', PARAM_TEXT);
        $data->body  = clean_param($data->body  ?? '', PARAM_CLEANHTML);
        $data->enabled    = (int)(bool)($data->enabled ?? 1);
        $data->sortorder  = (int)($data->sortorder ?? 0);
        $data->date_start = !empty($data->date_start) ? (int)$data->date_start : null;
        $data->date_end   = !empty($data->date_end)   ? (int)$data->date_end   : null;
        // Handle turma: array of selected cohorts -> comma-separated string.
        if (is_array($data->turma)) {
            // From checkboxes: array of selected values.
            $turma_values = array_map(function($v) { return clean_param($v, PARAM_TEXT); }, $data->turma);
            $turma_values = array_filter($turma_values, function($v) { return $v !== ''; });
            $data->turma = !empty($turma_values) ? implode(',', $turma_values) : null;
        } else {
            // Single value (legacy).
            $data->turma = !empty($data->turma) ? clean_param((string)$data->turma, PARAM_TEXT) : null;
        }
        $data->timemodified = time();
        $data->usermodified = $USER->id;

        if (!empty($data->id) && $data->id > 0) {
            // Use standard update for known columns.
            $DB->update_record('local_dashboard_notices', $data);
            // Update turma via raw SQL to bypass column metadata cache.
            $DB->execute(
                "UPDATE {local_dashboard_notices} SET turma = :turma WHERE id = :id",
                ['turma' => $data->turma, 'id' => $data->id]
            );
            return (int)$data->id;
        } else {
            $data->timecreated = time();
            return (int)$DB->insert_record('local_dashboard_notices', $data);
        }
    }

    /**
     * Delete a notice by ID.
     *
     * @param int $id
     */
    public static function delete_notice(int $id): void {
        global $DB;
        $DB->delete_records('local_dashboard_notices', ['id' => $id]);
    }

    /**
     * Toggle enabled state of a notice.
     *
     * @param int $id
     * @return bool  New enabled state.
     */
    public static function toggle_notice(int $id): bool {
        global $DB, $USER;
        $record = $DB->get_record('local_dashboard_notices', ['id' => $id], '*', MUST_EXIST);
        $record->enabled      = $record->enabled ? 0 : 1;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->update_record('local_dashboard_notices', $record);
        return (bool)$record->enabled;
    }

    /**
     * Convert a list of notice DB rows to template-ready array context.
     *
     * @param \stdClass[] $notices
     * @return array
     */
    public static function to_template_context(array $notices): array {
        $icons = [
            'info'    => 'ℹ️',
            'warning' => '⚠️',
            'danger'  => '🚨',
            'success' => '✅',
        ];

        $out = [];
        foreach ($notices as $notice) {
            $out[] = [
                'id'    => (int)$notice->id,
                'title' => format_string($notice->title),
                'body'  => format_text($notice->body ?? '', FORMAT_HTML, ['noclean' => false]),
                'type'  => $notice->type,
                'icon'  => $icons[$notice->type] ?? 'ℹ️',
                'turma' => $notice->turma ?? '',
            ];
        }
        return $out;
    }
}
