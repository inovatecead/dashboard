<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve files from the banners file area.
 *
 * @param \stdClass $course
 * @param \stdClass $cm
 * @param \context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_dashboard_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'banners') {
        return false;
    }

    require_login();

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_dashboard', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
    return true;
}
