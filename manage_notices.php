<?php
/**
 * Admin page to manage dashboard notices (avisos).
 *
 * Supports: list, add, edit, delete, toggle, reorder (via action param).
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_dashboard\local\notices_service;

admin_externalpage_setup('local_dashboard_notices');

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/dashboard/manage_notices.php'));
$PAGE->set_title(get_string('notices_manage', 'local_dashboard'));
$PAGE->set_heading(get_string('notices_manage', 'local_dashboard'));

// ── REORDER (AJAX) ───────────────────────────────────────────────────────────
if ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $ids = optional_param_array('ids', [], PARAM_INT);
    global $DB, $USER;
    foreach ($ids as $pos => $nid) {
        if ($nid > 0) {
            $DB->set_field('local_dashboard_notices', 'sortorder',  (int)$pos,       ['id' => (int)$nid]);
            $DB->set_field('local_dashboard_notices', 'timemodified', time(),         ['id' => (int)$nid]);
            $DB->set_field('local_dashboard_notices', 'usermodified', (int)$USER->id, ['id' => (int)$nid]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    notices_service::delete_notice($id);
    redirect(
        new moodle_url('/local/dashboard/manage_notices.php'),
        get_string('notice_deleted', 'local_dashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── TOGGLE ───────────────────────────────────────────────────────────────────
if ($action === 'toggle' && $id > 0) {
    require_sesskey();
    notices_service::toggle_notice($id);
    redirect(new moodle_url('/local/dashboard/manage_notices.php'));
}

// ── SAVE (insert or update) ──────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $data = new \stdClass();
    $data->id         = optional_param('noticeid', 0, PARAM_INT);
    $data->title      = required_param('title', PARAM_TEXT);
    $data->body       = optional_param('body', '', PARAM_CLEANHTML);
    $data->type       = required_param('type', PARAM_ALPHA);
    $data->enabled    = optional_param('enabled', 0, PARAM_INT);
    $data->sortorder  = optional_param('sortorder', 0, PARAM_INT);

    $date_start_str = optional_param('date_start', '', PARAM_RAW_TRIMMED);
    $date_end_str   = optional_param('date_end',   '', PARAM_RAW_TRIMMED);
    $data->date_start = $date_start_str ? strtotime($date_start_str) : null;
    $data->date_end   = $date_end_str   ? strtotime($date_end_str)   : null;

    notices_service::save_notice($data);
    redirect(
        new moodle_url('/local/dashboard/manage_notices.php'),
        get_string('notice_saved', 'local_dashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── FORM (add / edit) ────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $record = null;
    if ($action === 'edit' && $id > 0) {
        $record = notices_service::get_notice($id);
        if (!$record) {
            redirect(new moodle_url('/local/dashboard/manage_notices.php'));
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'edit'
        ? get_string('notice_edit', 'local_dashboard')
        : get_string('notice_add', 'local_dashboard'));

    $formurl  = new moodle_url('/local/dashboard/manage_notices.php', ['action' => 'save']);
    $title    = $record ? s($record->title) : '';
    // Body goes into contenteditable as raw HTML (not escaped).
    $body_html = $record ? ($record->body ?? '') : '';
    $type     = $record ? $record->type       : 'info';
    $enabled  = $record ? (int)$record->enabled : 1;
    $sort     = $record ? (int)$record->sortorder : 0;
    $noticeid = $record ? (int)$record->id    : 0;

    $date_start_val = ($record && $record->date_start) ? date('Y-m-d', $record->date_start) : '';
    $date_end_val   = ($record && $record->date_end)   ? date('Y-m-d', $record->date_end)   : '';

    $types = notices_service::TYPES;
    $type_options = '';
    foreach ($types as $t) {
        $sel = ($t === $type) ? ' selected' : '';
        $label = get_string('notice_type_' . $t, 'local_dashboard');
        $type_options .= "<option value=\"{$t}\"{$sel}>{$label}</option>";
    }

    $checked = $enabled ? ' checked' : '';

    // Encode body for safe JS embedding.
    $body_js = json_encode($body_html);

    echo <<<HTML
    <style>
      .rte-toolbar{display:flex;gap:4px;padding:6px 8px;background:#f8fafc;border:1px solid #dee2e6;border-bottom:none;border-radius:6px 6px 0 0;}
      .rte-btn{padding:3px 10px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer;font-size:0.85rem;line-height:1.5;color:#374151;transition:background 0.15s;}
      .rte-btn:hover{background:#e5e7eb;}
      .rte-btn b{font-weight:700;}
      .rte-editor{min-height:80px;padding:8px 12px;border:1px solid #dee2e6;border-radius:0 0 6px 6px;background:#fff;font-size:0.95rem;line-height:1.5;outline:none;}
      .rte-editor:focus{border-color:#86b7fe;box-shadow:0 0 0 3px rgba(13,110,253,.15);}
      .rte-editor a{color:#2563eb;text-decoration:underline;}
    </style>

    <form method="post" action="{$formurl}" class="mform" style="max-width:680px;" id="notice-form">
      <input type="hidden" name="sesskey" value="{$USER->sesskey}">
      <input type="hidden" name="noticeid" value="{$noticeid}">

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="ntitle">Título <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <input type="text" id="ntitle" name="title" class="form-control" required maxlength="255" value="{$title}">
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label">Texto</label>
        <div class="col-md-9">
          <!-- Toolbar -->
          <div class="rte-toolbar" role="toolbar" aria-label="Formatação">
            <button type="button" class="rte-btn" onclick="rteCmd('bold')" title="Negrito (Ctrl+B)"><b>N</b></button>
            <button type="button" class="rte-btn" onclick="rteCmd('italic')" title="Itálico (Ctrl+I)"><i>I</i></button>
            <button type="button" class="rte-btn" onclick="rteLink()" title="Inserir link">🔗 Link</button>
            <button type="button" class="rte-btn" onclick="rteCmd('removeFormat')" title="Remover formatação">✕ Limpar</button>
          </div>
          <!-- Editor -->
          <div id="rte-editor" class="rte-editor" contenteditable="true" aria-multiline="true"
               aria-label="Conteúdo do aviso"></div>
          <!-- Hidden field submitted with the form -->
          <textarea id="nbody" name="body" style="display:none;"></textarea>
          <small class="form-text text-muted mt-1">Opcional. Suporta negrito, itálico e links.</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="ntype">Tipo</label>
        <div class="col-md-9">
          <select id="ntype" name="type" class="form-control form-control-sm" style="max-width:200px;">
            {$type_options}
          </select>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="nsort">Ordem</label>
        <div class="col-md-9">
          <input type="number" id="nsort" name="sortorder" class="form-control form-control-sm" style="max-width:100px;" value="{$sort}" min="0">
          <small class="form-text text-muted">Número menor aparece primeiro.</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="nstart">Exibir de</label>
        <div class="col-md-9 d-flex align-items-center" style="gap:10px;flex-wrap:wrap;">
          <input type="date" id="nstart" name="date_start" class="form-control form-control-sm" style="max-width:160px;" value="{$date_start_val}">
          <span>até</span>
          <input type="date" id="nend" name="date_end" class="form-control form-control-sm" style="max-width:160px;" value="{$date_end_val}">
          <small class="text-muted">(deixe vazio = sem restrição)</small>
        </div>
      </div>

      <div class="form-group row">
        <div class="col-md-9 offset-md-3">
          <div class="custom-control custom-checkbox">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" id="nenabled" name="enabled" value="1" class="custom-control-input"{$checked}>
            <label class="custom-control-label" for="nenabled">Ativo (visível no dashboard)</label>
          </div>
        </div>
      </div>

      <div class="form-group row">
        <div class="col-md-9 offset-md-3">
          <button type="submit" class="btn btn-primary">Salvar</button>
          <a href="/local/dashboard/manage_notices.php" class="btn btn-secondary ml-2">Cancelar</a>
        </div>
      </div>
    </form>

    <script>
    (function() {
        var editor  = document.getElementById('rte-editor');
        var hidden  = document.getElementById('nbody');
        var form    = document.getElementById('notice-form');

        // Load existing content into editor.
        var initial = {$body_js};
        if (initial) { editor.innerHTML = initial; }

        // Before submit: copy editor HTML into the hidden textarea.
        form.addEventListener('submit', function() {
            hidden.value = editor.innerHTML;
        });

        window.rteCmd = function(cmd) {
            editor.focus();
            document.execCommand(cmd, false, null);
        };

        window.rteLink = function() {
            var sel = window.getSelection();
            var text = sel && sel.toString() ? sel.toString() : '';
            var url  = prompt('URL do link (ex: https://...):', 'https://');
            if (!url) { return; }
            editor.focus();
            if (text) {
                document.execCommand('createLink', false, url);
                // Ensure target=_blank on newly created link.
                var links = editor.querySelectorAll('a[href="' + url + '"]');
                links.forEach(function(a) { a.target = '_blank'; a.rel = 'noopener'; });
            } else {
                var linkText = prompt('Texto do link:', url);
                if (!linkText) { return; }
                var a = document.createElement('a');
                a.href = url; a.target = '_blank'; a.rel = 'noopener';
                a.textContent = linkText;
                document.execCommand('insertHTML', false, a.outerHTML);
            }
        };
    })();
    </script>
    HTML;

    echo $OUTPUT->footer();
    exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$notices = notices_service::get_all_notices();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notices_manage', 'local_dashboard'));

$addurl = new moodle_url('/local/dashboard/manage_notices.php', ['action' => 'add']);
echo '<div style="margin-bottom:1rem;"><a href="' . $addurl . '" class="btn btn-primary">'
    . get_string('notice_add', 'local_dashboard') . '</a></div>';

if (empty($notices)) {
    echo $OUTPUT->notification(get_string('notices_empty', 'local_dashboard'), 'info');
} else {
    $reorder_url = (new moodle_url('/local/dashboard/manage_notices.php',
        ['action' => 'reorder', 'sesskey' => sesskey()]))->out(false);

    $type_labels = [
        'info'    => '<span class="badge badge-info">Info</span>',
        'warning' => '<span class="badge badge-warning">Aviso</span>',
        'danger'  => '<span class="badge badge-danger">Urgente</span>',
        'success' => '<span class="badge badge-success">Sucesso</span>',
    ];

    // Build drag-and-drop table manually (html_table doesn't support row attributes).
    echo '<style>
        .notices-table{width:100%;border-collapse:collapse;font-size:0.9rem;}
        .notices-table th{background:#f8f9fa;padding:8px 10px;text-align:left;border-bottom:2px solid #dee2e6;white-space:nowrap;}
        .notices-table td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:middle;}
        .notices-table tr.dragging{opacity:0.4;}
        .notices-table tr.drag-over td{border-top:3px solid #3b82f6;}
        .drag-handle{cursor:grab;color:#9ca3af;font-size:1.1rem;user-select:none;padding-right:4px;}
        .drag-handle:active{cursor:grabbing;}
    </style>';

    echo '<table class="notices-table" id="notices-sortable">';
    echo '<thead><tr>
        <th style="width:32px;"></th>
        <th>Título</th>
        <th>Tipo</th>
        <th>Ativo</th>
        <th>Início</th>
        <th>Fim</th>
        <th>Ações</th>
    </tr></thead><tbody>';

    foreach ($notices as $n) {
        $editurl   = (new moodle_url('/local/dashboard/manage_notices.php',
            ['action' => 'edit', 'id' => $n->id]))->out(false);
        $deleteurl = (new moodle_url('/local/dashboard/manage_notices.php',
            ['action' => 'delete', 'id' => $n->id, 'sesskey' => sesskey()]))->out(false);
        $toggleurl = (new moodle_url('/local/dashboard/manage_notices.php',
            ['action' => 'toggle', 'id' => $n->id, 'sesskey' => sesskey()]))->out(false);

        $active_badge = $n->enabled
            ? '<span class="badge badge-success">✓ Sim</span>'
            : '<span class="badge badge-secondary">✗ Não</span>';
        $date_start = $n->date_start ? userdate($n->date_start, '%d/%m/%Y') : '—';
        $date_end   = $n->date_end   ? userdate($n->date_end,   '%d/%m/%Y') : '—';
        $type_badge = $type_labels[$n->type] ?? s($n->type);

        $toggle_label = $n->enabled ? 'Desativar' : 'Ativar';
        $actions = '<a href="' . $editurl   . '" class="btn btn-xs btn-outline-primary mr-1">Editar</a>'
                 . '<a href="' . $toggleurl . '" class="btn btn-xs btn-outline-secondary mr-1">' . $toggle_label . '</a>'
                 . '<a href="' . $deleteurl . '" class="btn btn-xs btn-outline-danger"'
                 . ' onclick="return confirm(\'Excluir este aviso?\')">Excluir</a>';

        echo '<tr draggable="true" data-id="' . (int)$n->id . '">'
           . '<td><span class="drag-handle" title="Arrastar para reordenar">⠿</span></td>'
           . '<td>' . s($n->title) . '</td>'
           . '<td>' . $type_badge . '</td>'
           . '<td>' . $active_badge . '</td>'
           . '<td>' . $date_start . '</td>'
           . '<td>' . $date_end   . '</td>'
           . '<td>' . $actions    . '</td>'
           . '</tr>';
    }

    echo '</tbody></table>';

    // Drag-and-drop JS.
    echo '<script>
    (function() {
        var tbody    = document.querySelector("#notices-sortable tbody");
        var reorderUrl = ' . json_encode($reorder_url) . ';
        var sesskey    = ' . json_encode(sesskey()) . ';
        var dragged  = null;

        tbody.addEventListener("dragstart", function(e) {
            dragged = e.target.closest("tr");
            if (!dragged) { return; }
            dragged.classList.add("dragging");
            e.dataTransfer.effectAllowed = "move";
        });

        tbody.addEventListener("dragend", function() {
            if (dragged) { dragged.classList.remove("dragging"); dragged = null; }
            tbody.querySelectorAll("tr").forEach(function(r) { r.classList.remove("drag-over"); });
        });

        tbody.addEventListener("dragover", function(e) {
            e.preventDefault();
            var target = e.target.closest("tr");
            if (!target || target === dragged) { return; }
            tbody.querySelectorAll("tr").forEach(function(r) { r.classList.remove("drag-over"); });
            target.classList.add("drag-over");
        });

        tbody.addEventListener("drop", function(e) {
            e.preventDefault();
            var target = e.target.closest("tr");
            if (!target || target === dragged || !dragged) { return; }
            target.classList.remove("drag-over");

            // Insert dragged before or after target depending on mouse position.
            var rect = target.getBoundingClientRect();
            var mid  = rect.top + rect.height / 2;
            if (e.clientY < mid) {
                tbody.insertBefore(dragged, target);
            } else {
                tbody.insertBefore(dragged, target.nextSibling);
            }

            saveOrder();
        });

        function saveOrder() {
            var ids = [];
            tbody.querySelectorAll("tr[data-id]").forEach(function(r) {
                ids.push(parseInt(r.getAttribute("data-id"), 10));
            });
            var form = new FormData();
            form.append("sesskey", sesskey);
            ids.forEach(function(id) { form.append("ids[]", id); });

            fetch(reorderUrl, { method: "POST", body: form })
                .then(function(r) { return r.json(); })
                .catch(function() { /* silent */ });
        }
    })();
    </script>';
}

echo $OUTPUT->footer();

