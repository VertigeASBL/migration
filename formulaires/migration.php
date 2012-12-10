<?php
function formulaires_migration_charger_dist() {
    
    $contexte = array(
            '' => '',
    );
    return $contexte;
}

function formulaires_migration_verifier_dist() {
    
    $erreurs = array();
    if (!_request('cms')) {
            $erreurs['message_erreur'] = 'Vous devez choisir un CMS !';
    }
    return $erreurs;
}

function formulaires_migration_traiter_dist() {
 	include_spip('migration_fonctions');
	$cms = _request('cms');
	$prefix = _request('prefix');

	/* Si un prefix est passé on lance les fonctions en le passant */
	if (!empty($prefix)) {
		if ($cms == 'wordpress') importer_wordpress($prefix);
	}
	/* Sinon on lance la fonction avec le paramètre par défaut */
	else {
		if ($cms == 'wordpress') importer_wordpress();	
	}
	
    // message
    return array(
            'editable' => true,
            'message_ok' => 'Migration Terminée.',
            'redirect' => ''
    );
}
?>