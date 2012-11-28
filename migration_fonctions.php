<?php
/**
 * Plugin Migration
 * (c) 2012 Michel Bystranowski
 * Licence GNU/GPL
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/*
  Reconversion HTML vers typo SPIP
*/
function html2spip_translate ($texte) {
  
  require_once(find_in_path('lib/html2spip/misc_tools.php'));
  require_once(find_in_path('lib/html2spip/HTMLEngine.class'));
  require_once(find_in_path('lib/html2spip/HTML2SPIPEngine.class'));

  $parser = new HTML2SPIPEngine($GLOBALS['db_ok']['link'], _DIR_IMG);
  $parser->loggingEnable();
  $identity_tags = 'script;embed;param;object';
  $parser->addIdentityTags(explode(';', $identity_tags));

  $output = $parser->translate($texte);
  return trim($output['default']);
}


/*******************************/
/******* Exemples **************/
/*******************************/

/* On définit ici nos fonctions d'import, qu'on peut appeller alors 
   dans le squelette prive/squelettes/contenu/migrer.html. On fait la
   migration en allant sur la page ecrire/?exec=migrer
*/


/*
  Peuple la table spip_entreprises avec le contenu de la table 
  Guilde_Entreprises maintenue par Joomla.
*/
function importer_entreprises_depuis_joomla () {
  
  /* on récupère les données de la table de départ */
  $tableau_entreprises = sql_allfetsel('Entreprise, Phrase, '. 
    'Rue1, CP1, Ville1, Commune1, Tel1, Fax1, Gsm1, Email1, Site1,'.
    'Rue2, CP2, Ville2, Commune2, Tel2, Fax2, Gsm2, Email2, Site2,'.
    'Rue3, CP3, Ville3, Commune3, Tel3, Fax3, Gsm3, Email3, Site3,'.
    'Ouverture, Trouverez, Presentation, Infos, Type', 'Guilde_Entreprises');

  /* Dans notre cas, le champ Commune1 est en fait la région dans laquelle
     se trouve l'entreprise. Dans mon SPIP, je veux plutôt que la région
     soit donnée par des rubriques. */
  foreach ($tableau_entreprises as $i => $entreprise) {
    switch ($entreprise['Commune1']) {
      case 'Brabant Wallon':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_barbant'];
        break;
      case 'Bruxelles':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_bruxelles'];
        break;
      case 'Chimay':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_chimay'];
        break;
      case 'Charleroi':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_charleroi'];
        break;
      case 'Liège':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_liege'];
        break;
      case 'Mons':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_mons'];
        break;
      case 'Namur':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_namur'];
        break;
      case 'Sud-Luxembourg':
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_sud_luxembourg'];
        break;
      default:
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_entreprises'];
        break;
    }

    include_spip('action/editer_objet');

    /* On remplit alors la table spip que l'on a créé avec la Fabrique, en
       passant chaque champ dans html2spip.
     */
    $id_entreprise = objet_inserer('entreprise', $entreprise['id_rubrique']);
    if ($id_entreprise != 0) {
      objet_modifier('entreprise', $id_entreprise,
                     array_map('html2spip_translate', array(
                           'entreprise'   => $entreprise['Entreprise'],
                           'phrase'       => $entreprise['Phrase'],
                           'rue1'         => $entreprise['Rue1'],
                           'cp1'          => $entreprise['CP1'],
                           'ville1'       => $entreprise['Ville1'],
                           'tel1'         => $entreprise['Tel1'],
                           'fax1'         => $entreprise['Fax1'],
                           'gsm1'         => $entreprise['Gsm1'],
                           'email1'       => $entreprise['Email1'],
                           'site1'        => $entreprise['Site1'],
                           'rue3'         => $entreprise['Rue3'],
                           'cp3'          => $entreprise['CP3'],
                           'ville3'       => $entreprise['Ville3'],
                           'tel3'         => $entreprise['Tel3'],
                           'fax3'         => $entreprise['Fax3'],
                           'gsm3'         => $entreprise['Gsm3'],
                           'email3'       => $entreprise['Email3'],
                           'site3'        => $entreprise['Site3'],
                           'rue3'         => $entreprise['Rue3'],
                           'cp3'          => $entreprise['CP3'],
                           'ville3'       => $entreprise['Ville3'],
                           'tel3'         => $entreprise['Tel3'],
                           'fax3'         => $entreprise['Fax3'],
                           'gsm3'         => $entreprise['Gsm3'],
                           'email3'       => $entreprise['Email3'],
                           'site3'        => $entreprise['Site3'],
                           'ouverture'    => $entreprise['Ouverture'],
                           'trouverez'    => $entreprise['Trouverez'],
                           'presentation' => $entreprise['Presentation'],
                           'infos'        => $entreprise['Infos'],
                           'statut'       => 'publie',
                         )));
    }
  }
  return True;
}

?>