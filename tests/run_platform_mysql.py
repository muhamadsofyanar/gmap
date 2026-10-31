"""Optional real-MariaDB QA. Requires extracted MariaDB runtime via MARIADB_QA_ROOT.
Creates an isolated temporary server; never connects to a deployment database.
"""
import os, secrets, socket, subprocess, tempfile, time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
runtime=Path(os.environ['MARIADB_QA_ROOT']).resolve()
with tempfile.TemporaryDirectory(prefix='legaone-mysql-qa-') as tmp:
    tmp=Path(tmp); data=tmp/'data';log=tmp/'server.log'
    env=dict(os.environ,LD_LIBRARY_PATH=str(runtime/'usr/lib/x86_64-linux-gnu'))
    init=subprocess.run([str(runtime/'usr/bin/mariadb-install-db'),'--no-defaults','--basedir='+str(runtime/'usr'),'--datadir='+str(data),'--auth-root-authentication-method=normal','--skip-test-db','--user=root'],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
    if init.returncode:raise RuntimeError('MariaDB QA initialization failed')
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    with log.open('w') as out:
        server=subprocess.Popen([str(runtime/'usr/sbin/mariadbd'),'--no-defaults','--basedir='+str(runtime/'usr'),'--datadir='+str(data),'--socket=','--port='+str(port),'--bind-address=127.0.0.1','--pid-file='+str(tmp/'pid'),'--user=root','--skip-name-resolve'],env=env,stdout=out,stderr=out)
        try:
            for _ in range(200):
                if server.poll() is not None:raise RuntimeError('MariaDB QA startup failed')
                try:
                    with socket.create_connection(('127.0.0.1',port),timeout=.1):break
                except OSError:time.sleep(.03)
            cfg=dict(env,APP_ENV='development',APP_URL='https://platform.example.test',DB_HOST='127.0.0.1',DB_PORT=str(port),DB_NAME='lqa_'+secrets.token_hex(4),DB_USER='appqa',DB_PASS=secrets.token_hex(16),PLATFORM_ACCOUNTS_ENABLED='1',PLATFORM_PROVISION_USER='provqa',PLATFORM_PROVISION_PASSWORD=secrets.token_hex(16),XSENDER_ENCRYPTION_KEY='AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',PLATFORM_MYSQL_TEST='1',PLATFORM_QA_CONTEXT_FILE=str(tmp/'fixture.json'))
            result=subprocess.run(['php',str(ROOT/'tests/platform_mysql.php')],env=cfg,cwd=ROOT,text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
            print(result.stdout,end='')
            if result.returncode:raise SystemExit(result.returncode)
            http=subprocess.run(['python3',str(ROOT/'tests/test_platform_http.py')],env=cfg,cwd=ROOT,text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
            print(http.stdout,end='')
            if http.returncode:raise SystemExit(http.returncode)
        finally:
            server.terminate()
            try:server.wait(timeout=10)
            except subprocess.TimeoutExpired:server.kill();server.wait()
