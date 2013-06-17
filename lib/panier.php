<?php
/**
 * Module de gestion de panier.
 * Mise en panier, passage de commande.
 *
 * @package    Vel
 * @subpackage Library
 * @author     Adrien <aimbert@solire.fr>
 * @license    Solire http://www.solire.fr/
 */

namespace Vel\Lib;

/**
 * Fonctionnalités de base d'un panier
 *
 * @package    Vel
 * @subpackage Library
 * @author     Adrien <aimbert@solire.fr>
 * @license    Solire http://www.solire.fr/
 */

class Panier
{
    /**
     * Chemin vers le fichier de configuration
     */
    const CONFIG_PATH = 'config/panier.ini';

    /**
     * Identifiant du panier
     *
     * @var int
     */
    protected $id = null;

    /**
     * Connection à la base de données.
     * @var myPDO
     */
    protected $db = null;

    /**
     * Configuration du module de panier
     *
     * @var \Slrfw\Config
     */
    public $config = null;

    /**
     *
     * @var self
     */
    private static $single = false;


    /**
     * utiliser init() ou run() pour instancier panier
     *
     * @param int|null $id Identifiant du panier
     *
     * @return void
     * @uses \Slrfw\Config pour charger la configuration
     * @uses \Slrfw\Registry
     */
    public function __construct()
    {
        if (self::$single !== false) {
            return;
        }
        self::$single = true;

        $path = \Slrfw\FrontController::search(self::CONFIG_PATH, false);
        $this->config = new \Slrfw\Config($path);

        $this->db = \Slrfw\Registry::get('db');
        $cookieName = $this->config->get('session', 'cookieName');
        if (isset($_COOKIE[$cookieName])) {
            $query = 'SELECT id FROM ' . $this->config->get('table', 'panier')
                   . ' WHERE cle = ' . $this->db->quote($_COOKIE[$cookieName]) . ';';
            $panier = $this->db->query($query)->fetch();
            if (empty($panier)) {
                $this->create();
                return null;
            }
            $this->id = $panier['id'];
        } else {
            $this->create();
        }
    }

    /**
     * Création d'un nouveau panier
     *
     * @return void
     */
    protected function create()
    {
        $cle = $this->genereCle();
        $cookieName = $this->config->get('session', 'cookieName');
        $expire = time() +
            (int) $this->config->get('session', 'cookieDuration');

        setcookie($cookieName, $cle, $expire, '/');

        $query = 'INSERT INTO ' . $this->config->get('table', 'panier')
               . ' (cle) VALUES ( ' . $this->db->quote($cle) . ' );';
        $this->db->exec($query);
        $this->id = $this->db->lastInsertId();
    }

    /**
     * Modifie la date de dernier edition du panier
     *
     * @return void
     */
    protected function hit()
    {
        $query = 'UPDATE ' . $this->config->get('table', 'panier') . ' '
               . 'SET hit = NOW() '
               . 'WHERE id = ' . $this->id;
        $this->db->exec($query);
    }

    /**
     * Renvois l'identifiant du panier
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Ajoute un produit au panier
     *
     * @param int $idRef Identifiant de la référence à ajouter
     * @param int $qte   Quantité de la référence
     *
     * @return void
     * @throws \Slrfw\Exception\Lib $qte < 0 alors que le produit n'est pas dans le
     * panier
     */
    public function ajoute($idRef, $qte)
    {
        $tableLigne = $this->config->get('table', 'panierLigne');

        $this->hit();

        $query = 'SELECT quantite '
               . 'FROM ' . $tableLigne . ' tl '
               . 'WHERE id_panier = ' . $this->id . ' '
               . 'AND id_reference = ' . $idRef;
        $quantite = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);
        if ($quantite > 0) {
            /* = Produit déjà présent
              `------------------------------------------------- */
            if (($quantite + $qte) <= 0) {
                $this->supprime($idRef);
                return true;
            } else {
                $query = 'UPDATE ' . $tableLigne
                       . ' SET quantite = quantite + ' . $qte . ' '
                       . 'WHERE id_panier = ' . $this->id
                       . ' AND id_reference = ' . $idRef;
            }
        } else {
            if ($qte < 0) {
                throw new \Slrfw\Exception\Lib($this->config->get('erreur', 'ajoutQte'));
            }
            /* = On ajoute le produit dans le panier
              `------------------------------------------------- */
            $info = array();

            /* = Recherche des champs à remplir directement dans la table panier
              ------------------------------- */
            $query = 'DESC ' . $tableLigne;
            $archiLigne = $this->db->query($query)->fetchAll(\PDO::FETCH_COLUMN, 0);

            $query = 'DESC ' . $this->config->get('table', 'reference');
            $archiRef = $this->db->query($query)->fetchAll(\PDO::FETCH_COLUMN, 0);

            $ignoreList = array('id');
            foreach ($archiLigne as $column) {
                if (in_array($column, $ignoreList)) {
                    continue;
                }

                if (in_array($column, $archiRef)) {
                    $info[] = $column;
                }
            }
            unset($query, $ignoreList, $archiLigne, $archiRef, $column);


            /* = Récupération de la référence
              ------------------------------- */
            $query = 'SELECT ' . implode(', ', $info) . ' '
                   . 'FROM ' . $this->config->get('table', 'reference') . ' '
                   . 'WHERE id_gab_page = ' . $idRef;
            try {
                $ref = $this->db->query($query)->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $exc) {
                throw new \Slrfw\Exception\Lib(
                    $this->config->get('erreur', 'ajoutRef'), 1, $exc
                );
            }
            if (empty($ref)) {
                throw new \Slrfw\Exception\Lib($this->config->get('erreur', 'ajoutRefNo'));
            }

            /* = Création des données à insérer
              `------------------------------------------------- */
            $data = array(
                'quantite'      => $qte,
                'id_panier'     => $this->id,
                'id_reference'  => $idRef,
            );

            foreach ($ref as $key => $value) {
                $data[$key] = $value;
            }

            /* = Formatage de la requête
              `------------------------------------------------- */
            $set = array();
            foreach ($data as $key => $value) {
                $set[] = '`' . $key . '` = ' . $this->db->quote($value);
            }

            $query = 'INSERT INTO ' . $tableLigne . ' SET '
                   . ' ' . implode(', ', $set) . ' ';
        }

        try {
            $this->db->exec($query);
        } catch (\PDOException $exc) {
            unset($exc);
            throw new \Slrfw\Exception\Lib($this->config->get('erreur', 'ajoutSql'));
        }
    }

    /**
     * Supprime une référence du panier
     *
     * @param int $idRef identifiant de la référence
     *
     * @return void
     */
    public function supprime($idRef)
    {
        $this->hit();
        $tableLigne = $this->config->get('table', 'panierLigne');

        $query = 'DELETE FROM ' . $tableLigne . ' '
               . 'WHERE id_reference = ' . $idRef
               . ' AND id_panier = ' . $this->id;
        $this->db->exec($query);

        /* = supprimer le panier si celui-ci est vide
          `------------------------------------------------- */
        $query = 'SELECT COUNT(*) FROM ' . $tableLigne . ' '
               . 'WHERE id_panier = ' . $this->id;
        $count = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);

        if (!$count) {
            $tablePanier = $this->config->get('table', 'panier');
            $query = 'DELETE FROM ' . $tablePanier . ' '
                   . 'WHERE id = ' . $this->id;
            $this->db->exec($query);

            $cookieName = $this->config->get('session', 'cookieName');
            setcookie($cookieName, '', time() - 3600);

        }
    }

    /**
     * Calcul et renvois les ports du panier
     *
     * @return float
     * @todo finaliser la function
     */
    public function getPort()
    {
        $port = 0;

        if ($this->config->get('methode', 'port') == 'ini') {
            $port = $this->config->get('port', 'montant');

            if ($this->config->get('port', 'franco')) {
                $prix = $this->getPrix();
                $franco = $this->config->get('port', 'franco');
                if ((float)$prix >= (float)$franco) {
                    $port = 0;
                }
            }
            return $port;
        }

        return $port;
    }


    /**
     * Renvois le prix total du panier
     * <br/>Soit le prix et les frais de port
     *
     * @return flaot
     */
    public function getTotal()
    {
        $prix = $this->getPrix();
        $prix += $this->getPort();

        return $prix;
    }

    /**
     * Renvois le montant hors taxes du panier
     *
     * @return float
     */
    public function getHT()
    {
        $methode = $this->config->get('methode', 'prixHT');
        $query = 'SELECT SUM((' . $methode . ') * quantite) '
               . 'FROM ' . $this->config->get('table', 'panierLigne') . ' '
               . 'WHERE id_panier = ' . $this->id;
        $prix = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);

        return $prix;
    }

    /**
     * Renvois le montant total du panier (sans les frais de port)
     *
     * @param int $idRef Identifiant de référence,
     *
     * @return float
     */
    public function getPrix($idRef = null)
    {
        $methode = $this->config->get('methode', 'prixTTC');

        $query = 'SELECT SUM((' . $methode . ') * quantite) '
               . 'FROM ' . $this->config->get('table', 'panierLigne') . ' '
               . 'WHERE id_panier = ' . $this->id;

        if (!empty($idRef)) {
            $query .= ' AND id_reference = ' . $idRef;
        }
        $prix = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);

        return $prix;
    }

    /**
     * Renvois le nombre de produits dans le panier
     * Si $idRef est précisé, c'est le nombre de cette référence qui est renvoyé
     *  et non le nombre de produit total du panier
     *
     * @param int $idRef Identifiant de référence,
     *
     * @return int
     */
    public function getNombre($idRef = null)
    {
        if (empty($idRef)) {
            $query = 'SELECT SUM(quantite) '
                   . 'FROM ' . $this->config->get('table', 'panierLigne') . ' '
                   . 'WHERE id_panier = ' . $this->id;
        } else {
            $query = 'SELECT SUM(quantite) '
                   . 'FROM ' . $this->config->get('table', 'panierLigne') . ' '
                   . 'WHERE id_panier = ' . $this->id . ' '
                   . ' AND id_reference = ' . $idRef;
        }
        $count = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);

        if (empty($count)) {
            $count = 0;
        }

        return $count;
    }

    /**
     * Renvois toutes les informations du panier
     *
     * @return array
     */
    public function getInfo()
    {
        $query = 'SELECT * '
               . 'FROM ' . $this->config->get('table', 'panierLigne') . ' '
               . 'WHERE id_panier = ' . $this->id;
        $lignes = $this->db->query($query)->fetchAll();
        $lignes['total'] = $this->getTotal();
        $lignes['prix'] = $this->getPrix();
        $lignes['prixHT'] = $this->getHT();
        $lignes['port'] = $this->getPort();

        return $lignes;
    }

    /**
     * Supprime toutes les informations du panier
     *
     * @return void
     */
    public function vide()
    {
        $query = 'DELETE FROM ' . $this->config->get('table', 'panierLigne') . ' '
               . 'WHERE id_panier = ' . $this->id;
        $this->db->exec($query);
        $query = 'DELETE FROM ' . $this->config->get('table', 'panier') . ' '
               . 'WHERE id = ' . $this->id;
        $this->db->exec($query);

        $this->id = null;
    }

    /**
     * Génère une clé pour identifier le panier
     *
     * @return string clé unique pour identifier un panier
     */
    protected function genereCle()
    {
        $use = false;
        do {
            $cle = \Slrfw\Tools::random(32);
            $query = 'SELECT COUNT(*) '
                   . 'FROM ' . $this->config->get('table', 'panier') . ' '
                   . 'WHERE cle = ' . $this->db->quote($cle);
            $use = $this->db->query($query)->fetch(\PDO::FETCH_COLUMN);
        } while ($use);

        return $cle;
    }
}

