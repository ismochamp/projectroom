<?php
define('WP_INSTALLING',true);
$_SERVER['HTTP_HOST']='127.0.0.1:8196';
$_SERVER['REQUEST_METHOD']='GET';
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
add_filter('pre_wp_mail',function(){return false;});
if(!is_blog_installed())wp_install('Projectroom','portfolio_admin','admin@example.invalid',false,'',getenv('PORTFOLIO_ADMIN_PASSWORD'));
update_option('siteurl','http://127.0.0.1:8196');update_option('home','http://127.0.0.1:8196');
update_option('permalink_structure','/%postname%/');update_option('blog_public',0);
$result=activate_plugin('projectroom-portal/projectroom-portal.php');if(is_wp_error($result)){fwrite(STDERR,$result->get_error_message());exit(1);}
switch_theme('projectroom');flush_rewrite_rules(true);
if(!get_option('pr_seeded')){
 global $wpdb;[$p,$m,$a]=pr_tables();
 $client=wp_create_user('portfolio_client',getenv('PORTFOLIO_CLIENT_PASSWORD'),'client@example.invalid');wp_update_user(['ID'=>$client,'display_name'=>'Alex Morgan · Sample client','role'=>'subscriber']);
 $other=wp_create_user('portfolio_other',getenv('PORTFOLIO_OTHER_PASSWORD'),'other@example.invalid');wp_update_user(['ID'=>$other,'display_name'=>'Sam Taylor · Sample client','role'=>'subscriber']);
 $admin=get_user_by('login','portfolio_admin');wp_update_user(['ID'=>$admin->ID,'display_name'=>'Ismail Habib']);wp_set_current_user($admin->ID);
 $wpdb->insert($p,['name'=>'Northline website refresh','client_id'=>$client,'summary'=>'A clearer service journey, a simpler enquiry flow, and a website your team can maintain.','created_at'=>current_time('mysql',true)]);$pid=$wpdb->insert_id;
 pr_log($pid,0,'Project workspace opened','Fictional sample project used to verify the delivery and approval workflow.');
 $items=[['Discovery & page structure','approved',-4,'Page structure agreed: Home, Services, Approach and Contact. The service journey groups offerings around visitor needs. This is original sample delivery content for an independent project.'],['Enquiry flow & content review','ready_for_review',3,"The revised enquiry flow is ready for review.\n\nIncluded in this delivery:\n• A shorter form with clear labels and required fields.\n• A confirmation message that explains the next step.\n• A content outline for the three service pages.\n\nPlease review the proposed field order and the wording of the confirmation. Approve this milestone or add the changes you need below."],['Build handover & final checks','in_progress',10,'']];
 foreach($items as $item){$wpdb->insert($m,['project_id'=>$pid,'title'=>$item[0],'status'=>$item[1],'due_date'=>gmdate('Y-m-d',time()+$item[2]*86400),'brief'=>$item[3]]);$mid=$wpdb->insert_id;if($item[1]==='approved'){wp_set_current_user($client);pr_log($pid,$mid,'Client approved milestone','The page structure is clear. Ready for the next stage.');wp_set_current_user($admin->ID);}if($item[1]==='ready_for_review')pr_log($pid,$mid,'Delivery ready for review','Enquiry flow and service content are ready for your feedback.');}
 $wpdb->insert($p,['name'=>'PRIVATE — Southbank internal project','client_id'=>$other,'summary'=>'This separate fictional project verifies strict account separation.','created_at'=>current_time('mysql',true)]);$otherpid=$wpdb->insert_id;
 $wpdb->insert($m,['project_id'=>$otherpid,'title'=>'PRIVATE — access boundary check','status'=>'ready_for_review','due_date'=>gmdate('Y-m-d',time()+5*86400),'brief'=>'PRIVATE — only the assigned client and project administrators can read this delivery brief.']);
 pr_log($otherpid,$wpdb->insert_id,'Private delivery created','PRIVATE — separate project activity.');update_option('pr_seeded',1);
}
echo 'WordPress '.get_bloginfo('version').' initialized: ProjectRoom theme + private portal plugin.'.PHP_EOL;
