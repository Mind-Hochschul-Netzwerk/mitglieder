<?php
/**
 * Model representing a user's agreement action.
 *
 * This model tracks whether a user has accepted or revoked an agreement,
 * including metadata such as timestamps and administrative actions.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\UserAgreementAction;
use DateTimeImmutable;

class UserAgreement
{
    /**
     * Constructs a UserAgreement instance.
     *
     * @param int $id The unique identifier of the user agreement.
     * @param User|null $user The user associated with the agreement.
     * @param Agreement|null $agreement The agreement that was accepted or revoked.
     * @param DateTimeImmutable|null $timestamp The timestamp of the action.
     * @param UserAgreementAction|null $action The action taken by the user (accept or revoke).
     * @param UserInfo|null $admin The admin user who recorded the action, if applicable.
     */
    public function __construct(
        public int $id = 0,
        public ?User $user = null,
        public ?Agreement $agreement = null,
        public ?DateTimeImmutable $timestamp = null,
        public ?UserAgreementAction $action = null,
        public ?UserInfo $admin = null,
    )
    {
        if (!$timestamp) {
            $this->timestamp = new \DateTimeImmutable();
        }
    }
}
