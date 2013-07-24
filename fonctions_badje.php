<?php 
/*
*   Cette fonction teste si un organisme est déjà présent dans la base.
*   Elle renvoie l'id_de l'oganisme s'il existe, sinon elle renvoie false
*/
function organisme_existe($organisme_nom) {
    $id_organisme = sql_getfetsel(
                                'id_organisme', 
                                'spip_badje_organismes', 
                                'nom_organisme='.sql_quote($organisme_nom));

    if (!$id_organisme) return false;
    else return $id_organisme;
}

/*
*   Cette fonction test la présence ou non d'un type activité dans la base de donnée.
*/
function type_activite_existe($type_activite) {
    $id_type_acitive = sql_getfetsel(
                            'id_type_activite', 
                            'spip_badje_type_activites', 
                            'type_activite='.sql_quote($type_activite));

    if (!$id_type_acitive) return false;
    else return $id_type_acitive;
}


?>