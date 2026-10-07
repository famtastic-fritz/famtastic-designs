#!/usr/bin/env bash
# Reproduce the installed native HVAC proof using a COPY of an explicit,
# previously installed synthetic runtime. No shared database or providers.
# FAMTASTIC_HVAC_RUNTIME=/private/tmp/famtastic-acquisition-drupal.XXXXXX bash "$0"
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd -P)"
: "${FAMTASTIC_HVAC_RUNTIME:?Set FAMTASTIC_HVAC_RUNTIME to an existing disposable installed Drupal fixture root.}"
php_bin="$(command -v "${PHP_BIN:-php}")"
command -v python3 >/dev/null

python3 - "$repo_root" "$FAMTASTIC_HVAC_RUNTIME" "$php_bin" <<'PY'
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import sqlite3
import subprocess
import sys
import tempfile
from urllib.parse import quote

repo, requested_runtime = Path(sys.argv[1]).resolve(), Path(sys.argv[2])
php = sys.argv[3]
if requested_runtime.is_symlink():
    raise SystemExit('Synthetic runtime symlink refused.')
runtime = requested_runtime.resolve()
if not re.fullmatch(r'/private/tmp/famtastic-(?:acquisition|hvac)-drupal\.[A-Za-z0-9]{6}', str(runtime)):
    raise SystemExit('Only explicitly supplied disposable acquisition/HVAC fixtures are accepted.')
settings = runtime / 'backend/web/sites/default/settings.php'
database = runtime / 'backend/web/sites/default/files/.ht.sqlite'
if not settings.is_file() or not database.is_file() or settings.is_symlink() or database.is_symlink():
    raise SystemExit('Installed synthetic SQLite fixture required.')
settings_bytes = settings.read_text()
if "'synthetic-acquisition-only'" not in settings_bytes or str(database) not in settings_bytes:
    raise SystemExit('Runtime must retain its synthetic settings marker and exact local database binding.')
if (repo / 'backend/composer.lock').read_bytes() != (runtime / 'backend/composer.lock').read_bytes():
    raise SystemExit('Runtime dependency lock differs; supply a matching disposable runtime.')
if not (runtime / 'backend/vendor/drush/drush/drush.php').is_file():
    raise SystemExit('Installed Drush runtime required.')
# Check the borrowed DB read-only before copying it. Never open it for mutation.
with sqlite3.connect('file:' + quote(str(database)) + '?mode=ro', uri=True) as source_db:
    for table, column in [('famtastic_customer', 'email'), ('famtastic_prospect', 'public_email')]:
        for (email,) in source_db.execute(f'SELECT {column} FROM {table}'):
            if email and not str(email).lower().endswith('@example.test'):
                raise SystemExit('Non-fictional account/prospect in borrowed fixture; copy refused.')

temporary = Path(tempfile.mkdtemp(prefix='famtastic-hvac-drupal.', dir='/private/tmp'))
sandbox = temporary.with_name('famtastic-hvac-drupal.' + secrets.token_hex(3))
if sandbox.exists():
    raise SystemExit('Existing disposable destination refused.')
temporary.rename(sandbox)
print('Disposable copied runtime:', sandbox, flush=True)
shutil.copytree(runtime / 'backend', sandbox / 'backend', symlinks=False)
module = Path('backend/web/modules/custom/famtastic_pipeline')
shutil.rmtree(sandbox / module)
shutil.copytree(repo / module, sandbox / module)
for name in ['scripts', 'evidence', 'home', 'tmp']:
    (sandbox / name).mkdir()
shutil.copy(repo / 'scripts/hvac-lab-drupal-proof.php', sandbox / 'scripts/')
shutil.copytree(repo / 'marketing/campaigns/acquisition-199/industry-previews/hvac-coastal-current', sandbox / 'backend/private/hvac')
copied_settings = sandbox / 'backend/web/sites/default/settings.php'
copied_settings.chmod(0o600)
copied_settings.write_text(settings_bytes.replace(str(runtime), str(sandbox)))

# This reproduces the proven invocation: no inherited credentials; independent
# memory transports, PHP network/mail blockers and the installed proof's guards.
environment = {
    'PATH': os.environ['PATH'], 'HOME': str(sandbox / 'home'),
    'TMPDIR': str(sandbox / 'tmp'), 'LANG': 'C',
    'HVAC_DRUPAL_SANDBOX': str(sandbox), 'HVAC_DRUPAL_EVIDENCE': str(sandbox / 'evidence'),
    'FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT': 'memory', 'FAMTASTIC_EMAIL_TRANSPORT': 'memory',
    'FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE': str(sandbox / 'mail.jsonl'),
    'FAMTASTIC_ALLOW_REAL_OUTREACH': 'false', 'FAMTASTIC_DEPLOY_TRANSPORT': 'disabled',
    'FAMTASTIC_HOSTING_BILLING_PROVIDER': 'disabled',
}
arguments = [
    php, '-d', 'memory_limit=512M', '-d', 'allow_url_fopen=0',
    '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail',
    '-d', 'sendmail_path=/usr/bin/false', str(sandbox / 'backend/vendor/drush/drush/drush.php'),
    '--root=' + str(sandbox / 'backend/web'), '--uri=http://acquisition-drupal.example.test',
]
for command, logfile in [
    (['cache:rebuild'], 'cache-rebuild.log'),
    (['php:script', str(sandbox / 'scripts/hvac-lab-drupal-proof.php')], 'installed-proof.log'),
]:
    path = sandbox / 'evidence' / logfile
    with path.open('w') as output:
        result = subprocess.run(arguments + command, env=environment, cwd=sandbox / 'backend', stdout=output, stderr=subprocess.STDOUT)
    if result.returncode:
        print(path.read_text()[-6500:])
        raise SystemExit(result.returncode)
report = json.loads((sandbox / 'evidence/evidence.json').read_text())
if report.get('status') != 'passed' or not all(report.get('checks', {}).values()):
    raise SystemExit('Installed HVAC proof did not pass.')
print((sandbox / 'evidence/installed-proof.log').read_text())
print('Safe aggregate evidence:', sandbox / 'evidence/evidence.json')
print('Private fictional preview HTML and copied runtime retained under:', sandbox)
PY
