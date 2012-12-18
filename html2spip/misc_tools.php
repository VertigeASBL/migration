<?php
/*
	Ajouter par Debondt Didier
	
	Clear URL: Cette fonction va ce charger de nettoyer les URL un peux spécial pour les encoder.
	Certain CMS (Wordpress par exemple) on la mauvaise idée d'autoriser les caractère spéciaux dans les fichiers image. 
	On créer une fonction pour nettoyer les URL et faire en sorte qu'elles passent dans la moulinette de SPIP.
*/
function clear_url($url) {
	$special = array("'", 'à', 'é', 'è', '!', '&', '"', 'ç', "’", "é", "à");
	$clean = array("%27", '%C3%A0', '%C3%A9', '%C3%A8', '%21', '%26', '%22', '%C3%A7', "%E2%80%99", "e%CC%81", "a%CC%80");

	$u = str_replace($special, $clean, utf8_decode($url));

	spip_log($u, 'migration'._LOG_AVERTISSEMENT);

	return $u;
}

# Copyright (C) 2010  Jean-Jacques Puig
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

################################################################################
#
# Array functions
#
################################################################################

function array_diff_key_value($keys, $values) {
  $values_as_keys = array();

  foreach($values as $value)
    $values_as_keys[$value] = null;

  return array_diff_key($keys, $values_as_keys);
}

function array_key_value_if_notEmpty($key, $array, $default_value = null) {
  if ($array == null)
    return $default_value;

  if (array_key_exists($key, $array)
      && ($array[$key] != ''))
    return $array[$key];
  else
    return $default_value;
}

################################################################################
#
# Stored SQL Procedures functions
#   (Now useless; declarations will be removed in the future)
#
################################################################################

# spip_register_procedures($spip)
# This function is declared for compatibility.
# Will be removed in near future
function spip_register_procedures($spip) {}

# spip_unregister_procedures($spip)
# This function is declared for compatibility.
# Will be removed in near future
function spip_unregister_procedures($spip) {}

################################################################################
#
# Database related functions
#
################################################################################

/*
	Modifier par Phenix pour prendre en compte l'ID article et faire une liaison entre les deux.
*/
function spip_add_document($id_article, $url, $title) {
	/* On a besoin des fonctions de copie locale et des fonctions de gestion des documents */
	include_spip('action/editer_document');
	/* Avoir les fonctions qui permete de lier les objet entre eux, c'est pas mal non plus, pour associer les articles et les documents. */
	include_spip('action/editer_liens');

	$copier_local = charger_fonction('copier_local','action');

	$id_document = document_inserer();
	document_modifier($id_document, array(
											'titre' => $title,
											'fichier' => clear_url($url),
											'distant' => 'oui',
											'mode' => 'image'
											));

	/* On associe le document à son article */
	objet_associer(array('document' => $id_document), array('article' => $id_article));

	/* On copie le document dans SPIP */
	$copier_local($id_document);

	return $id_document;
}
?>