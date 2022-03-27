<?php
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

declare(strict_types=1);
namespace MHN\Mitglieder\Domain\Controller;

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Service\Ldap;
use MHN\Mitglieder\Service\Db;
use MHN\Mitglieder\Mitglied;

class MassDeletionController {
    private ?Db $db;

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    public function run(): void
    {
        Auth::intern();
        Auth::requirePermission('rechte');

        Tpl::set('htmlTitle', 'Massenlöschung');
        Tpl::set('title', 'Massenlöschung');

        Tpl::sendHead();

        ensure($_GET['a'], ENSURE_STRING, 'main');

        switch ($_GET['a']) {
            case 'fillDatabase':
                $this->fillDatabaseWithDeletionCandidates();
                break;
            case 'clear':
                $this->clearDatabase();
                break;
            case 'sendMailsDry':
                $this->sendMails('dry');
                break;
            case 'sendMails':
                $this->sendMails('not dry');
                break;
            default:
                $this->showDeletionCandidates();
                break;
        }

        Tpl::submit();
    }

    private function fillDatabaseWithDeletionCandidates(): void
    {
        /*
            DROP TABLE IF EXISTS `deletion_candidates`;
            CREATE TABLE `deletion_candidates` (
            `id` int(10) unsigned NOT NULL,
            `username` varchar(255) NOT NULL,
            `vorname` varchar(255) NOT NULL,
            `nachname` varchar(255) NOT NULL,
            `geschlecht` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            `mail_sent` tinyint(4) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
         */

        $hasData = !is_null($this->db->query('SELECT id FROM deletion_candidates LIMIT 1')->get());
        if ($hasData) {
            die('table has data. <a href="?">back</a> <a href="?a=clear">clear</a>');
        }
        $this->db->query('INSERT INTO deletion_candidates (id, username, vorname, nachname, geschlecht, email) SELECT id, username, vorname, nachname, geschlecht, "" AS email FROM mitglieder WHERE aufnahmedatum < 20181005 AND membership_confirmation IS NULL');
        $ids = array_map('intval', $this->db->query('SELECT id FROM deletion_candidates')->getColumn());
        foreach ($ids as $id) {
            $m = Mitglied::lade($id);
            $this->db->query('UPDATE deletion_candidates SET email=:email WHERE id=:id', [
                'id'=> $m->get('id'),
                'email' => $m->get('email'),
            ]);
        }
        die('done. <a href="?">continue</a>');
    }

    private function clearDatabase(): void
    {
        $this->db->query('DELETE FROM deletion_candidates');
        die('done. <a href="?">continue</a>');
    }

    private function showDeletionCandidates(): void
    {
        $ids = array_map('intval', $this->db->query('SELECT id FROM mitglieder WHERE aufnahmedatum < 20181005 AND membership_confirmation IS NULL')->getColumn());
        $users = array_map(function ($id) {
            $m = Mitglied::lade($id);
            return [
                'id' => $m->get('id'),
                'fullName' => $m->get('fullName'),
                'ort' => $m->get('ort'),
                'email' => $m->get('email'),
                'aufnahmedatum' => $m->get('aufnahmedatum') ? $m->get('aufnahmedatum')->format('d.m.Y') : 'unbekannt',
                'lastLogin' => $m->get('last_login') ? $m->get('last_login')->format('d.m.Y') : 'vor 2014',
                'moodle' => ($m->get('last_login') && $m->get('last_login') > new \DateTime('2021-05-22')) ? 'ja' : 'nein',
            ];
        }, $ids);

        Tpl::set('htmlTitle', 'Zum Löschen vorgesehene Personen');
        Tpl::set('title', 'Zum Löschen vorgesehene Personen');

        Tpl::set('deletionCandidates', $users);
        Tpl::render('MassDeletionController/deletionCandidates');
    }

    private function sendMails(string $dryRun = 'not dry'): void
    {
        $entries = $this->db->query('SELECT * FROM deletion_candidates WHERE mail_sent = 0 AND email NOT LIKE "%.invalid"')->getAll();

        if ($dryRun === 'dry') {
            echo "<p>dry run. <a href=?a=sendMails>do it</a></p>";
        }
        $start = microtime(true);
        echo "<pre>";
        echo "will send " . count($entries) . " mails\n";
        $count = 0;
        foreach ($entries as $entry) {
            $time = microtime(true) - $start;
            if ($time > 10) {
                break;
            }
            $m = Mitglied::lade((int)$entry['id']);
            Tpl::set('vorname', $m->get('vorname'), false);
            Tpl::set('geschlecht', $m->get('geschlecht'), false);
            Tpl::set('vorname', $m->get('vorname'), false);
            Tpl::set('aufnahmedatum', $m->get('aufnahmedatum'), false);

            $body = Tpl::render('MassDeletionController/mail', false);

            if ($dryRun === 'dry') {
                echo "$body\n\n";
            } else {
                try {
                    $m->sendEmail('Dein MHN-Konto wird gelöscht', $body);
                    $this->db->query('UPDATE deletion_candidates SET mail_sent = 1 WHERE id=:id', ['id' => (int)$entry['id']]);
                } catch (\Exception $e) {
                    echo "could not send to user " . $m->get('id') . ' <' . $m->get('email') . ">\n";
                }
            }

            $count++;
        }
        echo "sent $count mails in $time seconds\n";
        echo "</pre>";
    }
}
