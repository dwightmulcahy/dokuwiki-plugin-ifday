<?php
declare(strict_types=1);

/**
 * reflect.php
 * - Returns [$evaluator, $method] for Ifday_ConditionEvaluator::evaluateCondition
 */

if (!class_exists('Ifday_ConditionEvaluator')) {
    fwrite(STDERR, "FATAL: Class 'Ifday_ConditionEvaluator' not found. Did syntax.php define it?\n");
    exit(3);
}

$evaluator = new Ifday_ConditionEvaluator();
$reflection = new ReflectionClass($evaluator);
$method = $reflection->getMethod('evaluateCondition');
$method->setAccessible(true);
