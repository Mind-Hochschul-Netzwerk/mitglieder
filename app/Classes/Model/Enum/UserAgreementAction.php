<?php
namespace App\Model\Enum;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

enum UserAgreementAction: string {
    case Accept = 'accept';
    case Revoke = 'revoke';
}

