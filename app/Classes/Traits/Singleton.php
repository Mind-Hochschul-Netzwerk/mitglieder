<?php
declare(strict_types=1);
namespace App\Traits;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Trait für Singletons
 */
trait Singleton
{
    private static ?self $instance = null;

    /**
     * Gibt die Instanz der Klasse zurück
     */
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * Kopieren und Instanziieren von Extern verbieten
     */
    private function __clone()
    {
    }

    /**
     * Kopieren und Instanziieren von Extern verbieten
     */
    private function __construct()
    {
    }
}
