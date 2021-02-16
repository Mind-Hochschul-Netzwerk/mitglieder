<?php
declare(strict_types=1);
namespace MHN\Mitglieder\Domain\Repository;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Domain\Model\ChangeLogEntry;
use MHN\Mitglieder\DB;
use MHN\Mitglieder\DB_Result;

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
        $row = DB::query('SELECT id, userId, changerUserId, dataTimestamp, dataName, oldValue, newValue, info FROM userdata_changelog WHERE id=%d', $id)->get_row();
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
        while ($row = DB::query('SELECT id, userId, changerUserId, dataTimestamp, dataName, oldValue, newValue, info FROM userdata_changelog WHERE userId=%d ORDER BY dataTimestamp, id', $userId)->get_row()) {
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
        DB::query('DELETE FROM userdata_changelog WHERE userId = %d AND dataName = "%s"', $entry->getUserId(), $entry->getDataName());
        DB::query('INSERT INTO userdata_changelog SET userId = %d, changerUserId = %d, dataTimestamp = "%s", dataName = "%s", oldValue = "%s", newValue = "%s", info = "%s"',
            $entry->getUserId(), $entry->getChangerUserId(), $entry->getTimestamp()->format('Y-m-d H:i:s'), $entry->getDataName(), $entry->getOldValue(), $entry->getNewValue(), $entry->getInfo()
        );
        $entry->setId(DB::insert_id());
    }

    /**
     * Löscht alle Einträge zu einem Benutzer
     *
     * @var int $userId
     * @return void
     */
    public function deleteByUserId(int $userId) {
        DB::query('DELETE FROM userdata_changelog WHERE userId = %d', $userId);
    }
}
