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
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

class UserAgreement extends Model
{
    protected static string $repositoryClass = UserAgreementRepository::class;

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
        public ?UserInfo $admin = null
    )
    {
        if (!$timestamp) {
            $this->timestamp = new \DateTimeImmutable();
        }
    }

    /**
     * Creates a UserAgreement instance from database row data.
     *
     * @param int $id The unique identifier of the user agreement.
     * @param int $user_id The ID of the associated user.
     * @param int $agreement_id The ID of the associated agreement.
     * @param string $timestamp The timestamp in string format.
     * @param string $action The action taken by the user.
     * @param string|null $admin_info The user info JSON of the admin who recorded the action, if applicable.
     * @return static A new UserAgreement instance.
     */
    public static function fromDatabase(int $id, int $user_id, int $agreement_id, string $timestamp, string $action, ?string $admin_info): static
    {
        return new static($id,
            UserRepository::getInstance()->findOneById($user_id),
            AgreementRepository::getInstance()->findOneById($agreement_id),
            new DateTimeImmutable($timestamp),
            UserAgreementAction::from($action),
            $admin_info ? UserInfo::fromJson($admin_info) : null
        );
    }
}
