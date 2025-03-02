<?php
namespace App\Model;

use App\Model\Enum\UserAgreementAction;
use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Repräsentiert eine Zustimmung zur Datenschutzverpflichtung
 */
class UserAgreement extends Model
{
    protected static $repositoryClass = UserAgreementRepository::class;

    public function __construct(
        public int $id = 0,
        public ?User $user = null,
        public ?Agreement $agreement = null,
        public ?DateTimeImmutable $timestamp = null,
        public ?UserAgreementAction $action = null,
        public ?User $admin = null
    )
    {
        if (!$timestamp) {
            $this->timestamp = new \DateTimeImmutable();
        }
    }

    public static function fromDatabase(int $id, int $user_id, int $agreement_id, string $timestamp, string $action, ?int $admin_id) {
        return new static($id,
            UserRepository::getInstance()->findOneById($user_id),
            AgreementRepository::getInstance()->findOneById($agreement_id),
            new DateTimeImmutable($timestamp),
            UserAgreementAction::from($action),
            $admin_id ? UserRepository::getInstance()->findOneById($admin_id) : null
        );
    }
}
