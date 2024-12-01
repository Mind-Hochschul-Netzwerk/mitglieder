<?php
declare(strict_types=1);
namespace App\Service;

use App\Service\TemplateEngine\Engine;

class TemplateVariable extends \App\Service\TemplateEngine\TemplateVariable {
    public function input(...$attributes): string
    {
        $attributes['class'] ??= 'form-control';

        $attributes['uncover'] ??= null;
        $uncover = $attributes['uncover'];
        unset($attributes['uncover']);

        $tag = parent::input(...$attributes);

        return $uncover ? ("<div class='input-group'>$tag" . $uncover->uncoverToggle() . "</div>") : $tag;
    }

    public function select(array $options, ...$attributes): string
    {
        $attributes['class'] ??= 'form-control';

        $attributes['uncover'] ??= null;
        $uncover = $attributes['uncover'];
        unset($attributes['uncover']);

        $tag = parent::select($options, ...$attributes);

        return $uncover ? ("<div class='input-group'>$tag" . $uncover->uncoverToggle() . "</div>") : $tag;
    }

    public function uncoverToggle(): string
    {
        $name ??= $this->name;
        return '<span class="input-group-addon">' . $this->box(
            label: false, class: "input-group-addon", name: $name, dataHeight: 32, dataWidth: 50,
            dataToggle: 'toggle', dataOnstyle: 'success', dataOffstyle: 'danger',
            dataOn: Engine::htmlEscape('<span class="glyphicon glyphicon-eye-open"></span>'),
            dataOff: Engine::htmlEscape('<span class="glyphicon glyphicon-eye-close"></span>')) . '</span>';
    }
}
