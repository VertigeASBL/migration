Migration =========

Ce plugin contient du code réutilisable et des exemples de migrations
de données vers SPIP.

Migration d'une table SQL -------------------------

Pour migrer une table SQL donnée vers un nouvel objet éditorial SPIP,
le plugin Fabrique fait une bonne partie du travail tout seul. Il faut
quand même à chaque fois migrer les données "à la main". Le fichier
`migration_fonctions.php` contient un exemple de migration et une
fonction pratique pour utiliser la libraire html2spip.

Migration des urls ------------------

Lorsqu'on passe un site d'un CMS quelconque vers SPIP, il est fort
problable que les urls changent également. Dans ce cas, il faudrait
faire en sorte que les vieilles urls soient redirigée vers les
nouvelles avec le code d'erreur 302. Le squelette `redirige.html`
propose un exemple de méthode pour le faire.
