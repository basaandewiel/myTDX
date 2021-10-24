<?php

/*
	myTinyTodo language pack
	Language: German
	Original name: Deutsch
	Author: Pascal / Rene Wagner
	Author Url: http://www.pascal90.de / https://github.com/guzzisti
	Date: 2017/10/22
*/

class Lang extends DefaultLang
{
	var $js = array
	(
		'confirmDelete' => 'Willst Du die Aufgabe wirklich löschen?',
		'confirmLeave' => 'Einige Daten wurden noch nicht gespeichert. Willst du die Seite wirklich verlassen?',
		'actionNoteSave' => 'speichern',
		'actionNoteCancel' => 'abbrechen',
		'error' => 'Fehler aufgetreten (für Details klicken)',
		'denied' => 'Zugriff verweigert',
		'invalidpass' => 'Falsches Passwort',
		'tagfilter' => 'Schlagwort:',
		'addList' => 'Neue Liste anlegen',
		'renameList' => 'Liste umbenennen',
		'deleteList' => 'Die Liste wird mit allen Aufgaben gelöscht.\\nBist Du sicher?',
		'clearCompleted' => 'Alle abgeschlossenen Aufgaben dieser Liste werden gelöscht.\\nBist Du sicher?',
		'settingsSaved' => 'Einstellungen gespeichert. Aktualisierung...',
	);

	var $inc = array
	(
		'htab_newtask' => 'Neue Aufgabe',
		'htab_search' => 'Suche',
		'btn_add' => 'Hinzufügen',
		'btn_search' => 'Suche',
		'advanced_add' => 'Erweitert',
		'searching' => 'Suche nach',
		'tasks' => 'Aufgabe',
		'taskdate_inline_created' => 'hinzugefügt am %s',
		'taskdate_inline_completed' => 'Erledigt am %s',
		'taskdate_inline_duedate' => 'Fällig am %s',
		'taskdate_created' => 'Erstellt',
		'taskdate_completed' => 'Erledigt',
		'go_back' => '&lt;&lt; Zurück',
		'edit_task' => 'Aufgabe bearbeiten',
		'add_task' => 'Neue Aufgabe',
		'priority' => 'Priorität',
		'task' => 'Aufgabe',
		'note' => 'Notiz',
		'tags' => 'Schlagwörter',
		'tags_descr' => "(prefixes: =state, @user, ~fix, !highlight)",
		'save' => 'Speichern',
		'cancel' => 'Abbrechen',
		'password' => 'Passwort',
		'btn_login' => 'Login',
		'a_login' => 'Login',
		'a_logout' => 'Logout',
		'public_tasks' => 'Öffentliche Aufgabe',
		'tagcloud' => 'Tags',
		'tagfilter_cancel' => 'Filter aufheben',
		'sortByHand' => 'Manuell sortieren',
		'sortByPriority' => 'Nach Priorität sortieren',
		'sortByDueDate' => 'Nach Fälligkeitsdatum sortieren',
		'sortByDateCreated' => 'Nach Erstelldatum sortieren',
		'sortByDateModified' => 'Nach Änderungsdatum sortieren',
		'due' => 'Fällig',
		'daysago' => 'vor %d Tagen',
		'indays' => 'in %d Tagen',
		'months_short' => array('Jan','Feb','Mrz','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'),
		'months_long' => array('Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'),
		'days_min' => array('So','Mo','Di','Mi','Do','Fr','Sa'),
		'days_long' => array('Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'),
		'today' => 'heute',
		'yesterday' => 'gestern',
		'tomorrow' => 'morgen',
		'f_past' => 'Überfällig',
		'f_today' => 'Heute und morgen',
		'f_soon' => 'Bald',
		'action_edit' => 'Bearbeiten',
		'action_note' => 'Notiz bearbeiten',
		'action_delete' => 'Löschen',
		'action_priority' => 'Priorität',
		'action_move' => 'Verschieben nach',
		'notes' => 'Notizen:',
		'notes_show' => 'Anzeigen',
		'notes_hide' => 'Verbergen',
		'list_new' => 'Neue Liste',
		'list_rename' => 'Liste umbenennen',
		'list_delete' => 'Liste löschen',
		'list_publish' => 'Liste veröffentlichen',
		'list_hide' => 'Liste ausblenden',
		'list_showcompleted' => 'Abgeschlossene Aufgaben anzeigen',
		'list_clearcompleted' => 'Abgeschlossene Aufgaben löschen',
		'list_select' => 'Liste auswählen',
		'list_export' => 'Export',
		'list_export_csv' => 'CSV',
		'list_export_ical' => 'iCalendar',
		'list_rssfeed' => 'RSS Feed',
		'alltags' => 'Alle Schlagwörter:',
		'alltags_show' => 'Alle anzeigen',
		'alltags_hide' => 'Alle verbergen',
		'a_settings' => 'Einstellungen',
		'rss_feed' => 'RSS Feed',
		'feed_title' => '%s',
		'feed_completed_tasks' => 'Abgeschlossene Aufgabe',
		'feed_modified_tasks' => 'Geänderte Aufgaben',
		'feed_new_tasks' => 'Neue Aufgaben',
		'alltasks' => 'Alle Aufgaben',
		
		/* Settings */
		'set_header' => 'Einstellungen',
		'set_title' => 'Titel',
		'set_title_descr' => '(angeben, um Standardtitel zu ändern)',
		'set_language' => 'Sprache',
		'set_protection' => 'Passwortschutz',
		'set_enabled' => 'Aktiviert',
		'set_disabled' => 'Deaktiviert',
		'set_newpass' => 'Neues Passwort',
		'set_newpass_descr' => '(leer lassen, um aktuelles Passwort nicht zu ändern)',
		'set_smartsyntax' => 'Smartsyntax',
		'set_smartsyntax_descr' => '(/Priorität/ Aufgabe /Schlagwörter/)',
		'set_timezone' => 'Zeitzone',
		'set_autotag' => 'Automatische Schlagwörter',
		'set_autotag_descr' => '(fügt Schlagwort des aktuellen Filters automatisch der neu erstellten Aufgabe hinzu)',
		'set_sessions' => 'Sessionhandling-Mechanismus',
		'set_sessions_php' => 'PHP',
		'set_sessions_files' => 'Dateien',
		'set_firstdayofweek' => 'Erster Tag der Woche',
		'set_custom' => 'benutzerdefiniert',
		'set_date' => 'Datumsformat',
		'set_date2' => 'Kurzes Datumsformat',
		'set_shortdate' => 'Kurzes Datumsformat (aktuelles Jahr)',
		'set_clock' => 'Zeitformat',
		'set_12hour' => '12 Stunden',
		'set_24hour' => '24 Stunden',
		'set_7day' => '1 Woche',
		'set_1month' => '1 Monat',
		'set_1year' => '1 Jahr',
		'set_always' => 'Unbegrenzt',
		'set_submit' => 'Änderungen speichern',
		'set_cancel' => 'Abbrechen',
		'set_showdate' => 'Aufgabendatum in Liste anzeigen',
		'set_alientags' => 'Schlagwörter von anderen Listen anzeigen',
		'set_dbbackup' => 'Backups aufbewahren für',
		'set_dbbackup_descr' => 'Funktioniert nur mit SQLite!',		
	);
}
