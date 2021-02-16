<?php
declare(strict_types=1);
namespace MHN\Mitglieder\Domain\Model;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Repräsentiert einen Eintrag im Mitgliedsdaten-ChangeLog
 */
class ChangeLogEntry
{
    /** @var int ID des Log-Eintrags (0, wenn nicht in der Datenbank) */
    private $id = 0;

    /** @var int ID des bearbeiteten Datensatzes */
    private $userId = '';

    /** @var int ID des bearbeitenden Mitglieds; 0 für System */
    private $changerUserId = '';

    /** @var \DateTime|null */
    private $timestamp = null;

    /** @var string Name des veränderten Eintrags */
    private $dataName = '';

    /** @var string alter Inhalt */
    private $oldValue = '';

    /** @var string neuer Inhalt */
    private $newValue = '';

    /** @var string ggf. zusätzliche Informationen */
    private $info = '';

    /**
     * Konstruktor
     *
     * @var int ID des Log-Eintrags (0, wenn nicht in der Datenbank)
     * @var int ID des bearbeiteten Datensatzes
     * @var int ID des bearbeitenden Mitglieds
     * @var \DateTime|null
     * @var string Name des veränderten Eintrags
     * @var string alter Inhalt
     * @var string neuer Inhalt
     * @var string ggf. zusätzliche Informationen
     */
    public function __construct(int $id, int $userId, int $changerUserId, \DateTime $timestamp, string $dataName, string $oldValue, string $newValue, string $info = '')
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->changerUserId = $changerUserId;
        $this->timestamp = $timestamp;
        $this->dataName = $dataName;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->info = $info;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getUserId() : int
    {
        return $this->userId;
    }

    public function getChangerUserId() : int
    {
        return $this->changerUserId;
    }

    public function getTimestamp() : \DateTime
    {
        return $this->timestamp;
    }

    public function getDataName() : string
    {
        return $this->dataName;
    }

    public function getOldValue() : string
    {
        return $this->oldValue;
    }

    public function getNewValue() : string
    {
        return $this->newValue;
    }

    public function getInfo() : string
    {
        return $this->info;
    }
}
