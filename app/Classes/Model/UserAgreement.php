<?php
namespace App\Model;

use App\Repository\AgreementRepository;
use App\Repository\UserAgreementRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

enum UserAgreementAction: string {
    case Accept = 'accept';
    case Revoke = 'revoke';
}

/**
 * Repräsentiert eine Zustimmung zur Datenschutzverpflichtung
 */
class UserAgreement extends Model
{
    protected static $repositoryClass = UserAgreementRepository::class;

    public function __construct(
        public int $id,
        public User $user,
        public Agreement $commitment,
        public DateTimeImmutable $timestamp,
        public UserAgreementAction $action,
        public ?User $admin = null
    )
    {
    }

    public static function fromDatabase(int $id, int $user_id, int $commitment_id, string $timestamp, string $action, int $admin_id) {
        return new static($id,
            UserRepository::getInstance()->findOneById($user_id),
            AgreementRepository::getInstance()->findOneById($commitment_id),
            new DateTimeImmutable($timestamp),
            UserAgreementAction::from($action),
            $admin_id ?? UserRepository::getInstance()->findOneById($admin_id)
        );
    }
}
