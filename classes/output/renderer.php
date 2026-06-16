<?php
namespace local_dashboard\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {
    public function render_landing(array $data): string {
        return $this->render_from_template('local_dashboard/landing', $data);
    }
}
