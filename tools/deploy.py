"""Nasadenie celého git stromu na qr.gobyus.com (SFTP)
   + byte-verifikácia a php -l na serveri.

   Heslo sa NEUKLADÁ do repa. Poradie hľadania:
     1. premenná prostredia QR_DEPLOY_PW
     2. súbor .deploy_env v tomto priečinku (kľúč SFTP_PASS)
     3. interaktívne getpass

   Hosting/FTP údaje pre Gobyus zatiaľ nie sú vyplnené — nastavte
   QR_DEPLOY_HOST / QR_DEPLOY_USER / QR_DEPLOY_ROOT (env alebo .deploy_env),
   inak skript skončí chybou namiesto tichého nahratia na cudzí hosting.

   Použitie:
     pip install paramiko
     python tools/deploy.py
   ------------------------------------------------------------------ """
import getpass
import hashlib
import os
import re
import subprocess
import sys

import paramiko

os.chdir(os.path.join(os.path.dirname(__file__), ".."))

HOST = os.environ["QR_DEPLOY_HOST"]
PORT = int(os.environ.get("QR_DEPLOY_PORT", "222"))
USER = os.environ["QR_DEPLOY_USER"]
ROOT = os.environ.get("QR_DEPLOY_ROOT", "/")
DEPLOY_ENV = os.environ.get("QR_DEPLOY_ENV", os.path.join(os.path.dirname(__file__), ".deploy_env"))


def password() -> str:
    if os.environ.get("QR_DEPLOY_PW"):
        return os.environ["QR_DEPLOY_PW"]
    if os.path.isfile(DEPLOY_ENV):
        for line in open(DEPLOY_ENV, encoding="utf-8"):
            line = line.strip()
            if line.startswith("SFTP_PASS="):
                return line.split("=", 1)[1].strip()
    return getpass.getpass(f"Heslo pre {USER}@{HOST}: ")


tracked = subprocess.check_output(["git", "ls-files"], text=True).splitlines()
# data/ sa nikdy nenasadzuje — na serveri je to živý obsah, ktorý si píše
# administrácia priamo cez api/save.php; nahratie z gitu by ho vrátilo na
# stav posledného commitu.
skip_pref = (".claude/", "tools/", "data/")
skip_exact = {"README.md", "TODO.md", ".gitignore"}
files = [f for f in tracked if not f.startswith(skip_pref) and f not in skip_exact]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, PORT, username=USER, password=password(), timeout=20)
sftp = client.open_sftp()


def ensure(sftp, root, rel):
    cur = root
    for p in rel.split("/")[:-1]:
        cur += "/" + p
        try:
            sftp.stat(cur)
        except IOError:
            sftp.mkdir(cur)


try:
    sftp.stat(ROOT)
except IOError:
    print(f"PRESKOCENE: priečinok {ROOT} na serveri neexistuje")
    sys.exit(1)

print(f"=== {ROOT} ===")
for f in files:
    ensure(sftp, ROOT, f)
    sftp.put(f, ROOT + "/" + f)
    try:
        sftp.chmod(ROOT + "/" + f, 0o644)
    except IOError:
        pass
print(f"  nahranych {len(files)} suborov")

diffs = []
for f in files:
    rb = sftp.open(ROOT + "/" + f, "rb").read()
    lb = open(f, "rb").read()
    if hashlib.sha256(rb).hexdigest() != hashlib.sha256(lb).hexdigest():
        diffs.append(f)
live = []
for f in ["api/config.php", "api/dbconfig.php", "api/mailconfig.php"]:
    try:
        sftp.stat(ROOT + "/" + f)
        live.append(f)
    except IOError:
        pass
print("  VERIFY:", "VSETKO SEDI" if not diffs else f"ROZDIELY {diffs}")
print("  live-only pritomne:", live)

try:
    _, out, _ = client.exec_command(
        f"cd {ROOT} && for f in api/*.php og.php; do php -l $f 2>/dev/null | grep -v 'No syntax'; done"
    )
    errors = out.read().decode("utf-8", "replace").strip()
    print("  php -l:", errors or "bez chyb")
except Exception:
    print("  php -l: preskocene (ucet je len SFTP, bez shellu)")
html = sftp.open(ROOT + "/index.html", "rb").read().decode("utf-8", "replace")
m = re.search(r"\?v=([0-9a-z]+)", html)
print("  verzia na serveri:", m.group(1) if m else "?")

sftp.close()
client.close()
