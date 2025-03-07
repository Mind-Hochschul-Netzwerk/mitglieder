<?php
/**
 * Enum representing possible user actions on agreements.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Model\Enum;

enum UserAgreementAction: string {
    /**
     * Represents the action of accepting an agreement.
     */
    case Accept = 'accept';

    /**
     * Represents the action of revoking a previously accepted agreement.
     */
    case Revoke = 'revoke';
}

