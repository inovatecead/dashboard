<?php
namespace local_dashboard;

defined('MOODLE_INTERNAL') || die();

class observers {
  public static function on_login(\core\event\user_loggedin $event) {
    global $USER, $CFG;

    $enabled = get_config('local_dashboard', 'enabledredirect');
    if (empty($enabled)) {
        return;
    }

    // Verificar se não é um usuário guest
    if (isguestuser($USER)) {
        return;
    }

    $flag = optional_param('pp_redirect', 0, PARAM_INT);
    if (!$flag) {
      $url = new \moodle_url('/local/dashboard/index.php', ['pp_redirect' => 1]);
      redirect($url);
    }
  }

  /**
   * Observer para quando uma mensagem é enviada
   * Limpa cache do contador de mensagens não lidas do destinatário
   */
  public static function on_message_sent(\core\event\message_sent $event) {
    $cache = \cache::make('local_dashboard', 'unread_messages');
    
    // Limpar cache do destinatário para forçar recontagem
    $relateduserid = $event->relateduserid;
    if ($relateduserid) {
        $cache->delete("user_{$relateduserid}");
    }
  }

  /**
   * Observer para quando uma mensagem é visualizada
   * Limpa cache do contador de mensagens não lidas do usuário
   */
  public static function on_message_viewed(\core\event\message_viewed $event) {
    $cache = \cache::make('local_dashboard', 'unread_messages');
    
    // Limpar cache do usuário que visualizou para forçar recontagem
    $userid = $event->userid;
    if ($userid) {
        $cache->delete("user_{$userid}");
    }
  }
}
