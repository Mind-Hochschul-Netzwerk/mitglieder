<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * escape
 */
function e(mixed $value): mixed {
    return Tpl::getInstance()->escape($value);
}

/**
 * Template-Engine
 */
class Tpl
{
    const TEMPLATES_DIR = '/var/www/Resources/Private/Templates';

    /**
     * a context is a tuple ['extendedTemplate', 'extendTemplateVariables', 'variables']
     */
    private $contexts = [];

    public $bodyTmp = '';
    public $disableOnShutdown = false;

    private static ?self $instance = null;
    public static function getInstance(): self {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->contexts = [
            [
                'extendedTemplate' => null,
                'extendTemplateVariables' => [],
                'variables' => [],
            ],
        ];
    }

    /**
     * Setzt eine Variable*
     *
     * @param string $var
     * @param mixed $val
     */
    public function set(string $var, $val) {
        $this->getContext()['variables'][$var] = $val;
    }

    public function &getRootContextVariables() {
        return $this->contexts[0]['variables'];
    }

    public function &getParentContextVariables() {
        if (count($this->contexts) < 2) {
            throw new \LogicException('there is no parent context');
        }
        return $this->contexts[count($this->contexts) - 2]['variables'];
    }

    public function &getContextVariables(): array {
        return $this->getContext()['variables'];
    }

    public function &getContext(): array {
        return $this->contexts[count($this->contexts) - 1];
    }

    public function get($var): mixed {
        return array_merge(...array_column($this->contexts, 'variables'))[$var] ?? null;
    }

    public function escape(mixed $value): mixed {
        if (is_array($value)) {
            return array_map(fn($v) => $this->escape($v), $value);
        } elseif (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        } else {
            return $value;
        }
    }

    /**
     * Stellt ein Template dar.
     *
     * @param string $tpl
     * @param bool $display ausgeben und nicht nur zurückgegeben
     * @throws \UnexpectedValueException wenn das Template nicht existiert
     * @return string gerenderter Inhalt
     */
    public function render($templateName, array $variables = [])
    {
        $this->contexts[] = [
            'extendedTemplate' => '',
            'extendedTemplateVariables' => [],
            'variables' => $variables
        ];
        $allVariables = $this->escape(array_merge(...array_column($this->contexts, 'variables')));

        extract($allVariables);

        if (!is_file(self::TEMPLATES_DIR . "/$templateName.tpl.php")) {
            throw new \UnexpectedValueException("Template $templateName existiert nicht.", 1493681481);
        }

        $this->startRecording();
        include self::TEMPLATES_DIR . "/$templateName.tpl.php";
        $contents = $this->stopRecording();

        $context = $this->getContext();

        if ($context['extendedTemplate']) {
            $contents = $this->render($context['extendedTemplate'], [
                ...['@@contents' => $contents],
                ...$context['extendedTemplateVariables']
            ]);
        }

        array_pop($this->contexts);
        return $contents;
    }

    /**
     *
     */
    public function include($templateName, array $variables = []): void {
        echo $this->render($templateName, $variables);
    }

    /**
     * Output-Buffering unterbrechen
     */
    public function stopRecording(): string
    {
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    /**
     * Output-Buffering wieder aufnehmen
     */
    public function startRecording()
    {
        ob_start();
    }

    public function extends(string $templateName, array $variables = []) {
        $this->getContext()['extendedTemplate'] = $templateName;
        $this->getContext()['extendedTemplateVariables'] = $variables;
    }
}
