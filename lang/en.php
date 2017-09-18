<?php

/*
	myTinyTodo language pack
	Language: English
	Original name: English
	Author: Max Pozdeev
	Author Url: http://www.mytinytodo.net
	AppVersion: v1.4.2
	Date: 2011-03-21
*/

class Lang extends DefaultLang
{
	var $js = array
	(
		'confirmDelete' => "Are you sure you want to delete the task?",
		'confirmLeave' => "There can be unsaved data. Do you really want to leave?",
		'actionNoteSave' => "save",
		'actionNoteCancel' => "cancel",
		'error' => "Some error occurred (click for details)",
		'denied' => "Access denied",
		'invalidpass' => "Wrong password",
		'tagfilter' => "Tag:",
		'addList' => "Create new list",
		'renameList' => "Rename list",
		'deleteList' => "This will delete current list with all tasks in it.\\nAre you sure?",
		'clearCompleted' => "This will delete all completed tasks in the list.\\nAre you sure?",
		'settingsSaved' => "Settings saved. Reloading...",
	);

	var $inc = array
	(
		'htab_newtask' => "New task",
		'htab_search' => "Search",
		'btn_add' => "Add",
		'btn_search' => "Search",
		'advanced_add' => "Advanced",
		'searching' => "Searching for",
		'tasks' => "Tasks",
		'taskdate_inline_created' => "created at %s",
		'taskdate_inline_completed' => "Completed at %s",
		'taskdate_inline_duedate' => "Due %s",
		'taskdate_created' => "Created",
		'taskdate_completed' => "Completed",
		'go_back' => "&lt;&lt; Back",
		'edit_task' => "Edit Task",
		'add_task' => "New Task",
		'priority' => "Priority",
		'task' => "Task",
		'note' => "Note",
		'tags' => "Tags",
		'save' => "Save",
		'cancel' => "Cancel",
		'password' => "Password",
		'btn_login' => "Login",
		'a_login' => "Login",
		'a_logout' => "Logout",
		'public_tasks' => "Public Tasks",
		'tagcloud' => "Tags",
		'tagfilter_cancel' => "cancel filter",
		'sortByHand' => "Sort by hand",
		'sortByPriority' => "Sort by priority",
		'sortByDueDate' => "Sort by due date",
		'sortByDateCreated' => "Sort by date created",
		'sortByDateModified' => "Sort by date modified",
		'due' => "Due",
		'daysago' => "%d days ago",
		'indays' => "in %d days",
		'months_short' => array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"),
		'months_long' => array("January","February","March","April","May","June","July","August","September","October","November","December"),
		'days_min' => array("Su","Mo","Tu","We","Th","Fr","Sa"),
		'days_long' => array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"),
		'today' => "today",
		'yesterday' => "yesterday",
		'tomorrow' => "tomorrow",
		'f_past' => "Overdue",
		'f_today' => "Today and tomorrow",
		'f_soon' => "Soon",
		'action_edit' => "Edit",
		'action_note' => "Edit Note",
		'action_delete' => "Delete",
		'action_priority' => "Priority",
		'action_move' => "Move to",
		'notes' => "Notes:",
		'notes_show' => "Show",
		'notes_hide' => "Hide",
		'list_new' => "New list",
		'list_rename' => "Rename list",
		'list_delete' => "Delete list",
		'list_publish' => "Publish list",
		'list_showcompleted' => "Show completed tasks",
		'list_clearcompleted' => "Clear completed tasks",
		'list_select' => "Select list",
		'list_export' => "Export",
		'list_export_csv' => "CSV",
		'list_export_ical' => "iCalendar",
		'list_rssfeed' => "RSS Feed",
		'alltags' => "All tags:",
		'alltags_show' => "Show all",
		'alltags_hide' => "Hide all",
		'a_settings' => "Settings",
		'rss_feed' => "RSS Feed",
		'feed_title' => "%s",
		'feed_completed_tasks' => "Completed tasks",
		'feed_modified_tasks' => "Modified tasks",
		'feed_new_tasks' => "New tasks",
		'alltasks' => "All tasks",

		/* Settings */
		'set_header' => "Settings",
		'set_title' => "Title",
		'set_title_descr' => "(specify if you want to change default title)",
		'set_language' => "Language",
		'set_protection' => "Password protection",
		'set_enabled' => "Enabled",
		'set_disabled' => "Disabled",
		'set_newpass' => "New password",
		'set_newpass_descr' => "(leave blank if won't change current password)",
		'set_smartsyntax' => "Smart syntax",
		'set_smartsyntax_descr' => "(/priority/ task /tags/)",
		'set_timezone' => "Time zone",
		'set_autotag' => "Autotagging",
		'set_autotag_descr' => "(automatically adds tag of current tag filter to newly created task)",
		'set_sessions' => "Session handling mechanism",
		'set_sessions_php' => "PHP",
		'set_sessions_files' => "Files",
		'set_firstdayofweek' => "First day of week",
		'set_custom' => "Custom",
		'set_date' => "Date format",
		'set_date2' => "Short Date format",
		'set_shortdate' => "Short Date (current year)",
		'set_clock' => "Clock format",
		'set_12hour' => "12-hour",
		'set_24hour' => "24-hour",
		'set_7day' => "One week",
		'set_1month' => "One month",
		'set_1year' => "One year",
		'set_always' => "Indefinitely",
		'set_submit' => "Submit changes",
		'set_cancel' => "Cancel",
		'set_showdate' => "Show task date in list",
		'set_alientags' => "Show tags from other lists",
		'set_dbbackup' => "How long to keep backups (sqlite only)",
	);
}
