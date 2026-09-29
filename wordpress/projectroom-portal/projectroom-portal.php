<?php
// SPDX-License-Identifier: MIT
// Copyright (c) 2026 Ismail Habib
/**
 * Plugin Name: ProjectRoom Portal
 * Description: Private project spaces, milestone delivery and client approvals.
 * Version: 1.0.0
 * Author: Ismail Habib
 * License: MIT
 * License URI: https://opensource.org/license/mit/
 */
if(!defined('ABSPATH'))exit;
function pr_tables(){global $wpdb;return [$wpdb->prefix.'pr_projects',$wpdb->prefix.'pr_milestones',$wpdb->prefix.'pr_activity'];}
function pr_install(){global $wpdb;[$p,$m,$a]=pr_tables();$c=$wpdb->get_charset_collate();require_once ABSPATH.'wp-admin/includes/upgrade.php';
 dbDelta("CREATE TABLE $p (id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(180) NOT NULL, client_id bigint unsigned NOT NULL, summary text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY client (client_id)) ENGINE=InnoDB $c;");
 dbDelta("CREATE TABLE $m (id bigint unsigned NOT NULL AUTO_INCREMENT, project_id bigint unsigned NOT NULL, title varchar(180) NOT NULL, due_date date NOT NULL, status varchar(30) NOT NULL, brief longtext NOT NULL, PRIMARY KEY (id), KEY project (project_id)) ENGINE=InnoDB $c;");
 dbDelta("CREATE TABLE $a (id bigint unsigned NOT NULL AUTO_INCREMENT, project_id bigint unsigned NOT NULL, milestone_id bigint unsigned NOT NULL DEFAULT 0, user_id bigint unsigned NOT NULL, event varchar(100) NOT NULL, note text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY project (project_id)) ENGINE=InnoDB $c;");
}
register_activation_hook(__FILE__,'pr_install');
function pr_fail($text,$code=400){wp_die(esc_html($text),'ProjectRoom',['response'=>$code,'back_link'=>true]);}
function pr_staff(){return current_user_can('manage_options');}
function pr_project($id){global $wpdb;[$p]=pr_tables();return $wpdb->get_row($wpdb->prepare("SELECT * FROM $p WHERE id=%d",$id));}
function pr_allowed($project){return $project&&is_user_logged_in()&&(pr_staff()||(int)$project->client_id===get_current_user_id());}
function pr_require($id){$project=pr_project($id);if(!pr_allowed($project))pr_fail('You do not have access to this project.',403);return $project;}
function pr_nonce($action){if(!isset($_POST['_wpnonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),$action))pr_fail('This form expired. Refresh the page and try again.',403);}
function pr_log($pid,$mid,$event,$note=''){global $wpdb;[,,$a]=pr_tables();return $wpdb->insert($a,['project_id'=>$pid,'milestone_id'=>$mid,'user_id'=>get_current_user_id(),'event'=>$event,'note'=>$note,'created_at'=>current_time('mysql',true)]);}
function pr_redirect($id){wp_safe_redirect(add_query_arg(['project'=>$id,'saved'=>'1'],home_url('/')));exit;}
function pr_transition(){
 if(!is_user_logged_in())pr_fail('Sign in to continue.',403);$pid=absint($_POST['project_id']??0);$project=pr_require($pid);pr_nonce('pr_transition_'.$pid);global $wpdb;[,$m]=pr_tables();$mid=absint($_POST['milestone_id']??0);$operation=sanitize_key($_POST['operation']??'');$note=sanitize_textarea_field(wp_unslash($_POST['note']??''));
 if(strlen($note)>6000)pr_fail('Keep notes below 6,000 characters.');
 $wpdb->query('START TRANSACTION');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $m WHERE id=%d AND project_id=%d FOR UPDATE",$mid,$pid));if(!$row){$wpdb->query('ROLLBACK');pr_fail('Unknown milestone.',404);}
 $next=null;$event='';
 if($operation==='deliver'){
  if(!pr_staff()){ $wpdb->query('ROLLBACK');pr_fail('Staff access required.',403); }
  if(!in_array($row->status,['in_progress','changes_requested'],true)||strlen($note)<20){$wpdb->query('ROLLBACK');pr_fail('Only an active milestone can be delivered; include a delivery brief of at least 20 characters.',409);}
  $next='ready_for_review';$event='Delivery ready for review';
 }elseif(in_array($operation,['approve','request_changes'],true)){
  if((int)$project->client_id!==get_current_user_id()){$wpdb->query('ROLLBACK');pr_fail('Only the assigned client can approve or request changes.',403);}
  if($row->status!=='ready_for_review'){$wpdb->query('ROLLBACK');pr_fail('This milestone is not awaiting review.',409);}
  if($operation==='request_changes'&&strlen($note)<5){$wpdb->query('ROLLBACK');pr_fail('Describe the changes you need.');}
  $next=$operation==='approve'?'approved':'changes_requested';$event=$operation==='approve'?'Client approved milestone':'Client requested changes';
 }else{$wpdb->query('ROLLBACK');pr_fail('Unknown operation.');}
 $data=['status'=>$next];if($operation==='deliver')$data['brief']=$note;
 $ok=$wpdb->update($m,$data,['id'=>$mid,'project_id'=>$pid]);$logged=pr_log($pid,$mid,$event,$note);if($ok===false||!$logged){$wpdb->query('ROLLBACK');pr_fail('The update could not be saved.',500);}$wpdb->query('COMMIT');pr_redirect($pid);
}
add_action('admin_post_pr_transition','pr_transition');add_action('admin_post_nopriv_pr_transition','pr_transition');
function pr_create_project(){
 if(!pr_staff())pr_fail('Staff access required.',403);pr_nonce('pr_create_project');global $wpdb;[$p]=pr_tables();$name=sanitize_text_field(wp_unslash($_POST['name']??''));$client=absint($_POST['client_id']??0);$summary=sanitize_textarea_field(wp_unslash($_POST['summary']??''));
 if(strlen($name)<3||strlen($name)>180||!get_user_by('id',$client)||strlen($summary)>6000)pr_fail('Choose an existing WordPress user and a valid project name.');
 $wpdb->query('START TRANSACTION');$ok=$wpdb->insert($p,['name'=>$name,'client_id'=>$client,'summary'=>$summary,'created_at'=>current_time('mysql',true)]);$pid=$wpdb->insert_id;$logged=$ok?pr_log($pid,0,'Project created',$summary):false;
 if(!$ok||!$logged){$wpdb->query('ROLLBACK');pr_fail('Project creation failed.',500);}$wpdb->query('COMMIT');pr_redirect($pid);
}
add_action('admin_post_pr_create_project','pr_create_project');add_action('admin_post_nopriv_pr_create_project','pr_create_project');
function pr_create_milestone(){
 if(!pr_staff())pr_fail('Staff access required.',403);$pid=absint($_POST['project_id']??0);pr_require($pid);pr_nonce('pr_create_milestone_'.$pid);$title=sanitize_text_field(wp_unslash($_POST['title']??''));$due=sanitize_text_field($_POST['due_date']??'');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$due);
 if(strlen($title)<3||strlen($title)>180||!$date||$date->format('Y-m-d')!==$due)pr_fail('Enter a valid milestone title and due date.');global $wpdb;[,$m]=pr_tables();$wpdb->query('START TRANSACTION');$ok=$wpdb->insert($m,['project_id'=>$pid,'title'=>$title,'due_date'=>$due,'status'=>'in_progress','brief'=>'']);$mid=$wpdb->insert_id;$logged=$ok?pr_log($pid,$mid,'Milestone added',$title):false;if(!$ok||!$logged){$wpdb->query('ROLLBACK');pr_fail('Milestone creation failed.',500);}$wpdb->query('COMMIT');pr_redirect($pid);
}
add_action('admin_post_pr_create_milestone','pr_create_milestone');add_action('admin_post_nopriv_pr_create_milestone','pr_create_milestone');
function pr_download(){
 $pid=absint($_GET['project']??0);$project=pr_require($pid);global $wpdb;[,$m]=pr_tables();$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $m WHERE id=%d AND project_id=%d",absint($_GET['milestone']??0),$pid));if(!$row||!$row->brief)pr_fail('No delivery brief available.',404);
 nocache_headers();header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="delivery-'.(int)$row->id.'.txt"');header('X-Content-Type-Options: nosniff');echo "PROJECTROOM — PRIVATE DELIVERY BRIEF\n\n".$project->name."\n".$row->title."\nStatus: ".$row->status."\nDue date: ".$row->due_date."\n\n".$row->brief;exit;
}
add_action('admin_post_pr_download','pr_download');add_action('admin_post_nopriv_pr_download','pr_download');
function pr_badge($status){$labels=['in_progress'=>'In progress','ready_for_review'=>'Ready for review','approved'=>'Approved','changes_requested'=>'Changes requested'];return '<span class="badge '.esc_attr($status).'">'.esc_html($labels[$status]??$status).'</span>';}
function pr_fields($action,$pid,$mid=0){echo '<input type="hidden" name="action" value="'.esc_attr($action).'"><input type="hidden" name="project_id" value="'.esc_attr($pid).'"><input type="hidden" name="milestone_id" value="'.esc_attr($mid).'">';wp_nonce_field($action.'_'.$pid);}
function pr_dashboard(){
 if(!is_user_logged_in()){ob_start();echo '<section class="signin"><span class="eyebrow">A CLEARER WAY TO WORK TOGETHER</span><h1>Your project.<br>One shared space.</h1><p>Follow the work, review deliveries and keep every decision in context.</p>';wp_login_form(['redirect'=>home_url('/'),'remember'=>false]);echo '<small>Your workspace is private. Sign in with the account provided by your project team.</small><small>Independent working project · Fictional sample content.</small></section>';return ob_get_clean();}
 global $wpdb;[$p,$m,$a]=pr_tables();$staff=pr_staff();$projects=$staff?$wpdb->get_results("SELECT * FROM $p ORDER BY id"):$wpdb->get_results($wpdb->prepare("SELECT * FROM $p WHERE client_id=%d ORDER BY id",get_current_user_id()));$pid=isset($_GET['project'])?absint($_GET['project']):($projects[0]->id??0);$project=$pid?pr_require($pid):null;ob_start();
 echo '<div class="workspace"><aside class="sidebar"><a href="'.esc_url(home_url('/')).'" class="brand"><span>▥</span> ProjectRoom</a><div class="nav-label">WORKSPACE</div><a class="side-link active" href="'.esc_url(home_url('/')).'">▦ &nbsp; Projects <span>'.count($projects).'</span></a><div class="nav-label">YOUR PROJECTS</div>';
 foreach($projects as $item)echo '<a class="project-link '.((int)$pid===(int)$item->id?'selected':'').'" href="'.esc_url(add_query_arg('project',$item->id,home_url('/'))).'">'.esc_html($item->name).'</a>';
 echo '<div class="sidebar-bottom"><div class="avatar">'.esc_html(strtoupper(substr(wp_get_current_user()->display_name,0,1))).'</div><div><strong>'.esc_html(wp_get_current_user()->display_name).'</strong><small>'.($staff?'Project manager':'Client workspace').'</small></div><a aria-label="Sign out" href="'.esc_url(wp_logout_url(home_url('/'))).'">↗</a></div></aside><section class="main"><header class="topbar"><span>Workspace <b>/</b> Projects</span><span class="private">● &nbsp; Private client space</span></header>';
 if($project){
 $milestones=$wpdb->get_results($wpdb->prepare("SELECT * FROM $m WHERE project_id=%d ORDER BY id",$pid));$approved=count(array_filter($milestones,fn($x)=>$x->status==='approved'));$review=count(array_filter($milestones,fn($x)=>$x->status==='ready_for_review'));$progress=count($milestones)?round($approved/count($milestones)*100):0;
 echo '<div class="project-header"><div><span class="eyebrow">PROJECT OVERVIEW</span><h1>'.esc_html($project->name).'</h1><p>'.esc_html($project->summary).'</p></div><div class="project-code">PR / '.str_pad($pid,3,'0',STR_PAD_LEFT).'<small>Active workspace</small></div></div>';
 if(isset($_GET['saved']))echo '<div class="saved">✓ Your update is saved. The activity history has been updated.</div>';
 echo '<div class="stats"><article><span>Milestones approved</span><strong>'.$approved.' <small>/ '.count($milestones).'</small></strong><div class="progress"><i style="width:'.$progress.'%"></i></div></article><article><span>Waiting for your review</span><strong>'.$review.'</strong><small>Open a delivery to leave your decision</small></article><article><span>Your project contact</span><strong class="contact">Ismail Habib</strong><small>Development & integrations</small></article></div><div class="content-grid"><section><div class="section-heading"><h2>Milestones & deliveries</h2><span>'.count($milestones).' milestones</span></div><div class="milestones">';
 foreach($milestones as $i=>$row){echo '<article class="milestone"><div class="milestone-number">'.($row->status==='approved'?'✓':str_pad($i+1,2,'0',STR_PAD_LEFT)).'</div><div class="milestone-body"><div class="milestone-top"><h3>'.esc_html($row->title).'</h3>'.pr_badge($row->status).'</div><p class="due">Target date · '.esc_html(gmdate('j M Y',strtotime($row->due_date))).'</p>';
 if($row->brief){echo '<details '.($row->status==='ready_for_review'?'open':'').'><summary>Delivery brief <span>⌄</span></summary><div class="brief">'.nl2br(esc_html($row->brief)).'</div><a class="download" href="'.esc_url(add_query_arg(['action'=>'pr_download','project'=>$pid,'milestone'=>$row->id],admin_url('admin-post.php'))).'">↓ Download private brief</a></details>';}
 if($row->status==='ready_for_review'&&(int)$project->client_id===get_current_user_id()){echo '<form class="review-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';pr_fields('pr_transition',$pid,$row->id);echo '<label for="note-'.(int)$row->id.'">Your feedback <span>Required when requesting changes</span></label><textarea id="note-'.(int)$row->id.'" name="note" maxlength="6000" rows="2" placeholder="Add context for your decision…"></textarea><div class="actions"><button name="operation" value="approve">✓ Approve milestone</button><button name="operation" value="request_changes" class="secondary">Request changes</button></div></form>';}
 if($staff&&in_array($row->status,['in_progress','changes_requested'],true)){echo '<details><summary>Prepare delivery <span>＋</span></summary><form class="review-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';pr_fields('pr_transition',$pid,$row->id);echo '<label>Delivery brief<textarea required minlength="20" maxlength="6000" name="note" rows="4">'.esc_textarea($row->brief).'</textarea></label><button name="operation" value="deliver">Send for client review</button></form></details>';}
 echo '</div></article>';}
 if(!$milestones)echo '<div class="empty">No milestones yet. The project manager can create the first one below.</div>';echo '</div>';
 if($staff){echo '<details class="staff-panel"><summary>＋ Add milestone</summary><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';pr_fields('pr_create_milestone',$pid);echo '<label>Milestone title<input required name="title" maxlength="180"></label><label>Target date<input required type="date" name="due_date"></label><button>Add milestone</button></form></details>';}
 echo '</section><aside class="activity"><div class="section-heading"><h2>Project activity</h2><span>UTC</span></div><div class="activity-list">';$events=$wpdb->get_results($wpdb->prepare("SELECT * FROM $a WHERE project_id=%d ORDER BY id DESC LIMIT 30",$pid));foreach($events as $event){$user=get_user_by('id',$event->user_id);echo '<article><i></i><strong>'.esc_html($event->event).'</strong><p>'.esc_html($event->note).'</p><small>'.esc_html($user?$user->display_name:'Former member').' · '.esc_html(gmdate('j M, H:i',strtotime($event->created_at))).'</small></article>';}
 if(!$events)echo '<p>No activity yet.</p>';echo '</div><div class="help-card"><span>KEEP IT IN CONTEXT</span><h3>A shared record<br>of every decision.</h3><p>Approvals and change requests are saved beside the work, so the next step stays clear.</p></div></aside></div>';
 }else echo '<div class="project-header"><h1>Your workspace is ready.</h1><p>No projects are assigned to this account yet.</p></div>';
 if($staff){echo '<details class="staff-panel"><summary>＋ Create client project</summary><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pr_create_project">';wp_nonce_field('pr_create_project');echo '<label>Project name<input required name="name" maxlength="180"></label><label>Client<select name="client_id">';foreach(get_users(['role'=>'subscriber']) as $user)echo '<option value="'.esc_attr($user->ID).'">'.esc_html($user->display_name.' — '.$user->user_login).'</option>';echo '</select></label><label>Project summary<textarea name="summary" maxlength="6000" rows="2"></textarea></label><button>Create project</button></form></details>';}
 if($staff&&$project){echo '<details class="staff-panel"><summary>Project data management</summary><p>Delete this project, all milestones and its full activity history permanently.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';pr_fields('pr_delete',$pid);echo '<button class="secondary">Permanently delete project data</button></form></details>';}echo '<footer class="workspace-footer"><span>ProjectRoom · Built by Ismail Habib</span><span>Independent working project · Fictional sample project and users</span></footer></section></div>';return ob_get_clean();
}
add_shortcode('projectroom','pr_dashboard');
add_filter('show_admin_bar',fn($show)=>false);
add_action('template_redirect',function(){if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();header('Referrer-Policy: same-origin');});
function pr_delete(){
 if(!pr_staff())pr_fail('Staff access required.',403);$pid=absint($_POST['project_id']??0);pr_require($pid);pr_nonce('pr_delete_'.$pid);global $wpdb;[$p,$m,$a]=pr_tables();$wpdb->query('START TRANSACTION');$wpdb->delete($a,['project_id'=>$pid]);$wpdb->delete($m,['project_id'=>$pid]);$wpdb->delete($p,['id'=>$pid]);$wpdb->query('COMMIT');wp_safe_redirect(home_url('/'));exit;
}
add_action('admin_post_pr_delete','pr_delete');add_action('admin_post_nopriv_pr_delete','pr_delete');
