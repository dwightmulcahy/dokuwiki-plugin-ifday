<?php
if (!defined('DOKU_INC')) die();

class action_plugin_ifday_cachetuner extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'onCacheUse');
    }

    public function onCacheUse(Doku_Event $event) {
        // Only touch the XHTML page render cache
        if (empty($event->data) || $event->data->mode !== 'xhtml') return;

        $id = $event->data->page ?? null;
        if (!$id) return;

        // Only act on pages that include <ifday
        $wikitext = rawWiki($id);
        if ($wikitext === '' || stripos($wikitext, '<ifday') === false) return;

        // Compute seconds until next midnight in server time
        $now = time();
        $nextMidnight = strtotime('tomorrow 00:00', $now);
        $age = max(60, $nextMidnight - $now); // floor of 60s to avoid thrash

        // Set cache validity window
        $event->data->depends['age'] = $age;
    }
}
