<?php
declare(strict_types=1);
namespace App\Router\Attribute;

use Attribute;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Route {
    public function __construct(public string $matcher)
    {
    }
}
