<?php
/**
 * Installation des tables de commande
 *
 * @package    Vel
 * @subpackage Install
 * @author     Adrien <aimbert@solire.fr>
 * @license    CC by-nc http://creativecommons.org/licenses/by-nc/3.0/fr/
 */

require_once pathinfo(__FILE__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . 'exec-init.php';

/** Mettre script d'installation ici  **/

$query = 'CREATE TABLE IF NOT EXISTS `' . $confSql->get('table', 'commande') . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etat` int(10) unsigned NOT NULL,
  `reference` varchar(31) COLLATE utf8_unicode_ci NOT NULL DEFAULT "",
  `id_client` int(11) NOT NULL,
  `id_adresse_livraison` int(11) NOT NULL,
  `id_adresse_facturation` int(11) NOT NULL,
  `mode_livraison` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `motclient` text COLLATE utf8_unicode_ci NOT NULL,
  `total_ht` float(10,4) NOT NULL,
  `total_ttc` float(8,2) NOT NULL,
  `port` float(8,2) NOT NULL,
  `id_coupon` int(11) NOT NULL,
  `total` float(8,2) NOT NULL,
  `fidelite` tinyint(4) NOT NULL,
  `message` text COLLATE utf8_unicode_ci NOT NULL,
  `historique` text COLLATE utf8_unicode_ci NOT NULL,
  `bon_livraison` tinyint(1) NOT NULL,
  `facture` tinyint(1) NOT NULL,
  `mode_reg` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `date_reg` datetime NOT NULL,
  `adresse_ip` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$db->exec($query);

$query = 'CREATE TABLE IF NOT EXISTS `' . $confSql->get('table', 'commandeLigne') . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_commande` int(11) NOT NULL,
  `id_reference` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_ht` float NOT NULL,
  `prix_ttc` float NOT NULL,
  `taxe` float NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_commande` (`id_commande`),
  KEY `id_reference` (`id_reference`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$db->exec($query);

