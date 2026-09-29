#!/usr/bin/env python3
"""Create private local credentials and start the real WordPress application."""
import os,pathlib,secrets,subprocess,time
root=pathlib.Path(__file__).resolve().parent
env=root/'.local.env'
if not env.exists():
 with os.fdopen(os.open(env,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600),'w') as f:
  f.write('COMPOSE_PROJECT_NAME=ih-portfolio-16-'+secrets.token_hex(4)+'\n')
  for name in ['DB_PASSWORD','DB_ROOT_PASSWORD','WP_ADMIN_PASSWORD','WP_CLIENT_PASSWORD','WP_OTHER_PASSWORD']:
   f.write(name+'='+secrets.token_hex(24)+'\n')
 env.chmod(0o600)
cmd=['docker','compose','--env-file',str(env)]
subprocess.run(cmd+['up','-d'],cwd=root,check=True)
for attempt in range(45):
 result=subprocess.run(cmd+['exec','-T','wordpress','php','/tmp/portfolio-bootstrap.php'],cwd=root,capture_output=True,text=True)
 if result.returncode==0:
  print(result.stdout);break
 time.sleep(2)
else:
 print(result.stderr);raise SystemExit('WordPress initialization failed')
print('Open http://127.0.0.1:8196 | WordPress admin: /wp-admin/')
print('Local login: portfolio_admin / WP_ADMIN_PASSWORD in .local.env')
print("Client login: portfolio_client / WP_CLIENT_PASSWORD in .local.env")
