<?php
declare(strict_types=1);
namespace App\Service;

/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * template engine
 */
class Tpl extends \Hengeb\Simplates\Engine
{
    public string $proxyClass = TemplateVariable::class;

    private static ?self $instance = null;
    public static function getInstance(): self {
        return self::$instance ??= new self('/var/www/Resources/Private/Templates');
    }
}
