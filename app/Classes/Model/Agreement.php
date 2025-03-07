<?php
/**
 * Model representing an agreement with version control.
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
declare(strict_types=1);

namespace App\Model;

use App\Repository\AgreementRepository;
use DateTimeImmutable;
use Hengeb\Db\Db;

class Agreement extends Model
{
    protected static string $repositoryClass = AgreementRepository::class;

    /**
     * Constructs an Agreement instance.
     *
     * @param int $id The unique identifier of the agreement.
     * @param string $name The name of the agreement.
     * @param int $version The version number of the agreement.
     * @param string $text The textual content of the agreement.
     * @param DateTimeImmutable|null $timestamp The timestamp when the agreement was created or modified.
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public int $version = 0,
        public string $text = '',
        public ?DateTimeImmutable $timestamp = null
    )
    {
        if (!$timestamp) {
            $this->timestamp = new DateTimeImmutable();
        }
    }

    /**
     * Creates an Agreement instance from database row data.
     *
     * @param int $id The unique identifier of the agreement.
     * @param string $name The name of the agreement.
     * @param int $version The version number of the agreement.
     * @param string $text The textual content of the agreement.
     * @param string $timestamp The timestamp in string format.
     * @return static A new Agreement instance.
     */
    public static function fromDatabase(int $id, string $name = '', int $version = 0, string $text = '', string $timestamp): static
    {
        return new static($id, $name, $version, $text, new DateTimeImmutable($timestamp));
    }

    public function countUsers(): int
    {
        return Db::getInstance()->query('WITH relevant_entries AS (
          SELECT ua.*
            FROM user_agreements ua
            JOIN agreements a ON ua.agreement_id = a.id
            WHERE a.id = :agreement_id AND ua.action = "accept"
          ),
          latest_entries AS (
            SELECT ua.user_id, MAX(ua.id) AS max_id
            FROM user_agreements ua
            JOIN agreements a ON ua.agreement_id = a.id
            WHERE a.name = (SELECT name FROM agreements WHERE id = :agreement_id)
            GROUP BY ua.user_id
          )
          SELECT COUNT(*)
          FROM relevant_entries r
          LEFT JOIN latest_entries l
          ON r.user_id = l.user_id
          WHERE r.id = l.max_id;', [
            'agreement_id' => $this->id,
          ])->get();
    }
}
