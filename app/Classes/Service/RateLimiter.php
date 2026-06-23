<?php
declare(strict_types=1);
namespace App\Service;

use Hengeb\Db\Db;
use Hengeb\Router\Exception\InvalidUserDataException;

class RateLimiter
{
    public function __construct(private Db $db) {}

    public function attempt(string $action, string $identifier, int $maxAttempts, int $windowSeconds): void
    {
        $this->check($action, $identifier, $maxAttempts, $windowSeconds);
        $this->record($action, $identifier);
    }

    public function check(string $action, string $identifier, int $maxAttempts, int $windowSeconds): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
        $this->db->query(
            'DELETE FROM rate_limit WHERE action=:action AND identifier=:identifier AND created_at < :cutoff',
            compact('action', 'identifier', 'cutoff')
        );
        $count = (int) $this->db->query(
            'SELECT COUNT(*) FROM rate_limit WHERE action=:action AND identifier=:identifier',
            compact('action', 'identifier')
        )->get();
        if ($count >= $maxAttempts) {
            throw new InvalidUserDataException('Zu viele Versuche. Bitte warte einen Moment.');
        }
    }

    public function record(string $action, string $identifier): void
    {
        $this->db->query(
            'INSERT INTO rate_limit (action, identifier) VALUES (:action, :identifier)',
            compact('action', 'identifier')
        );
    }
}
