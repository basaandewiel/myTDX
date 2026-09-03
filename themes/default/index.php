<?php header("Content-type: text/html; charset=utf-8"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo MyTDX\e($title); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" type="text/css" href="<?php echo MyTDX\e($themeDir); ?>style.css" media="all">
<link rel="stylesheet" type="text/css" href="<?php echo MyTDX\e($themeDir); ?>tags.css" media="all">
<link rel="shortcut icon" href="favicon.ico">
</head>
<body>

<script type="text/javascript">
window.MTTX = {
    mttUrl: <?php echo json_encode($options['mttUrl']); ?>,
    templateUrl: <?php echo json_encode($options['templateUrl']); ?>,
    options: <?php echo json_encode($options); ?>,
    externalSearch: <?php echo json_encode($externalSearch); ?>,
    openList: <?php echo json_encode($openList); ?>,
    lang: <?php echo $langData; ?>
};
</script>
<script type="text/javascript" src="<?php echo MyTDX\e($baseUrl); ?>js/api.js?v=27"></script>
<script type="text/javascript" src="<?php echo MyTDX\e($baseUrl); ?>js/ui.js?v=27"></script>
<script type="text/javascript" src="<?php echo MyTDX\e($baseUrl); ?>js/datepicker.js?v=27"></script>
<script type="text/javascript" src="<?php echo MyTDX\e($baseUrl); ?>js/app.js?v=27"></script>

<div id="wrapper">
<div id="container">
<div id="mtt_body">

<h2><?php echo MyTDX\e($title); ?></h2>

<div id="loading"><?php echo __('set_enabled'); ?></div>

<div id="bar">
 <div id="msg"><span class="msg-text"></span><div class="msg-details"></div></div>
 <div class="bar-menu">
 <span class="menu-owner" style="display:none">
   <a href="#settings" id="settings"><?php echo __('a_settings');?></a>
   <span class="bar-delim"> | </span><a href="#logout" id="bar_logout"><?php echo __('a_logout');?></a>
 </span>
 <span id="bar_auth">
  <span id="bar_public" style="display:none"><?php echo __('public_tasks'); ?> |</span>
  <a href="#login" id="bar_login" class="nodecor"><u><?php echo __('a_login');?></u> &#9660;</a>
 </span>
 </div>
</div>

<br clear="all" />

<div id="page_tasks" style="display:none">

<div id="lists">
 <ul class="mtt-tabs"></ul>
 <div class="mtt-tabs-add-button" title="<?php echo __('list_new'); ?>"><span></span></div>
 <div id="tabs_buttons">
  <div class="mtt-tabs-select-button mtt-tabs-button" title="<?php echo __('list_select'); ?>"><span></span></div>
 </div>
 <div id="list_all" class="mtt-tab mtt-tabs-alltasks mtt-tabs-hidden"><a href="#alltasks"><span><?php echo __('alltasks'); ?></span><div class="list-action"></div></a></div>
</div>

<div id="toolbar" class="mtt-htabs">

<div id="htab_search">
 <table class="mtt-searchbox"><tr><td>
  <div class="mtt-searchbox-c">
   <input type="text" name="search" value="" maxlength="250" id="search" autocomplete="off" />
   <div class="mtt-searchbox-icon mtt-icon-search"></div>
   <div id="search_close" class="mtt-searchbox-icon mtt-icon-cancelsearch"></div>
  </div>
 </td></tr></table>
</div>

<div id="htab_newtask">
 <table class="mtt-taskbox"><tr><td class="mtt-tb-cell">
  <div class="mtt-tb-c">
   <form id="newtask_form" method="post">
    <label id="task_placeholder" class="placeholding" for="task">
     <input type="text" name="task" value="" maxlength="250" id="task" autocomplete="off" />
     <span><?php echo __('htab_newtask');?></span>
    </label>
    <div id="newtask_submit" class="mtt-taskbox-icon mtt-icon-submittask" title="<?php echo __('btn_add');?>"></div>
   </form>
  </div>
 </td>
 <td><a href="#" id="newtask_adv" class="mtt-img-button" title="<?php echo __('advanced_add');?>"><span></span></a></td>
 </tr></table>
</div>

<div id="searchbar" style="display:none"><?php echo __('searching');?> <span id="searchbarkeyword"></span></div>

<div style="clear:both"></div>

</div>

<h3>
<span id="taskview" class="mtt-menu-button"><span class="btnstr"><?php echo __('tasks');?></span> (<span id="total">0</span>) <span class="arrdown"></span></span>
<span class="mtt-notes-showhide"><?php echo __('notes');?> <a href="#" id="mtt-notes-show"><?php echo __('notes_show');?></a> / <a href="#" id="mtt-notes-hide"><?php echo __('notes_hide');?></a></span>
<span id="mtt_filters"></span>
<span id="tagcloudbtn" class="mtt-menu-button"><?php echo __('tagcloud');?> <span class="arrdown2"></span></span>
</h3>

<div id="taskcontainer">
 <ol id="tasklist" class="sortable"></ol>
</div>

</div> <!-- end of page_tasks -->


<div id="page_taskedit" style="display:none">

<div><a href="#" class="mtt-back-button"><?php echo __('go_back');?></a></div>

<h3 class="mtt-inadd"><?php echo __('add_task');?></h3>
<h3 class="mtt-inedit"><?php echo __('edit_task');?>
	<span id="taskedit-tid"></span>
 <div id="taskedit-date" class="mtt-inedit">
  (<span class="date-created" title="<?php echo __('taskdate_created');?>"><span></span></span><span class="date-completed" title="<?php echo __('taskdate_completed');?>"> &mdash; <span></span></span>)
 </div>
</h3>

<form id="taskedit_form" name="edittask" method="post">
<input type="hidden" name="csrf_token" value="<?php echo MyTDX\e(MyTDX\Csrf::token()); ?>" />
<input type="hidden" name="isadd" value="0" />
<input type="hidden" name="id" value="" />
<div class="form-row form-row-short">
 <span class="h"><?php echo __('priority');?></span>
 <select name="prio">
  <option value="2">+2</option><option value="1">+1</option><option value="0" selected="selected">&plusmn;0</option><option value="-1">&minus;1</option>
 </select>
</div>
<div class="form-row form-row-short">
 <span class="h"><?php echo __('due');?> </span>
 <input name="duedate" id="duedate" value="" class="in100" title="Y-M-D, M/D/Y, D.M.Y, M/D, D.M" autocomplete="off" />
</div>
<div class="form-row-short-end"></div>
<div class="form-row"><div class="h"><?php echo __('task');?></div> <input type="text" name="task" value="" class="in500" maxlength="250" /></div>
<div class="form-row"><div class="h"><?php echo __('note');?></div> <textarea name="note" class="in500"></textarea></div>
<div class="form-row"><div class="h"><?php echo __('tags');?><span class='descr'><?php echo __('tags_descr');?></span></div>
 <table cellspacing="0" cellpadding="0" width="100%"><tr>
  <td><input type="text" name="tags" id="edittags" value="" class="in500" maxlength="250" autocomplete="off" /></td>
  <td class="alltags-cell">
   <a href="#" id="alltags_show"><?php echo __('alltags_show');?></a>
   <a href="#" id="alltags_hide" style="display:none"><?php echo __('alltags_hide');?></a></td>
 </tr></table>
</div>
<div class="form-row" id="alltags" style="display:none;"><?php echo __('alltags');?> <span class="tags-list"></span></div>
<div class="form-row form-bottom-buttons">
 <input type="submit" value="<?php echo __('save');?>" />
 <input type="button" id="mtt_edit_cancel" class="mtt-back-button" value="<?php echo __('cancel');?>" />
</div>
</form>

</div>  <!-- end of page_taskedit -->


<div id="authform" style="display:none">
<form id="login_form">
 <div class="h"><?php echo __('password');?></div>
 <div><input type="password" name="password" id="password" autocomplete="current-password" /></div>
 <div><input type="submit" value="<?php echo __('btn_login');?>" /></div>
</form>
</div>

<div id="priopopup" style="display:none">
 <span class="prio-neg prio-neg-1" data-prio="-1">&minus;1</span>
 <span class="prio-zero" data-prio="0">&plusmn;0</span>
 <span class="prio-pos prio-pos-1" data-prio="1">+1</span>
 <span class="prio-pos prio-pos-2" data-prio="2">+2</span>
</div>

<div id="taskviewcontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li id="view_tasks"><?php echo __('tasks');?> (<span id="cnt_total">0</span>)</li>
 <li id="view_past"><?php echo __('f_past');?> (<span id="cnt_past">0</span>)</li>
 <li id="view_today"><?php echo __('f_today');?> (<span id="cnt_today">0</span>)</li>
 <li id="view_soon"><?php echo __('f_soon');?> (<span id="cnt_soon">0</span>)</li>
</ul>
</div>

<div id="tagcloud" style="display:none">
 <span id="tagcloudcancel" class="mtt-img-button"><span></span></span>
 <div id="tagcloudload"></div>
 <div id="tagcloudcontent"></div>
</div>


<div id="listmenucontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li class="mtt-need-list mtt-need-real-list" id="btnRenameList"><?php echo __('list_rename');?></li>
 <li class="mtt-need-list mtt-need-real-list" id="btnDeleteList"><?php echo __('list_delete');?></li>
 <li class="mtt-need-list mtt-need-real-list" id="btnHideList"><?php echo __('list_hide');?></li>
 <li class="mtt-need-list mtt-need-real-list" id="btnClearCompleted"><?php echo __('list_clearcompleted');?></li>
 <li class="mtt-need-list mtt-need-real-list mtt-menu-indicator" id="btnExport"><span class="submenu-icon"></span><?php echo __('list_export'); ?></li>
 <li class="mtt-menu-delimiter mtt-need-real-list"></li>
 <li class="mtt-need-list mtt-need-real-list" id="btnPublish"><div class="menu-icon"></div><?php echo __('list_publish');?></li><div class="menu-icon"></div><?php echo __('list_publish');?></li>
 <li class="mtt-need-list mtt-need-real-list" id="btnRssFeed"><div class="menu-icon"></div><?php echo __('list_rssfeed');?></li>
 <li class="mtt-menu-delimiter mtt-need-real-list"></li>
 <li class="mtt-need-list mtt-need-real-list" id="sortByHand"><div class="menu-icon"></div><?php echo __('sortByHand');?> <span class="mtt-sort-direction"></span></li>
 <li class="mtt-need-list" id="sortByDateCreated"><div class="menu-icon"></div><?php echo __('sortByDateCreated');?> <span class="mtt-sort-direction"></span></li>
 <li class="mtt-need-list" id="sortByPrio"><div class="menu-icon"></div><?php echo __('sortByPriority');?> <span class="mtt-sort-direction"></span></li>
 <li class="mtt-need-list" id="sortByDueDate"><div class="menu-icon"></div><?php echo __('sortByDueDate');?> <span class="mtt-sort-direction"></span></li>
 <li class="mtt-need-list" id="sortByDateModified"><div class="menu-icon"></div><?php echo __('sortByDateModified');?> <span class="mtt-sort-direction"></span></li>
 <li class="mtt-menu-delimiter"></li>
 <li class="mtt-need-list" id="btnShowCompleted"><div class="menu-icon"></div><?php echo __('list_showcompleted');?></li>
</ul>
</div>

<div id="listexportmenucontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li id="btnExportCSV"><?php echo __('list_export_csv');?></li>
 <li id="btnExportICAL"><?php echo __('list_export_ical');?></li>
</ul>
</div>

<div id="taskcontextcontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li id="cmenu_edit"><b><?php echo __('action_edit');?></b></li>
 <li id="cmenu_note"><?php echo __('action_note');?></li>
 <li id="cmenu_prio" class="mtt-menu-indicator"><span class="submenu-icon"></span><?php echo __('action_priority');?></li>
 <li id="cmenu_move" class="mtt-menu-indicator"><span class="submenu-icon"></span><?php echo __('action_move');?></li>
 <li id="cmenu_delete"><?php echo __('action_delete');?></li>
</ul>
</div>

<div id="cmenupriocontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li id="cmenu_prio:2"><div class="menu-icon"></div>+2</li>
 <li id="cmenu_prio:1"><div class="menu-icon"></div>+1</li>
 <li id="cmenu_prio:0"><div class="menu-icon"></div>&plusmn;0</li>
 <li id="cmenu_prio:-1"><div class="menu-icon"></div>&minus;1</li>
</ul>
</div>

<div id="cmenulistscontainer" class="mtt-menu-container" style="display:none">
<ul>
</ul>
</div>

<div id="slmenucontainer" class="mtt-menu-container" style="display:none">
<ul>
 <li id="slmenu_list:-1" class="list-id--1 mtt-need-list"><div class="menu-icon"></div><a href="#alltasks"><?php echo __('alltasks'); ?></a></li>
 <li class="mtt-menu-delimiter slmenu-lists-begin mtt-need-list"></li>
</ul>
</div>

<div id="page_ajax" style="display:none"></div>

</div>
<div id="space"></div>
</div>

<div id="footer"><div id="footer_content">Powered by <strong><a href="https://github.com/MoonCactus/myTDX/">myTDX</a></strong> 2.0</div></div>

</div>
</body>
</html>