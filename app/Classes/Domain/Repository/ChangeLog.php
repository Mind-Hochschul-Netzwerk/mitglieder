<?php
declare(strict_types=1);
namespace MHN\Mitglieder\Domain\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Domain\Model\ChangeLogEntry;
use MHN\Mitglieder\Service\Db;

/**
 * Ein Log mit den Änderungen der Mitgliedsdaten
 */
class ChangeLog implements \MHN\Mitglieder\Interfaces\Singleton
{
    use \MHN\Mitglieder\Traits\Singleton;

    /**
     * Gibt einen Eintrag zurück
     *
     * @var int $id ID des Eintrags
     * @return ChangeLogEntry
     * @throws \OutOfRangeException falls es den Datensatz nicht gibt
     */
    public function getEntryById(int $id) : ChangeLogEntry
    {
        $row = Db::getInstance()->query('SELECT id, userId, changerUserId, dataTimestamp, dataName, oldValue, newValue, info FROM userdata_changelog WHERE id=:id', ['id' => $id])->getRow();
        if ($row === null) {
            throw new \OutOfRangeException('entry not found in userdata_changelog. requested id = ' . $id, 1526591186);
        }
        return $this->makeEntryFromArray($row);
    }

    /**
     * Macht ein ChangeLogEntry-Objekt aus einem Datensatz
     *
     * @var array $row
     * @return ChangeLogEntry
     */
    private function makeEntryFromArray(array $row)
    {
        return new ChangeLogEntry($row['id'], $row['userId'], $row['changerUserId'], new \DateTime($row['dataTimestamp']), $row['dataName'], $row['oldValue'], $row['newValue'], $row['info']);
    }

    /**
     * Gibt alle Einträge eines Mitglieds zurück
     *
     * @var int $userId ID des Mitglieds
     * @return ChangeLogEntry[]
     */
    public function getEntriesByUserId(int $userId) : array
    {
        $entries = [];
        while ($row = Db::getInstance()->query('SELECT id, userId, changerUserId, dataTimestamp, dataName, oldValue, newValue, info FROM userdata_changelog WHERE userId=:userId ORDER BY dataTimestamp, id', ['userId' => $userId])->getRow()) {
            $entries[] = makeEntryFromArray($row);
        }
        return $entries;
    }

    /**
     * Speichert einen neuen Eintrag und löscht den vorherigen Eintrag.
     *
     * @return void
     */
    public function save(ChangeLogEntry $entry)
    {
        if ($entry->getId() !== 0) {
            throw new \UnexpectedValueexception('tried to update existing log entry. not yet implemented', 1526591991);
        }
        $db = Db::getInstance();
        $db->query('DELETE FROM userdata_changelog WHERE userId = :userId AND dataName = :dataName', [
            'userId' => $entry->getUserId(),
            'dataName' => $entry->getDataName()
        ]);
        $id = (int) $db->query('INSERT INTO userdata_changelog SET userId = :userId, changerUserId = :changerUserId, dataTimestamp = :dataTimestamp, dataName = :dataName, oldValue = :oldValue, newValue = :newValue, info = :info', [
            'userId' => $entry->getUserId(),
            'changerUserId' => $entry->getChangerUserId(),
            'dataTimestamp' => $entry->getTimestamp(),
            'dataName' => $entry->getDataName(),
            'oldValue' => $entry->getOldValue(),
            'newValue' => $entry->getNewValue(),
            'info' => $entry->getInfo(),
        ])->getInsertId();
        $entry->setId($id);
    }

    /**
     * Löscht alle Einträge zu einem Benutzer
     *
     * @var int $userId
     * @return void
     */
    public function deleteByUserId(int $userId) {
        Db::getInstance()->query('DELETE FROM userdata_changelog WHERE userId = :userId', ['userId' => $userId]);
    }
}
