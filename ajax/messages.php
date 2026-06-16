<?php
/**
 * AJAX endpoint for message counter updates
 * Returns unread message count without full page reload
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

header('Content-Type: application/json');

// Verificar se usuário está logado
require_login();

// Verificar sesskey
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !confirm_sesskey($input['sesskey'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

try {
    // Buscar conversas não lidas (consistente com badge do menu superior)
    // Usar lógica personalizada que funciona corretamente
    $unread = 0;
    if (class_exists('core_message\\api')) {
        $unread = count_unread_conversations_custom($USER->id);
    }
    
    echo json_encode([
        'success' => true,
        'unread' => $unread,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to fetch message count',
        'unread' => 0
    ]);
}

/**
 * Conta conversas não lidas usando query personalizada
 * Replica a mesma lógica do service.php e do badge do menu superior
 * 
 * @param int $userid ID do usuário
 * @return int Número de conversas não lidas
 */
function count_unread_conversations_custom($userid) {
    global $DB;
    
    try {
        // Query que replica exatamente a lógica do badge do menu superior
        $sql = "SELECT COUNT(DISTINCT mc.id)
                FROM {message_conversations} mc
                JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id
                WHERE mcm.userid = ?
                  AND EXISTS (
                      SELECT 1 FROM {messages} m
                      WHERE m.conversationid = mc.id
                        AND m.useridfrom != ?
                        AND NOT EXISTS (
                            SELECT 1 FROM {message_user_actions} mua
                            WHERE mua.messageid = m.id
                              AND mua.userid = ?
                              AND mua.action = ?
                        )
                  )";
        
        return $DB->count_records_sql($sql, [
            $userid,
            $userid, 
            $userid,
            \core_message\api::MESSAGE_ACTION_READ
        ]);
        
    } catch (\Throwable $e) {
        // Em caso de erro, usar API padrão como fallback
        try {
            return \core_message\api::count_unread_conversations($userid);
        } catch (\Throwable $e2) {
            return 0;
        }
    }
}
