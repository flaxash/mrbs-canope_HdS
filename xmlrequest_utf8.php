<?php

//////////////////////////////////////////////////////
//      Création du fichier xml               //
//////////////////////////////////////////////////////

require_once "functions.inc";

$database="cd922";
$connect = mysql_connect("localhost","cd922","jk6xkw5k");
mysql_select_db($database, $connect);

//récupère la date de début et celle de fin sous la forme d'un timestamp (timestamps dans la base de données)
$day   = date("d");
$month = date("m");
$year  = date("Y");

$timestamp_matin = mktime(3, 0, 0, $month, $day, $year);
$timestamp_soir = mktime(22,0,0, $month, $day, $year);


//Ensuite on envoie une requete SQL pour sélectionner une table dont on veux les informations
$sql = "SELECT R.id AS room_id, start_time, end_time, name, repeat_id,
				 E.id AS entry_id, type,
				 E.description AS entry_description, status,
				 E.create_by AS entry_create_by
			FROM mrbs_entry E, mrbs_room R
		   WHERE E.room_id = R.id
			 AND R.disabled = 0
			 AND start_time <= $timestamp_soir AND end_time > $timestamp_matin
		ORDER BY start_time";   // necessary so that multiple bookings appear in the right order

//On fait la requête et nous recevons les informations
$result_table= mysql_query($sql, $connect) or die(mysql_error());

//Là on transforme les informations recu sous forme de tableau
$num_table = mysql_num_rows($result_table);


if ($num_table != 0) {
		//On créer un fichier texte que l'on nomme et dont on indique l'emplacement
		//$file= fopen("nomdefichier.xml", "w");

		//là on créer le texte que nous mettrons dedans, tout d'abord l'en-tête
   $_xml ="<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
	$_xml .="<reservations>\r\n";
		//Cette fois ci on fait une boucle while permettant de prendre les informations ligne par ligne
   while ($row = mysql_fetch_array($result_table)) {
	  $debut = date("H:i",$row[start_time]);
	  
	  $fin = date("H:i",$row[end_time]);
      $nom = utf8_encode($row[name]);
	  
	  $_xml .="<reservation nom=\"$nom\" salle=\"$row[room_id]\" debut=\"$debut\" fin=\"$fin\" />\r\n";
   } 
   $_xml .="</reservations>\r\n";

		//Maintenant que nous avons créé le contenu du  XML, on retourne sa valeur
	   
		echo( $_xml);
} 

//Message d'erreur si il n'y a pas d'informations dans la BDD
else {
   echo "No Records found";
}

?>