-- Adminer 4.8.1 MySQL 11.5.2-MariaDB-ubu2404 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `agreements`;
CREATE TABLE `agreements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `deleted_usernames`;
CREATE TABLE `deleted_usernames` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci PACK_KEYS=0;

DROP TABLE IF EXISTS `mitglieder`;
CREATE TABLE `mitglieder` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `sichtbarkeit_email` tinyint(1) NOT NULL DEFAULT 0,
  `titel` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `vorname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `nachname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `geburtstag` date DEFAULT NULL,
  `aufnahmedatum` date DEFAULT NULL,
  `sichtbarkeit_geburtstag` tinyint(1) NOT NULL DEFAULT 1,
  `profilbild` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `profilbild_x` int(5) unsigned NOT NULL DEFAULT 0,
  `profilbild_y` int(5) unsigned NOT NULL DEFAULT 0,
  `mensa_nr` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_mensa_nr` tinyint(1) NOT NULL DEFAULT 1,
  `strasse` varchar(511) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_strasse` tinyint(1) NOT NULL DEFAULT 0,
  `adresszusatz` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_adresszusatz` tinyint(1) NOT NULL DEFAULT 0,
  `plz` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `ort` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_plz_ort` tinyint(1) NOT NULL DEFAULT 0,
  `land` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_land` tinyint(1) NOT NULL DEFAULT 0,
  `strasse2` varchar(511) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `adresszusatz2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `plz2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `ort2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `land2` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `telefon` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_telefon` tinyint(1) NOT NULL DEFAULT 0,
  `homepage` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sprachen` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `hobbys` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `interessen` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `beschaeftigung` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_beschaeftigung` tinyint(1) NOT NULL DEFAULT 1,
  `studienort` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_studienort` tinyint(1) NOT NULL DEFAULT 1,
  `studienfach` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_studienfach` tinyint(1) NOT NULL DEFAULT 1,
  `unityp` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_unityp` tinyint(1) NOT NULL DEFAULT 1,
  `schwerpunkt` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_schwerpunkt` tinyint(1) NOT NULL DEFAULT 1,
  `nebenfach` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_nebenfach` tinyint(1) NOT NULL DEFAULT 1,
  `abschluss` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_abschluss` tinyint(1) NOT NULL DEFAULT 1,
  `zweitstudium` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_zweitstudium` tinyint(1) NOT NULL DEFAULT 1,
  `hochschulaktivitaeten` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_hochschulaktivitaeten` tinyint(1) NOT NULL DEFAULT 1,
  `stipendien` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_stipendien` tinyint(1) NOT NULL DEFAULT 1,
  `auslandsaufenthalte` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_auslandsaufenthalte` tinyint(1) NOT NULL DEFAULT 1,
  `praktika` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_praktika` tinyint(1) NOT NULL DEFAULT 1,
  `beruf` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `sichtbarkeit_beruf` tinyint(1) NOT NULL DEFAULT 1,
  `auskunft_studiengang` tinyint(1) NOT NULL DEFAULT 0,
  `auskunft_stipendien` tinyint(1) NOT NULL DEFAULT 0,
  `auskunft_auslandsaufenthalte` tinyint(1) NOT NULL DEFAULT 0,
  `auskunft_praktika` tinyint(1) NOT NULL DEFAULT 0,
  `auskunft_beruf` tinyint(1) NOT NULL DEFAULT 0,
  `mentoring` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_ma` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_orte` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_vortrag` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_koord` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_graphisch` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_computer` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_texte_schreiben` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_texte_lesen` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_vermittlung` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_ansprechpartner` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_hilfe` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_sonstiges` tinyint(1) NOT NULL DEFAULT 0,
  `aufgabe_sonstiges_beschreibung` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `db_modified` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `db_modified_user_id` int(10) unsigned DEFAULT NULL,
  `kenntnisnahme_datenverarbeitung_aufnahme` datetime DEFAULT NULL,
  `kenntnisnahme_datenverarbeitung_aufnahme_text` text NOT NULL,
  `einwilligung_datenverarbeitung_aufnahme` datetime DEFAULT NULL,
  `einwilligung_datenverarbeitung_aufnahme_text` text NOT NULL,
  `resignation` datetime DEFAULT NULL,
  `membership_confirmation` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci PACK_KEYS=0;

DROP TABLE IF EXISTS `user_agreements`;
CREATE TABLE `user_agreements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(5) unsigned NOT NULL,
  `agreement_id` int(10) unsigned NOT NULL,
  `timestamp` datetime NOT NULL,
  `action` varchar(255) NOT NULL,
  `admin_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`admin_info`)),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_user_agreements_agreement` (`agreement_id`),
  CONSTRAINT `user_agreements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_agreements_ibfk_2` FOREIGN KEY (`agreement_id`) REFERENCES `agreements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2025-08-31 22:12:36
