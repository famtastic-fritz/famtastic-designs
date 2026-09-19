#!/usr/bin/env python3
"""Stage the local, reproducible source-performance remake; never fetch or publish.

Large private media stays in ignored artifacts. Supply the original, locally
segmented VP9-alpha presenter, and pinned local GSAP file explicitly. This is an
authored example, not an arbitrary-input video-generation service.
"""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import shutil
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[4]
SOURCE_SHA = "95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f"
GSAP_SHA = "c174bfce53a729418d57a8ad8625e7247c793a22fef8e2851e3cfa3de9cd8280"
LOGO_SHA = "ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950"
DURATION = 721 / 24


def sha(path: Path) -> str:
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def city() -> str:
    """Authored architectural scenery; deliberately deterministic, no stock API."""
    parts = ['<svg viewBox="0 0 1080 800" xmlns="http://www.w3.org/2000/svg">']
    for layer in range(3):
        step = [76, 104, 146][layer]
        for column, x in enumerate(range(-60, 1150, step)):
            width = step - 9
            height = 180 + (column * 83 + layer * 91) % (290 + layer * 40)
            y = 770 - height + layer * 23
            fill = ['#14241f', '#12201b', '#0b1712'][layer]
            parts.append(f'<rect x="{x}" y="{y}" width="{width}" height="{height}" fill="{fill}" stroke="#26382b"/>')
            for r, wy in enumerate(range(y + 16, 774, 27)):
                for c, wx in enumerate(range(x + 10, x + width - 8, 17)):
                    if (r * 7 + c * 5 + column * 3 + layer) % 7 < 3:
                        opacity = [.25, .42, .58][(r + c + column) % 3]
                        parts.append(f'<rect x="{wx}" y="{wy}" width="5" height="8" fill="#d5b470" opacity="{opacity}"/>')
    parts.append('</svg>')
    return ''.join(parts)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--original', type=Path, required=True)
    parser.add_argument('--presenter', type=Path, required=True)
    parser.add_argument('--gsap', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    for path in (args.original, args.presenter, args.gsap):
        if not path.is_file():
            parser.error(f'Missing local input: {path}')
    if sha(args.original) != SOURCE_SHA:
        parser.error('This authored remake is timed to the supplied original SHA; it is not a generic template.')
    if sha(args.gsap) != GSAP_SHA:
        parser.error('This authored remake expects the pinned GSAP 3.14.2 bytes.')
    if args.output.exists():
        parser.error('Use a fresh output directory; previous projects and renders are retained.')
    args.output.mkdir(parents=True)
    assets = args.output / 'assets'
    assets.mkdir()
    logo = REPO / 'frontend/public/brand/famtastic-designs-logo-v1.png'
    if sha(logo) != LOGO_SHA:
        parser.error('Canonical logo digest mismatch.')
    copy_sources = {
        'gsap.min.js': args.gsap,
        'famtastic-designs-logo-v1.png': logo,
        'metropolis-bold.woff2': REPO / 'frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-bold.woff2',
        'metropolis-regular.woff2': REPO / 'frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-regular.woff2',
        'kaushan-script.woff2': REPO / 'frontend/public/brand/fonts/kaushan-script-latin-v19.woff2',
    }
    provenance = {'source_movie': {'name': args.original.name, 'sha256': SOURCE_SHA},
                  'performer': 'Original 480x854 performance; local Vision mask with connected-component cleanup. No newly generated actor or recovered occluded detail.',
                  'soundtrack': 'Original AAC copied without re-encoding into a local M4A input.',
                  'gsap': {'version': '3.14.2', 'source': 'https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js'},
                  'provider_fee_usd': 0, 'published': False, 'inputs': []}
    for name, source in copy_sources.items():
        shutil.copyfile(source, assets / name)
        provenance['inputs'].append({'path': 'assets/' + name, 'sha256': sha(source), 'source_name': source.name})
    # The performer is visual-only. Remove the audio stream at the container
    # level instead of depending on a browser mute attribute during export.
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin', '-i', str(args.presenter),
                    '-map', '0:v:0', '-c:v', 'copy', '-an', str(assets / 'presenter.webm')], check=True)
    provenance['inputs'].append({'path': 'assets/presenter.webm', 'sha256': sha(assets / 'presenter.webm'),
                                'source_name': args.presenter.name, 'source_sha256': sha(args.presenter),
                                'derivation': 'Video stream copy with every audio stream removed; source retained.'})
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin', '-i', str(args.original),
                    '-map', '0:a:0', '-vn', '-c:a', 'copy', str(assets / 'soundtrack.m4a')], check=True)
    provenance['inputs'].append({'path': 'assets/soundtrack.m4a', 'sha256': sha(assets / 'soundtrack.m4a')})
    credit = subprocess.check_output(['node', '--input-type=module', '-e',
        'import {pathToFileURL} from "node:url"; const m=await import(pathToFileURL(process.argv[1])); process.stdout.write(m.creatorCreditHtml({embedded:true}));',
        str(REPO / 'scripts/creator-credit.mjs')], text=True)
    cues = json.loads((HERE / 'captions.json').read_text())['cues']
    captions = ''.join(f'<div id="caption-{i:02d}" class="caption" data-cue-start="{c["start"]}" data-cue-end="{c["end"]}"><span>{html.escape(c["text"])}</span></div>' for i, c in enumerate(cues))
    template = (HERE / 'index.template.html').read_text()
    (args.output / 'index.html').write_text(template.replace('{{CITY}}', city()).replace('{{CAPTIONS}}', captions).replace('{{CREDIT}}', credit).replace('{{MOTION}}', (HERE / 'motion.js').read_text()))
    for name in ('style.css', 'motion.js', 'index.motion.json', 'captions.json', 'SOURCE-LEDGER.json', 'BRIEF.md', 'design.md', 'STORYBOARD.md'):
        shutil.copyfile(HERE / name, args.output / name)
    (args.output / 'asset-provenance.json').write_text(json.dumps(provenance, indent=2) + '\n')
    (args.output / 'hyperframes.json').write_text(json.dumps({'media': {'autoProxy': False}}, indent=2) + '\n')
    files = sorted(p.relative_to(args.output).as_posix() for p in args.output.rglob('*') if p.is_file())
    manifest = {'schema': 'famtastic.local-hyperframes-project.v1', 'id': 'business-home-remake',
                'title': 'Give your business a home — supplied-performance remake',
                'entrypoint': 'index.html', 'width': 1080, 'height': 1920, 'fps': 24,
                'duration_seconds': DURATION, 'files': files,
                'audio_master': {'path': 'assets/soundtrack.m4a', 'mode': 'copy', 'start_seconds': 0},
                'brand_logo': {'path': 'assets/famtastic-designs-logo-v1.png', 'sha256': LOGO_SHA},
                'creator_credit_marker': 'data-famtastic-creator-credit="v1"'}
    (args.output / 'project.json').write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + '\n')
    print(json.dumps({'project': str(args.output.resolve()), 'files': len(files), 'manifest_sha256': sha(args.output / 'project.json')}, indent=2))


if __name__ == '__main__':
    main()
