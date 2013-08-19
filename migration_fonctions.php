<?php
/**
 * Plugin Migration
 * (c) 2012 Michel Bystranowski
 * Licence GNU/GPL
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/*
	On inclut les fonctions relative au CMS
*/

include_spip('cms_fonctions');

/*
  Reconversion HTML vers typo SPIP
*/
function html2spip_translate ($texte, $id_article) {
  
  require_once(find_in_path('html2spip/misc_tools.php'));
  require_once(find_in_path('html2spip/HTMLEngine.class.php'));
  require_once(find_in_path('html2spip/HTML2SPIP3Engine.class.php'));

  $parser = new HTML2SPIP3Engine($GLOBALS['db_ok']['link'], _DIR_IMG, $id_article);
  $parser->loggingEnable();
  $identity_tags = 'script;embed;param;object';
  $parser->addIdentityTags(explode(';', $identity_tags));

  $output = $parser->translate($texte);
  return trim($output['default']);
}


/* On définit ici nos fonctions d'import, qu'on peut appeller alors 
   dans le squelette prive/squelettes/contenu/migrer.html. On fait la
   migration en allant sur la page ecrire/?exec=migrer
*/

/*
	Function d'importation d'un wordpress dans SPIP
	Seulement les tables classique de wordpress.
*/
function importer_wordpress($prefix = 'wp_') {
	
	/* On a besoin des fonctions de création de rubrique */
	include_spip('action/editer_rubrique');
	
	// On récupère les catégories de wordpress pour en faire des rubriques.
	$cat = sql_allfetsel('name', $prefix.'terms AS t INNER JOIN '.$prefix.'term_taxonomy AS tax ON t.term_id = tax.term_id', 'taxonomy = \'category\'');

	// On en fait des rubriques pour spip, à la racine.
	foreach ($cat as $key => $value) {
		$id_rubrique = rubrique_inserer(0);
		rubrique_modifier($id_rubrique, array('titre' => $value['name']));
	}

	/* On ajoute une rubrique pour les pages de Wordpress */
	$id_rubrique_wordpress_page = rubrique_inserer(0);
	rubrique_modifier($id_rubrique_wordpress_page, array('titre' => 'page Wordpress'));

	/* On a besoin des fonctions pour créer un articles */
	include_spip('action/editer_article');

	/* On récupère tout les "posts" de Wordpress */
	$post = sql_allfetsel(
				'*', 
				$prefix.'posts as p
				INNER JOIN '.$prefix.'term_relationships as tr ON object_id = p.ID
				INNER JOIN '.$prefix.'terms as t ON tr.term_taxonomy_id = t.term_id
				INNER JOIN '.$prefix.'term_taxonomy as tax ON t.term_id = tax.term_id', 

				'post_type = \'post\' AND taxonomy = '.sql_quote('category'));

	foreach ($post as $key => $value) {
		/* On vérifie qu'il n'y a pas déjà un article qui porte ce nom */
		$is_in_db = sql_getfetsel('id_article', 'spip_articles', 'titre = '.sql_quote($value['post_title']));

		if (empty($is_in_db)) {
			/* On fixe le statut de l'article */
			if ($value['post_status'] == 'publish') $statut_spip = 'publie';
			elseif ($value['post_status'] == 'draft') $statut_spip = 'prepa';

			/* On récupère l'ID de la rubrique fraîchement créer */
			$id_rubrique = sql_getfetsel('id_rubrique', 'spip_rubriques', 'titre='.sql_quote($value['name']));

			/* On ajoute l'article à SPIP */
			$id_article = article_inserer($id_rubrique);
			article_modifier($id_article, array(
				'titre' => $value['post_title'],
				'date_redac' => $value['post_date'],
				'texte' => html2spip_translate(wpautop($value['post_content']), $id_article),
				'statut' => $statut_spip,
				'date_modif' => $value['post_date']
				));
		}
	}

	/* On récupère toutes les pages de Wordpress */
	$page = sql_allfetsel(
				'*', 
				$prefix.'posts', 

				'post_type = \'page\'');
	foreach ($page as $key => $value) {
		
		/* On fixe le statut de l'article */
		if ($value['post_status'] == 'publish') $statut_spip = 'publie';
		elseif ($value['post_status'] == 'draft') $statut_spip = 'prepa';

		$id_article = article_inserer($id_rubrique_wordpress_page);
		article_modifier($id_article, array(
											'titre' => $value['post_title'],
											'date_redac' => $value['post_date'],
											'texte' => html2spip_translate(wpautop($value['post_content']), $id_article),
											'statut' => $statut_spip,
											'date_modif' => $value['post_date']
											));
	}
}

/*
*   Importer la base de donnée Badje, c'est un bon exemple pour migrer une base de donnée Excel en plugin SPIP.
*/
function importer_badje () {
    // On inclut les fonctions de badje
    include_spip('fonctions_badje');
    include_spip('action/editer_objet');
    include_spip('action/editer_liens');

    // Etape préliminaire: on créer les groupes d'activité
    objet_inserer('groupe_activite', null, array('nom_groupe' => 'Activité créatives'));
    objet_inserer('groupe_activite', null, array('nom_groupe' => 'Activité sportive'));
    objet_inserer('groupe_activite', null, array('nom_groupe' => 'Multi-activité'));
    objet_inserer('groupe_activite', null, array('nom_groupe' => 'Soutien scolaire'));

    // On va lire chaque ligne importer depuis le fichier CVS et agir en fonction de ce que l'ont trouve.

    // Avec ça on a un tableau avec tout les champs de la base importe.
    $import = sql_allfetsel('*', 'import');

    // On boucle sur l'import, on va lire chaque ligne pour savoir ce que l'on va en faire.
    foreach ($import as $key => $value) {

        // On va tester la partie organisme et l'ajouter à la base de donnée s'il faut
        if (!$id_organisme = organisme_existe($value['nom_organisme'])) {

            // On creer le tableau des champs
            $set = array(
                        'nom_organisme' => $value['nom_organisme'],
                        'adresse' => $value['adresse_organisme'],
                        'code_postal' => $value['code_postal'],
                        'localite' => $value['localite'],
                        'telephone' => $value['telephone'],
                        'gsm' => $value['gsm'],
                        'fax' => $value['fax'],
                        'email' => $value['email'],
                        'site_internet' => $value['site_internet'],
                        );
            // On test l'agrément ONE
            if ($value['ONE']) $set['one'] = 'on';

            // On ajoute l'objet et on récupère id_organisme que l'on vien de créer
            $id_organisme = objet_inserer('organisme',null, $set);
        }

        // A ce stage, on est certain d'avoir un id_organisme, de l'avoir créer s'il existe, ou simplement de l'avoir récupéré.
        // On va ajouter l'activité. Car chaque ligne est une activité.

        // On créé le tableau des donnée de l'activité
        $set = array(
                    'nom' => $value['nom_organisme'],
                    'descriptif' => $value['desc_activite'],
                    'prix' => str_replace('?', '€', $value['prix']),
                    'age_min' => $value['age_min'],
                    'age_max' => $value['age_max'],
                    'adresse' => $value['adresse_activite'],
                    'code_postal' => $value['code_postal_activite'],
                    'commune' => $value['localite_activite']
                    );

        // test pour les champ handicap
        if ($value['accueil_handicap']) $set['accueil_handicap'] = $value['accueil_handicap'];
        if ($value['accessible_mobilite']) $set['accessibilite_handicap'] = $value['accessible_mobilite'];

        // Le logement
        $logement = array();
        if ($value['logement_externat']) $logement[] = $value['logement_externat'];
        if ($value['logement_sejour']) $logement[] = $value['logement_sejour'];

        // On vire les espace en trop
        $logement = array_map('trim', $logement);

        $logement = implode(',', $logement);

        $set['logement'] = $logement;

        // La garderie
        if ($value['garderie']) $set['garderie'] = 'on';

        // le repas chaud
        if ($value['repas_chaud']) $set['repas_chaud'] = 'on';

        // Les handicap
        if ($value['accueil_handicap']) $set['accueil_handicap'] = 'on';
        if ($value['accessible_mobilite']) $set['accessibilite_handicap'] = 'on';

        // on traite les périodes
        $periode = explode('-', $value['periode']);
        // On vire les espace en trop
        $periode = array_map('trim', $periode);
        $set['periode'] = implode(',', $periode);

        $id_activite = objet_inserer('activite', null, $set);

        // Maintenant qu'on a les organismes, on va lier l'organisme a l'activité.
        $objets_source = array('organisme' => $id_organisme);
        $objets_lies = array('activite' => $id_activite);

        objet_associer($objets_source, $objets_lies);

        // Ici on publie l'activité.
        objet_instituer('activite', $id_activite, array('statut' => 'publie'));



        // On va maintenant traiter les activités.
        $groupe_activite = array(
            1 => 'activite_creative',
            2 => 'activite_sport',
            3 => 'multi_activite',
            4 => 'soutien_scolaire'
            );
        foreach ($groupe_activite as $id_groupe => $groupe) {
        
            // On traite le champ creative
            $creative = explode('-', $value[$groupe]);
            $creative = array_map('trim', $creative);

            // On boucle sur tout les types d'activité
            foreach ($creative as $type_activite) {
                if (!empty($type_activite)) {
                    // On vérifie leur existance
                    if (!$id_type_activite = type_activite_existe($type_activite) ) {
                        
                        // Le type n'existe pas, on l'ajoute
                        $set = array('type_activite' => $type_activite);
                        $id_type_activite = objet_inserer('type_activite', null, $set);
                    }

                    // On créé le lien entre l'activité et le type d'activité.
                    $objets_source = array('type_activite' => $id_type_activite);
                    $objets_lies = array('activite' => $id_activite);
                    objet_associer($objets_source, $objets_lies);

                    // On fini par créer le lien entre le type et le groupe 1
                    // On créé le lien entre l'activité et le type d'activité.
                    $objets_source = array('groupe_activite' => $id_groupe);
                    $objets_lies = array('type_activite' => $id_type_activite);
                    
                    objet_associer($objets_source, $objets_lies);
                }
            }
        }
            
    }
}

/*******************************/
/******* Exemples **************/
/*******************************/

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
        $entreprise['id_rubrique'] = $GLOBALS['id_rubrique_brabant'];
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
}

?>