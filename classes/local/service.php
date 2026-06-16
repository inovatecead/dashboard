<?php
namespace local_dashboard\local;

defined('MOODLE_INTERNAL') || die();

class service {
    public static function get_dashboard_data(\stdClass $user): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/lib/filelib.php');

        // Cursos em andamento do usuário com categorias.
        $courses = enrol_get_users_courses($user->id, true, 'id,shortname,fullname,startdate,enddate,visible,category');
        $coursesarr     = [];
        $courseids      = [];
        $coursesByCategory = [];
        $coursesByPolo     = [];

        // Filtrar visíveis e coletar IDs de categorias
        $visiblecourses = [];
        foreach ($courses as $c) {
            if (!$c->visible) { continue; }
            $visiblecourses[] = $c;
            $courseids[]      = $c->id;
        }

        // Carregar categorias em lote (evita N+1 queries)
        $categoryids = array_unique(array_column($visiblecourses, 'category'));
        $categoriesmap = [];
        if (!empty($categoryids)) {
            list($incatids, $catparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
            foreach ($DB->get_records_sql(
                "SELECT id, name, path FROM {course_categories} WHERE id $incatids", $catparams
            ) as $row) {
                $categoriesmap[(int)$row->id] = $row;
            }
        }

        // Extrair IDs de polo (2º elemento do path de cada categoria) e carregar em lote
        $polocatids = [];
        foreach ($categoriesmap as $cat) {
            $parts = array_values(array_filter(explode('/', $cat->path)));
            $polocatids[] = count($parts) >= 2 ? (int)$parts[1] : (int)$parts[0];
        }
        $polocatids = array_unique($polocatids);

        $polocatnamesmap = [];
        if (!empty($polocatids)) {
            list($inpoloids, $poloparams) = $DB->get_in_or_equal($polocatids, SQL_PARAMS_NAMED, 'polo');
            foreach ($DB->get_records_sql(
                "SELECT id, name FROM {course_categories} WHERE id $inpoloids", $poloparams
            ) as $row) {
                // Normalizar: remover prefixo "Polo - " ou "... - Polo - "
                $raw = format_string($row->name);
                $normalized = preg_replace('/^.*Polo\s*[-\x{2013}]\s*/ui', '', $raw);
                $polocatnamesmap[(int)$row->id] = $normalized ?: $raw;
            }
        }

        // === Buscar papéis do usuário em todos os níveis de contexto (em lote) ===
        $systemroles = [];
        $catroles    = [];   // [category_id => [{rolename, roleshort}, ...]]
        $courseroles = [];   // [course_id   => [{rolename, roleshort}, ...]]

        $roleassignments = $DB->get_records_sql(
            "SELECT ra.id, r.name AS rolename, r.shortname,
                    ctx.contextlevel, ctx.instanceid
             FROM {role_assignments} ra
             JOIN {role} r ON r.id = ra.roleid
             JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.userid = :userid",
            ['userid' => $user->id]
        );
        $courseidsflip = array_flip($courseids);
        foreach ($roleassignments as $a) {
            $roledata = ['rolename' => ($a->rolename ?: $a->shortname), 'roleshort' => $a->shortname];
            $cl = (int)$a->contextlevel;
            if ($cl === CONTEXT_SYSTEM) {
                $systemroles[] = $roledata;
            } elseif ($cl === CONTEXT_COURSECAT) {
                $catroles[(int)$a->instanceid][] = $roledata;
            } elseif ($cl === CONTEXT_COURSE && isset($courseidsflip[(int)$a->instanceid])) {
                $courseroles[(int)$a->instanceid][] = $roledata;
            }
        }

        // Construir agrupamentos por categoria e por polo (incluindo papéis)
        $usedrolesmap = [];   // [shortname => rolename] dos papéis efetivamente usados

        foreach ($visiblecourses as $c) {
            $cat = $categoriesmap[(int)$c->category] ?? null;
            $categoryName = $cat ? format_string($cat->name) : get_string('uncategorized', 'moodle');

            // Determinar nome do polo via path
            $poloName = null;
            if ($cat) {
                $parts = array_values(array_filter(explode('/', $cat->path)));
                $polocatid = count($parts) >= 2 ? (int)$parts[1] : (int)$parts[0];
                $poloName  = $polocatnamesmap[$polocatid] ?? null;
            }
            $poloName = $poloName ?: get_string('uncategorized', 'moodle');

            // === Computar papéis do usuário neste curso ===
            $roleslist = $systemroles;

            // Papéis herdados de categoria ancestral
            if ($cat) {
                foreach ($catroles as $catid => $catrolelist) {
                    if (strpos($cat->path . '/', '/' . $catid . '/') !== false
                            || (int)$cat->id === $catid) {
                        foreach ($catrolelist as $cr) {
                            $roleslist[] = $cr;
                        }
                    }
                }
            }

            // Papéis diretos no curso
            if (isset($courseroles[$c->id])) {
                foreach ($courseroles[$c->id] as $r) {
                    $roleslist[] = $r;
                }
            }

            // Deduplicar por shortname
            $seen        = [];
            $uniqueroles = [];
            foreach ($roleslist as $r) {
                if (!isset($seen[$r['roleshort']])) {
                    $seen[$r['roleshort']]       = true;
                    $uniqueroles[]               = $r;
                    $usedrolesmap[$r['roleshort']] = $r['rolename'];
                }
            }

            // Badge: todos os papéis exceto 'student'
            $badgeroles = array_values(array_filter($uniqueroles,
                function ($r) { return $r['roleshort'] !== 'student'; }));

            $courseitem = [
                'id'       => $c->id,
                'fullname' => format_string($c->fullname),
                'url'      => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                'hasbadge' => !empty($badgeroles),
                'roles'    => $badgeroles,
                'rolescsv' => implode(',', array_column($uniqueroles, 'roleshort')),
            ];

            $coursesByCategory[$categoryName][] = $courseitem;
            $coursesByPolo[$poloName][]         = $courseitem;
        }

        // Montar filtro de papéis
        $rolefilters = [];
        foreach ($usedrolesmap as $short => $name) {
            $rolefilters[] = ['roleshort' => $short, 'rolename' => $name ?: $short];
        }
        usort($rolefilters, function ($a, $b) { return strcmp($a['rolename'], $b['rolename']); });
        $hasrolefilter = count($rolefilters) > 1;

        // Converter agrupamentos para formato do template
        ksort($coursesByCategory);
        foreach ($coursesByCategory as $categoryName => $catcourses) {
            $coursesarr[] = [
                'categoryname' => $categoryName,
                'courses'      => $catcourses,
                'coursecount'  => count($catcourses),
            ];
        }

        ksort($coursesByPolo);
        $coursesbypoloarr = [];
        foreach ($coursesByPolo as $poloName => $polocourses) {
            $coursesbypoloarr[] = [
                'categoryname' => $poloName,
                'courses'      => $polocourses,
                'coursecount'  => count($polocourses),
            ];
        }

        // Calendário Acadêmico (substitui anúncios)
        $calendario = self::get_calendario_data($user);

        // Mensagens do usuário
        $messages = [];
        $totalunreadconversations = 0;

        
        if (file_exists($CFG->dirroot . '/message/lib.php')) {
            require_once($CFG->dirroot . '/message/lib.php');
            
            // Buscar todas as conversas do usuário
            $conversations = \core_message\api::get_conversations($user->id, 0, 50);
            
            // Contar conversas com mensagens não lidas
            foreach ($conversations as $conversation) {
                if ($conversation->unreadcount > 0) {
                    $totalunreadconversations++;
                }
            }
            
            // Buscar apenas as conversas com mensagens não lidas (limitando a 5)
            // As conversas já vêm ordenadas por última atividade
            $unreadConversations = array_filter($conversations, function($conversation) {
                return $conversation->unreadcount > 0;
            });
            
            foreach (array_slice($unreadConversations, 0, 5) as $conversation) {
                // Verificar se o usuário ainda tem acesso à conversa
                if (!\core_message\api::is_user_in_conversation($user->id, $conversation->id)) {
                    continue;
                }
                
                $members = \core_message\api::get_conversation_members($user->id, $conversation->id);
                
                // Encontrar o outro usuário na conversa
                $otheruser = null;
                foreach ($members as $member) {
                    if ($member->id != $user->id) {
                        $otheruser = $member;
                        break;
                    }
                }
                
                if ($otheruser) {
                    // Buscar a última mensagem da conversa
                    // Parâmetros: userid, conversationid, limitfrom, limitnum, sort, timefrom
                    // sort = 'timecreated DESC' para pegar a mais recente primeiro
                    $lastmessages = \core_message\api::get_conversation_messages($user->id, $conversation->id, 0, 1, 'timecreated DESC', 0);
                    $lastmessage = !empty($lastmessages['messages']) ? reset($lastmessages['messages']) : null;
                    
                    // Determinar quem enviou a última mensagem
                    $sender_name = '';
                    $message_text = 'Nova mensagem';
                    
                    if ($lastmessage) {
                        $message_text = format_string($lastmessage->text);
                        
                        // Verificar se foi o usuário atual ou o outro usuário que enviou
                        if ($lastmessage->useridfrom == $user->id) {
                            $sender_name = 'Você: ';
                        } else {
                            // Buscar dados do remetente
                            $sender = $DB->get_record('user', ['id' => $lastmessage->useridfrom], 'id,firstname,lastname');
                            $sender_name = $sender ? fullname($sender) . ': ' : '';
                        }
                    }
                    
                    // Gerar URL correta para a conversa
                    $conversation_url = $CFG->wwwroot . '/message/index.php?convid=' . $conversation->id;
                    
                    $messages[] = [
                        'id' => $conversation->id,
                        'name' => fullname($otheruser),
                        'lastmessage' => $message_text,
                        'sendername' => $sender_name,
                        'timeago' => $lastmessage ? userdate($lastmessage->timecreated, get_string('strftimerecent')) : '',
                        'unread' => $conversation->unreadcount > 0, // True se tem mensagens não lidas
                        'unreadcount' => $conversation->unreadcount,
                        'url' => $conversation_url
                    ];
                }
            }
        }

        // Avisos dinâmicos (filtrados por turma do usuário)
        $active_notices = notices_service::get_active_notices((int)$user->id);
        $notices_ctx    = notices_service::to_template_context($active_notices);

        return [
            'courses'        => $coursesarr,
            'coursesempty'   => empty($coursesarr),
            'coursesbypolo'  => $coursesbypoloarr,
            'haspoloview'    => count($coursesbypoloarr) > 1,
            'rolefilters'    => $rolefilters,
            'hasrolefilter'  => $hasrolefilter,
            'calendario' => $calendario,
            'hascalendario' => !empty($calendario['hassemestres']),
            'messages' => $messages,
            'messagesempty' => empty($messages),
            'totalunreadconversations' => $totalunreadconversations,
            'allmessagesurl' => $CFG->wwwroot . '/message/index.php',
            'notices'        => $notices_ctx,
            'hasnotices'     => !empty($notices_ctx),
            'noticesJson'    => json_encode($notices_ctx, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ];
    }
    
    /**
     * Get calendario academico data if plugin is available
     *
     * @param \stdClass $user User object
     * @return array Calendario widget data
     */
    private static function get_calendario_data(\stdClass $user): array {
        global $CFG, $DB;
        
        // Check if report_calendario plugin exists
        $calendario_lib = $CFG->dirroot . '/report/calendario/lib.php';
        if (!file_exists($calendario_lib)) {
            return ['hassemestres' => false];
        }
        
        require_once($calendario_lib);
        
        // Note: View capability check removed - calendar is visible to all logged-in users
        // The manage capability is still checked inside report_calendario_get_widget_data
        
        // Check if function exists
        if (!function_exists('report_calendario_get_widget_data')) {
            return ['hassemestres' => false];
        }
        
        // Get user's turma from custom profile field
        $user_turma = '';
        if (!empty($user->id)) {
            // Get custom profile field ID for turma
            $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'turma'));
            if ($fieldid) {
                $user_turma = $DB->get_field('user_info_data', 'data', array(
                    'userid' => $user->id,
                    'fieldid' => $fieldid
                ));
            }
        }
        
        // Get widget data - show all active semesters, both types, filtered by user's turma
        return report_calendario_get_widget_data($user->id, 0, true, true, $user_turma);
    }
}

