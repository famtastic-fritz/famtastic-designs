"""Pure HTML consumer of the agency's canonical JS credit contract."""
from functools import lru_cache
from pathlib import Path
import subprocess
import re

@lru_cache(maxsize=1)
def credit_html():
    repo = Path(__file__).resolve().parent.parent
    return subprocess.check_output(
        ["node", "--input-type=module", "-e",
         "import {creatorCreditHtml} from './scripts/creator-credit.mjs'; process.stdout.write(creatorCreditHtml());"],
        cwd=repo, text=True)

def add_creator_credit(html):
    if 'data-famtastic-creator-credit="v1"' in html:
        return html
    credit = credit_html()
    if re.search(r"</body\s*>", html, re.I):
        return re.sub(r"</body\s*>", lambda _: credit + "\n</body>", html, count=1, flags=re.I)
    return html + "\n" + credit
