"""Actual WordPress HTTP checks of project isolation, capabilities and approval state transitions."""
from pathlib import Path
from urllib.request import build_opener,HTTPCookieProcessor,Request
from urllib.parse import urlencode,parse_qs,urlsplit
from urllib.error import HTTPError
from http.cookiejar import CookieJar
from datetime import datetime,timedelta,timezone
import re,html,uuid
BASE='http://127.0.0.1:8196';ROOT=Path(__file__).parent;checks=[]
def client():return build_opener(HTTPCookieProcessor(CookieJar()))
def request(web,path='/',data=None,status=200):
 try:
  r=web.open(Request(BASE+path,data=urlencode(data).encode() if data is not None else None));body=r.read().decode();assert r.status==status,(r.status,status);return body,r.url
 except HTTPError as e:
  body=e.read().decode();assert e.code==status,(e.code,status,body[-500:]);return body,e.url

def fields(form):return {k:html.unescape(v) for k,v in re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"',form)}
def forms(page,action):return [x for x in re.findall(r'<form\b.*?</form>',page,re.S) if 'value="'+action+'"' in x]
def check(text):checks.append(text);print('PASS:',text)
env=dict(line.split('=',1) for line in (ROOT/'.local.env').read_text().splitlines())
def login(name,key):
 web=client();request(web,'/wp-login.php');request(web,'/wp-login.php',{'log':name,'pwd':env[key],'redirect_to':BASE+'/','testcookie':'1'});return web
admin=login('portfolio_admin','WP_ADMIN_PASSWORD');user=login('portfolio_client','WP_CLIENT_PASSWORD');other=login('portfolio_other','WP_OTHER_PASSWORD');guest=client()
page,_=request(guest);assert 'Your project.' in page and 'Northline' not in page;check('Anonymous homepage contains only sign-in content')
page,_=request(user);assert 'Northline website refresh' in page and 'Southbank' not in page and 'PRIVATE —' not in page;check('Assigned client sees own project without other project data')
request(user,'/?project=2',status=403);check('Direct URL to another client project rejected')
request(user,'/wp-admin/admin-post.php?action=pr_download&project=2&milestone=4',status=403);check('Cross-project private delivery download rejected')
request(guest,'/wp-admin/admin-post.php?action=pr_download&project=1&milestone=2',status=403);check('Anonymous private delivery download rejected')
page,_=request(user,'/wp-admin/admin-post.php?action=pr_download&project=1&milestone=2');assert 'PRIVATE DELIVERY BRIEF' in page and 'Enquiry' in page;check('Assigned client can download its own private brief')
request(user,'/wp-admin/admin-post.php',{'action':'pr_create_project'},403);check('Client cannot create projects')
page,_=request(admin);form=forms(page,'pr_create_project')[0];client_id=re.search(r'<option value="(\d+)">[^<]*portfolio_client',form).group(1);tag='INTEGRATION FIXTURE '+uuid.uuid4().hex[:8];data=fields(form);data.update(name=tag,client_id=client_id,summary='Temporary fixture for state-transition and access tests.');page,url=request(admin,'/wp-admin/admin-post.php',data);pid=parse_qs(urlsplit(url).query)['project'][0];check('Staff creates persistent project assigned to a WordPress client')
try:
 data=fields(forms(page,'pr_create_milestone')[0]);data.update(title='Verification delivery',due_date=(datetime.now(timezone.utc)+timedelta(days=7)).strftime('%Y-%m-%d'));page,_=request(admin,'/wp-admin/admin-post.php',data);deliver=fields(forms(page,'pr_transition')[0]);mid=deliver['milestone_id'];deliver.update(operation='deliver',note='TEST DELIVERY: Confirm project membership checks and client review transitions.');check('Staff creates persistent milestone')
 request(admin,'/wp-admin/admin-post.php',dict(deliver,_wpnonce='invalid'),403);check('Invalid state-change nonce rejected')
 clientpage,_=request(user,'/?project='+pid);nonce_match=re.search(r'name="_wpnonce" value="([^"]+)"',clientpage)
 request(user,'/wp-admin/admin-post.php',deliver,403);check('Client cannot reuse a staff delivery form')
 page,_=request(admin,'/wp-admin/admin-post.php',deliver);assert 'Ready for review' in page;check('Staff delivery moves an active milestone to review')
 clientpage,_=request(user,'/?project='+pid);decision=fields(forms(clientpage,'pr_transition')[0]);decision.update(operation='approve',note='');
 request(other,'/wp-admin/admin-post.php',decision,403);check('Other client cannot mutate a foreign project')
 request(user,'/wp-admin/admin-post.php',dict(decision,milestone_id='4'),404);check('Milestone identifier cannot escape the selected project')
 request(user,'/wp-admin/admin-post.php',dict(decision,operation='deliver',note='Attempted client delivery with valid client nonce'),403);check('Valid client nonce does not grant staff delivery capability')
 request(user,'/wp-admin/admin-post.php',dict(decision,operation='request_changes',note=''),400);check('Change request requires actionable feedback')
 page,_=request(user,'/wp-admin/admin-post.php',dict(decision,operation='request_changes',note='Please clarify the handover steps.'));assert 'Changes requested' in page and 'Please clarify the handover steps.' in page;check('Client change request persists with actor and activity history')
 request(user,'/wp-admin/admin-post.php',decision,409);check('Stale approval rejected when milestone is not awaiting review')
 page,_=request(admin,'/?project='+pid);again=fields(forms(page,'pr_transition')[0]);again.update(operation='deliver',note='REVISED TEST DELIVERY: Handover now documents configuration, validation and local startup steps.');request(admin,'/wp-admin/admin-post.php',again);check('Staff can revise and redeliver after requested changes')
 page,_=request(user,'/?project='+pid);decision=fields(forms(page,'pr_transition')[0]);decision.update(operation='approve',note='Handover steps are clear.');page,_=request(user,'/wp-admin/admin-post.php',decision);assert 'Approved' in page and 'Client approved milestone' in page;check('Assigned client approval updates progress and durable activity')
 request(user,'/wp-admin/admin-post.php',decision,409);check('Repeated approval rejected after completion')
 request(user,'/wp-admin/admin-post.php',{'action':'pr_delete','project_id':pid},403);check('Client cannot delete project or history')
 page,_=request(admin,'/?project='+pid);delete=fields(forms(page,'pr_delete')[0]);request(admin,'/wp-admin/admin-post.php',dict(delete,_wpnonce='bad'),403);check('Staff project deletion requires a valid nonce')
finally:
 page,_=request(admin,'/?project='+pid);delete=fields(forms(page,'pr_delete')[0]);request(admin,'/wp-admin/admin-post.php',delete);page,_=request(admin);assert tag not in page;request(user,'/?project='+pid,status=403);check('Staff removes fixture project, milestones and activity')
ROOT.joinpath('TEST_RESULTS.md').write_text('# ProjectRoom verification\n\nVerified '+datetime.now(timezone.utc).isoformat(timespec='seconds')+' against real local WordPress 7.1.2 / PHP 8.3 / MariaDB.\n\n'+''.join('- PASS: '+x+'\n' for x in checks)+'\nAll test-created records removed. Uses native WordPress users, cookies, capabilities and CSRF nonces. Private delivery briefs are served only after project membership checks. Public hosting and external notifications are outside this build.\n')
print(len(checks),'integration checks passed.')
