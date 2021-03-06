<?php
/**
 * Installation du panier
 *
 * @package    Vel
 * @subpackage Install
 * @author     Adrien <aimbert@solire.fr>
 * @license    CC by-nc http://creativecommons.org/licenses/by-nc/3.0/fr/
 */

require_once pathinfo(__FILE__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . 'exec-init.php';

/** Mettre script d'installation ici  **/

$query = 'CREATE TABLE IF NOT EXISTS `' . $confSql->get('table', 'panier') . '` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cle` char(32) NOT NULL,
  `id_region` int(11) NOT NULL DEFAULT "1",
  `hit` timestamp NOT NULL DEFAULT "0000-00-00 00:00:00" ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;';

$db->exec($query);

$query = 'CREATE TABLE IF NOT EXISTS `' . $confSql->get('table', 'panierLigne') . '` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_panier` int(11) unsigned NOT NULL,
  `id_reference` int(11) unsigned NOT NULL,
  `code` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_ht` float NOT NULL,
  `taxe` float NOT NULL,
  `prix_ttc` float NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_commande` (`id_panier`),
  KEY `id_reference` (`id_reference`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

$db->exec($query);

