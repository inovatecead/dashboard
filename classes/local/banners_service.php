<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for managing dashboard banners (carrossel de imagens).
 */
class banners_service {

    /** File area name for banner images. */
    const FILEAREA = 'banners';

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
     * Return all enabled banners visible to end-users, ordered by sortorder.
     * Filters banners by user's turma when applicable.
     *
     * @param int|null $userid  If provided, filters by user's turma.
     * @return array  Array of stdClass rows.
     */
    public static function get_active_banners(?int $userid = null): array {
        global $DB;

        $params = [];
        // Get user's turma for filtering.
        $userturma = null;
        if ($userid !== null) {
            $userturma = self::get_user_turma($userid);
        }

        if ($userturma !== null) {
            // Filter: banner has no turma (all) OR turma contains user's turma (comma-separated).
            $sql = "SELECT *
                      FROM {local_dashboard_banners}
                     WHERE enabled = 1
                       AND (turma IS NULL OR turma = :userturma1 OR FIND_IN_SET(:userturma2, turma) > 0)
                  ORDER BY sortorder ASC, id ASC";
            $params['userturma1'] = $userturma;
            $params['userturma2'] = $userturma;
        } else {
            // No turma field configured — show all banners.
            $sql = "SELECT *
                      FROM {local_dashboard_banners}
                     WHERE enabled = 1
                  ORDER BY sortorder ASC, id ASC";
        }

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Return all banners (for admin listing), ordered by sortorder.
     *
     * @return array
     */
    public static function get_all_banners(): array {
        global $DB;
        return array_values($DB->get_records(
            'local_dashboard_banners',
            null,
            'sortorder ASC, id ASC'
        ));
    }

    /**
     * Get a single banner by ID.
     *
     * @param int $id
     * @return \stdClass|false
     */
    public static function get_banner(int $id) {
        global $DB;
        return $DB->get_record('local_dashboard_banners', ['id' => $id]);
    }

    /**
     * Save (insert or update) a banner.
     *
     * @param \stdClass $data  Object with fields: name, alt_text, link_url, enabled, sortorder.
     *                         If $data->id is set and > 0, performs an update; otherwise inserts.
     * @return int  The id of the saved record.
     */
    public static function save_banner(\stdClass $data): int {
        global $DB, $USER;

        $data->name       = clean_param($data->name ?? '', PARAM_TEXT);
        $data->alt_text   = clean_param($data->alt_text ?? '', PARAM_TEXT);
        $data->link_url   = !empty($data->link_url) ? clean_param($data->link_url, PARAM_URL) : null;
        $data->enabled    = (int)(bool)($data->enabled ?? 1);
        $data->sortorder  = (int)($data->sortorder ?? 0);
        // Handle turma: array from checkboxes -> comma-separated string.
        if (is_array($data->turma)) {
            $turma_values = array_map(function($v) { return clean_param($v, PARAM_TEXT); }, $data->turma);
            $turma_values = array_filter($turma_values, function($v) { return $v !== ''; });
            $data->turma = !empty($turma_values) ? implode(',', $turma_values) : null;
        } else {
            $data->turma = !empty($data->turma) ? clean_param((string)$data->turma, PARAM_TEXT) : null;
        }
        $data->timemodified = time();
        $data->usermodified = $USER->id;

        if (!empty($data->id) && $data->id > 0) {
            $DB->update_record('local_dashboard_banners', $data);
            // Update turma via raw SQL to bypass column metadata cache.
            $DB->execute(
                "UPDATE {local_dashboard_banners} SET turma = :turma WHERE id = :id",
                ['turma' => $data->turma, 'id' => $data->id]
            );
            return (int)$data->id;
        } else {
            $data->timecreated = time();
            return (int)$DB->insert_record('local_dashboard_banners', $data);
        }
    }

    /**
     * Save the banner image from an uploaded file.
     *
     * @param int $bannerid
     * @param array $uploadedfile  $_FILES entry for the file input.
     * @return bool
     */
    public static function save_banner_image(int $bannerid, array $uploadedfile): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($uploadedfile['name']) || $uploadedfile['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $context = \context_system::instance();
        $fs = get_file_storage();

        // Delete existing files for this banner.
        $fs->delete_area_files($context->id, 'local_dashboard', self::FILEAREA, $bannerid);

        // Save the new file.
        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'local_dashboard',
            'filearea'  => self::FILEAREA,
            'itemid'    => $bannerid,
            'filepath'  => '/',
            'filename'  => $uploadedfile['name'],
        ];

        $fs->create_file_from_pathname($fileinfo, $uploadedfile['tmp_name']);
        return true;
    }

    /**
     * Get the URL for a banner's image via pluginfile.php.
     *
     * @param int $bannerid
     * @return string|null  Full URL or null if no file found.
     */
    public static function get_banner_image_url(int $bannerid): ?string {
        global $CFG;

        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'local_dashboard',
            self::FILEAREA,
            $bannerid,
            'sortorder',
            false // Do not include directories.
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return $CFG->wwwroot . '/pluginfile.php/' . $context->id .
               '/local_dashboard/' . self::FILEAREA . '/' . $bannerid .
               '/' . $file->get_filename();
    }

    /**
     * Delete a banner by ID, including its image file.
     *
     * @param int $id
     */
    public static function delete_banner(int $id): void {
        global $DB;

        // Delete image file.
        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_dashboard', self::FILEAREA, $id);

        // Delete record.
        $DB->delete_records('local_dashboard_banners', ['id' => $id]);
    }

    /**
     * Toggle enabled state of a banner.
     *
     * @param int $id
     * @return bool  New enabled state.
     */
    public static function toggle_banner(int $id): bool {
        global $DB, $USER;
        $record = $DB->get_record('local_dashboard_banners', ['id' => $id], '*', MUST_EXIST);
        $record->enabled      = $record->enabled ? 0 : 1;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        $DB->update_record('local_dashboard_banners', $record);
        return (bool)$record->enabled;
    }

    /**
     * Convert a list of banner DB rows to template-ready array context.
     *
     * @param \stdClass[] $banners
     * @return array
     */
    public static function to_template_context(array $banners): array {
        $out = [];
        foreach ($banners as $banner) {
            $imageurl = self::get_banner_image_url((int)$banner->id);
            if ($imageurl === null) {
                continue; // Skip banners without images.
            }
            $out[] = [
                'id'       => (int)$banner->id,
                'name'     => format_string($banner->name),
                'alt_text' => format_string($banner->alt_text ?? $banner->name),
                'link_url' => $banner->link_url ?? '',
                'has_link' => !empty($banner->link_url),
                'imageurl' => $imageurl,
            ];
        }
        return $out;
    }
}
