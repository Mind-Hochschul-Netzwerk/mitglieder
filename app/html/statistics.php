<?php
declare(strict_types=1);

/**
 * Statistik
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

require_once '../lib/base.inc.php';

(new \MHN\Mitglieder\Domain\Controller\StatisticsController())->run();
