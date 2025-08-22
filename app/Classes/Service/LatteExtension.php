<?php
declare(strict_types=1);
namespace App\Service;

use Hengeb\Router\Interface\CurrentUserInterface;
use Hengeb\Router\Router;

/**
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

/**
 * Latte extension
 */
class LatteExtension extends \Latte\Extension {
    private array $timezones = [];
    private \DateTimeZone $timezone;

    public function __construct(
        private Router $router,
        private CurrentUserInterface $currentUser,
    )
    {
        $this->timezones['UTC'] = new \DateTimeZone('UTC');
        $this->timezone = $this->timezones['Europe/Berlin'] = new \DateTimeZone('Europe/Berlin');
    }

	/**
	 * Returns a list of |filters.
	 * @return array<string, callable>
	 */
	public function getFilters(): array
	{
		return [
            'timezone' => $this->timezoneFilter(...),
            'format' => $this->dateformat(...),
        ];
	}

    /**
     * change timezone of \DateTimeInterface object
     * examples:
     *   {$datetime|timezone:UTC|localDate}
     *   {$datetime|timezone:Europe/Berlin|localDate}
     *   {$datetime|timezone:local|localDate}
     */
    private function timezoneFilter(string|int|\DateTime|\DateTimeImmutable|null $datetime, string|\DateTimeZone $timezone = 'local'): ?\DateTimeInterface
    {
        if ($datetime === null) {
            return null;
        }

        if (is_string($timezone)) {
            if ($timezone === 'local') {
                $timezone = $this->getTimezone();
            } else {
                $timezone = $this->timezones[$timezone] ??= new \DateTimeZone($timezone);
            }
        }

        if (!$datetime instanceof \DateTimeInterface) {
            if (is_int($datetime) || ctype_digit($datetime)) {
                $datetime = \DateTimeImmutable::createFromTimestamp((int)$datetime);
            } else {
                $datetime = new \DateTimeImmutable($datetime, $this->timezones['UTC']);
            }
        }

        return $datetime->setTimeZone($timezone);
    }

    /**
     * Format date and time
     * examples:
     *   {$datetime|format}                     // default: Y-m-d H:i:s, to timezone transformation
     *   {$datetime|format:'Y-m-d@UTC'}         // use UTC
     *   {$datetime|format:'d.m.Y@local'}       // use default timezone (self::setTimeZone($timezone))
     *   {$datetime|format:'Y-m-d'}             // do not transform timezone
     *   {$datetime|format:'H:i@Europe/Berlin'} // use specific timezone
     */
    private function dateformat(string|int|\DateTime|\DateTimeImmutable|null $datetime, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($datetime === null) {
            return null;
        }

        if (!$datetime instanceof \DateTimeInterface) {
            if (is_int($datetime) || ctype_digit($datetime)) {
                $datetime = new \DateTimeImmutable('@' . $datetime); // PHP 8.4: \DateTimeImmutable::createFromTimestamp
            } else {
                $datetime = new \DateTimeImmutable($datetime, $this->timezones['UTC']);
            }
        }

        $timezone = null; // null: no change
        if (str_contains($format, '@')) {
            [$format, $timezone] = explode('@', $format);
        }
        if ($timezone === 'local') {
            $timezone = $this->getTimezone();
        } elseif ($timezone !== null) {
            $timezone = $this->timezones[$timezone] ??= new \DateTimeZone($timezone);
        }

        if ($timezone !== null) {
            $datetime = $datetime->setTimeZone($timezone);
        }

        return $datetime->format($format);
    }

    public function getTimeZone(): \DateTimeZone
    {
        return $this->timezone;
    }

    public function setTimeZone(\DateTimeZone $timezone): void
    {
        $this->timezone = $timezone;
    }

	/**
	 * Returns a list of functions used in templates.
	 * @return array<string, callable>
	 */
	public function getFunctions(): array
	{
        return [
            'csrfToken' => fn() => $this->router->createCsrfToken(),
            'csrfTokenTag' => fn() => new \Latte\Runtime\Html('<input type="hidden" name="_csrfToken" value="' . $this->router->createCsrfToken() . '">'),
            'currentUser' => fn() => $this->currentUser,
        ];
	}

	/**
	 * Returns a list of providers.
	 * @return array<mixed>
	 */
	public function getProviders(): array
	{
		return [
            'coreParentFinder' => function (\Latte\Runtime\Template $template) {
                if (!$template->getReferenceType()) {
                    return '../layout.latte';
                }
            },
        ];
	}
}
