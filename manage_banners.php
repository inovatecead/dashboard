<?php
/**
 * Admin page to manage dashboard banners (carrossel de imagens).
 *
 * Supports: list, add, edit, delete, toggle, reorder (via action param).
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_dashboard\local\banners_service;

admin_externalpage_setup('local_dashboard_banners');

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/dashboard/manage_banners.php'));
$PAGE->set_title(get_string('banners_manage', 'local_dashboard'));
$PAGE->set_heading(get_string('banners_manage', 'local_dashboard'));

// ── REORDER (AJAX) ───────────────────────────────────────────────────────────
if ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $ids = optional_param_array('ids', [], PARAM_INT);
    global $DB, $USER;
    foreach ($ids as $pos => $bid) {
        if ($bid > 0) {
            $DB->set_field('local_dashboard_banners', 'sortorder',    (int)$pos,       ['id' => (int)$bid]);
            $DB->set_field('local_dashboard_banners', 'timemodified',  time(),          ['id' => (int)$bid]);
            $DB->set_field('local_dashboard_banners', 'usermodified',  (int)$USER->id,  ['id' => (int)$bid]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    banners_service::delete_banner($id);
    redirect(
        new moodle_url('/local/dashboard/manage_banners.php'),
        get_string('banner_deleted', 'local_dashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── TOGGLE ───────────────────────────────────────────────────────────────────
if ($action === 'toggle' && $id > 0) {
    require_sesskey();
    banners_service::toggle_banner($id);
    redirect(new moodle_url('/local/dashboard/manage_banners.php'));
}

// ── SAVE (insert or update) ──────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $data = new \stdClass();
    $data->id        = optional_param('bannerid', 0, PARAM_INT);
    $data->name      = required_param('name', PARAM_TEXT);
    $data->alt_text  = optional_param('alt_text', '', PARAM_TEXT);
    $data->link_url  = optional_param('link_url', '', PARAM_URL);
    $data->enabled   = optional_param('enabled', 0, PARAM_INT);
    $data->sortorder = optional_param('sortorder', 0, PARAM_INT);
    $data->turma     = optional_param_array('turma', [], PARAM_TEXT);

    $bannerid = banners_service::save_banner($data);

    // Handle image upload.
    if (!empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        banners_service::save_banner_image($bannerid, $_FILES['banner_image']);
    }

    redirect(
        new moodle_url('/local/dashboard/manage_banners.php'),
        get_string('banner_saved', 'local_dashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── FORM (add / edit) ────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $record = null;
    if ($action === 'edit' && $id > 0) {
        $record = banners_service::get_banner($id);
        if (!$record) {
            redirect(new moodle_url('/local/dashboard/manage_banners.php'));
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'edit'
        ? get_string('banner_edit', 'local_dashboard')
        : get_string('banner_add', 'local_dashboard'));

    $formurl   = new moodle_url('/local/dashboard/manage_banners.php', ['action' => 'save']);
    $name      = $record ? s($record->name) : '';
    $alt_text  = $record ? s($record->alt_text ?? '') : '';
    $link_url  = $record ? s($record->link_url ?? '') : '';
    $enabled   = $record ? (int)$record->enabled : 1;
    $sort      = $record ? (int)$record->sortorder : 0;
    $bannerid  = $record ? (int)$record->id : 0;
    $checked   = $enabled ? ' checked' : '';
    $required  = $bannerid > 0 ? '' : ' required';  // File required only for new banners.
    $turma_val = $record ? ($record->turma ?? '') : '';
    $turma_array = !empty($turma_val) ? explode(',', $turma_val) : [];
    $checked2022 = in_array('2022', $turma_array) ? ' checked' : '';
    $checked2024 = in_array('2024', $turma_array) ? ' checked' : '';

    // Get current image preview.
    $imagepreview = '';
    if ($record && $record->id) {
        $imgurl = banners_service::get_banner_image_url((int)$record->id);
        if ($imgurl) {
            $imagepreview = '<div style="margin-top:8px;max-width:400px;border:1px solid #dee2e6;border-radius:8px;padding:4px;background:#f8f9fa;">'
                          . '<img src="' . $imgurl . '" alt="Preview" style="max-width:100%;max-height:160px;border-radius:6px;display:block;">'
                          . '<p style="margin:4px 0 0;font-size:0.78rem;color:#6b7280;">Imagem atual</p></div>';
        }
    }

    echo <<<HTML
    <style>
      .banner-form{max-width:680px;}
    </style>

    <form method="post" action="{$formurl}" class="mform banner-form" enctype="multipart/form-data" id="banner-form">
      <input type="hidden" name="sesskey" value="{$USER->sesskey}">
      <input type="hidden" name="bannerid" value="{$bannerid}">

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="bname">Nome <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <input type="text" id="bname" name="name" class="form-control" required maxlength="255" value="{$name}">
          <small class="form-text text-muted">Nome interno para identificação do banner.</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="bimage">Imagem <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <input type="file" id="bimage" name="banner_image" accept="image/png,image/jpeg,image/gif,image/webp" class="form-control-file" {$required}>
          <small class="form-text text-muted">Formatos aceitos: JPG, PNG, GIF, WebP. Tamanho recomendado: 1200×400px.</small>
          {$imagepreview}
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="balt">Texto alternativo</label>
        <div class="col-md-9">
          <input type="text" id="balt" name="alt_text" class="form-control" maxlength="255" value="{$alt_text}">
          <small class="form-text text-muted">Descrição para acessibilidade (leitores de tela).</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="blink">Link de destino</label>
        <div class="col-md-9">
          <input type="url" id="blink" name="link_url" class="form-control" maxlength="255" value="{$link_url}" placeholder="https://...">
          <small class="form-text text-muted">URL para onde o banner deve redirecionar ao clicar (opcional).</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label" for="bsort">Ordem</label>
        <div class="col-md-9">
          <input type="number" id="bsort" name="sortorder" class="form-control form-control-sm" style="max-width:100px;" value="{$sort}" min="0">
          <small class="form-text text-muted">Número menor aparece primeiro.</small>
        </div>
      </div>

      <div class="form-group row">
        <label class="col-md-3 col-form-label">Turma</label>
        <div class="col-md-9">
          <div style="display:flex;gap:16px;padding-top:6px;">
            <label class="custom-control custom-checkbox" style="font-weight:normal;">
              <input type="checkbox" name="turma[]" value="2022" class="custom-control-input"{$checked2022}>
              <span class="custom-control-label">Turma 2022</span>
            </label>
            <label class="custom-control custom-checkbox" style="font-weight:normal;">
              <input type="checkbox" name="turma[]" value="2024" class="custom-control-input"{$checked2024}>
              <span class="custom-control-label">Turma 2024</span>
            </label>
            <small class="text-muted" style="padding-top:2px;">(deixe ambos desmarcados = todas as turmas)</small>
          </div>
        </div>
      </div>

      <div class="form-group row">
        <div class="col-md-9 offset-md-3">
          <div class="custom-control custom-checkbox">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" id="benabled" name="enabled" value="1" class="custom-control-input"{$checked}>
            <label class="custom-control-label" for="benabled">Ativo (visível no dashboard)</label>
          </div>
        </div>
      </div>

      <div class="form-group row">
        <div class="col-md-9 offset-md-3">
          <button type="submit" class="btn btn-primary">Salvar</button>
          <a href="/local/dashboard/manage_banners.php" class="btn btn-secondary ml-2">Cancelar</a>
        </div>
      </div>
    </form>
    HTML;

    echo $OUTPUT->footer();
    exit;
}

// ── LIST ─────────────────────────────────────────────────────────────────────
$banners = banners_service::get_all_banners();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('banners_manage', 'local_dashboard'));

$addurl = new moodle_url('/local/dashboard/manage_banners.php', ['action' => 'add']);
echo '<div style="margin-bottom:1rem;"><a href="' . $addurl . '" class="btn btn-primary">'
    . get_string('banner_add', 'local_dashboard') . '</a></div>';

if (empty($banners)) {
    echo $OUTPUT->notification(get_string('banners_empty', 'local_dashboard'), 'info');
} else {
    $reorder_url = (new moodle_url('/local/dashboard/manage_banners.php',
        ['action' => 'reorder', 'sesskey' => sesskey()]))->out(false);

    echo '<style>
        .banners-table{width:100%;border-collapse:collapse;font-size:0.9rem;}
        .banners-table th{background:#f8f9fa;padding:8px 10px;text-align:left;border-bottom:2px solid #dee2e6;white-space:nowrap;}
        .banners-table td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:middle;}
        .banners-table tr.dragging{opacity:0.4;}
        .banners-table tr.drag-over td{border-top:3px solid #3b82f6;}
        .drag-handle{cursor:grab;color:#9ca3af;font-size:1.1rem;user-select:none;padding-right:4px;}
        .drag-handle:active{cursor:grabbing;}
        .banner-thumb{width:80px;height:45px;object-fit:cover;border-radius:4px;border:1px solid #e5e7eb;display:block;}
    </style>';

    echo '<table class="banners-table" id="banners-sortable">';
    echo '<thead><tr>
        <th style="width:32px;"></th>
        <th style="width:90px;">Imagem</th>
        <th>Nome</th>
        <th>Link</th>
        <th>Turma</th>
        <th>Ativo</th>
        <th>Ações</th>
    </tr></thead><tbody>';

    foreach ($banners as $b) {
        $editurl   = (new moodle_url('/local/dashboard/manage_banners.php',
            ['action' => 'edit', 'id' => $b->id]))->out(false);
        $deleteurl = (new moodle_url('/local/dashboard/manage_banners.php',
            ['action' => 'delete', 'id' => $b->id, 'sesskey' => sesskey()]))->out(false);
        $toggleurl = (new moodle_url('/local/dashboard/manage_banners.php',
            ['action' => 'toggle', 'id' => $b->id, 'sesskey' => sesskey()]))->out(false);

        $active_badge = $b->enabled
            ? '<span class="badge badge-success">✓ Sim</span>'
            : '<span class="badge badge-secondary">✗ Não</span>';

        $link_display = !empty($b->link_url)
            ? '<a href="' . s($b->link_url) . '" target="_blank" rel="noopener" style="font-size:0.8rem;">' . s(shorten_text($b->link_url, 30)) . '</a>'
            : '<span class="text-muted">—</span>';

        // Get thumbnail.
        $thumb_html = '<span class="text-muted" style="font-size:0.75rem;">Sem imagem</span>';
        $imgurl = banners_service::get_banner_image_url((int)$b->id);
        if ($imgurl) {
            $thumb_html = '<img src="' . $imgurl . '" alt="" class="banner-thumb">';
        }

        $turma_display = !empty($b->turma) ? '<span class="badge badge-info">' . s($b->turma) . '</span>' : '<span class="text-muted">Todas</span>';

        $toggle_label = $b->enabled ? 'Desativar' : 'Ativar';
        $actions = '<a href="' . $editurl   . '" class="btn btn-xs btn-outline-primary mr-1">Editar</a>'
                 . '<a href="' . $toggleurl . '" class="btn btn-xs btn-outline-secondary mr-1">' . $toggle_label . '</a>'
                 . '<a href="' . $deleteurl . '" class="btn btn-xs btn-outline-danger"'
                 . ' onclick="return confirm(\'Excluir este banner?\')">Excluir</a>';

        echo '<tr draggable="true" data-id="' . (int)$b->id . '">
            <td class="drag-handle" draggable="true">⠿</td>
            <td>' . $thumb_html . '</td>
            <td><strong>' . s($b->name) . '</strong></td>
            <td>' . $link_display . '</td>
            <td>' . $turma_display . '</td>
            <td>' . $active_badge . '</td>
            <td>' . $actions . '</td>
        </tr>';
    }

    echo '</tbody></table>';

    // Drag-and-drop reorder JavaScript.
    echo <<<JS
    <script>
    (function() {
        var table = document.getElementById('banners-sortable');
        if (!table) return;
        var dragSrc = null;
        var rows = table.querySelectorAll('tbody tr');

        rows.forEach(function(row) {
            var handle = row.querySelector('.drag-handle');
            if (!handle) return;

            handle.addEventListener('dragstart', function(e) {
                dragSrc = row;
                row.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', function() {
                row.classList.remove('dragging');
            });

            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (dragSrc && dragSrc !== row) {
                    row.classList.add('drag-over');
                }
            });

            row.addEventListener('dragleave', function() {
                row.classList.remove('drag-over');
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                row.classList.remove('drag-over');
                if (dragSrc && dragSrc !== row) {
                    var tbody = table.querySelector('tbody');
                    var rowsArray = Array.from(tbody.querySelectorAll('tr'));
                    var fromIdx = rowsArray.indexOf(dragSrc);
                    var toIdx   = rowsArray.indexOf(row);
                    if (fromIdx < toIdx) {
                        row.parentNode.insertBefore(dragSrc, row.nextSibling);
                    } else {
                        row.parentNode.insertBefore(dragSrc, row);
                    }
                    saveReorder();
                }
            });
        });

        function saveReorder() {
            var tbody = table.querySelector('tbody');
            var rowsArray = tbody.querySelectorAll('tr');
            var ids = [];
            rowsArray.forEach(function(r) { ids.push(r.getAttribute('data-id')); });
            fetch('{$reorder_url}', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ids: ids})
            });
        }
    })();
    </script>
    JS;
}

echo $OUTPUT->footer();
