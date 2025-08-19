<?php
// syntax.php

// This is the main plugin file.
// It acts as a controller, delegating the work to the ConditionEvaluator class.

if (!defined('DOKU_INC')) die();

// Include the new, refactored files
require_once(__DIR__ . '/inc/ConditionEvaluator.php');
require_once(__DIR__ . '/inc/IfdayUtils.php');

class syntax_plugin_ifday extends DokuWiki_Syntax_Plugin {

    public function getType() { return 'protected'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 65; }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<ifday.*?</ifday>', $mode, 'plugin_ifday');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        // Defer to the new evaluator class to handle all the complex logic.
        $evaluator = new Ifday_ConditionEvaluator();
        return $evaluator->handleCondition($match, $this->getConf('show_errors'));
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') {
            return false;
        }

        [$conditionPassed, $content, $errorMsg] = $data;

        // Render the content based on the result from the evaluator
        if (!$conditionPassed) {
            if ($this->getConf('show_errors')) {
                $renderer->doc .= '<div class="plugin_ifday_error" style="border:1px solid red; padding:10px; color:red; font-weight:bold; margin:1em 0;">';
                $renderer->doc .= 'ifday plugin error evaluating condition: "' . htmlspecialchars($content) . '"<br><strong>Details:</strong> ' . htmlspecialchars($errorMsg);
                $renderer->doc .= '</div>';
            }
            return true;
        }

        $renderer->doc .= $content;
        return true;
    }
}
