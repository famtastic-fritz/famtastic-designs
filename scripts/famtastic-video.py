#!/usr/bin/env python3
"""Repository adapter for the portable video studio; no publishing authority."""
import sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'marketing/engine/video_studio'))
from fam_video.cli import main
if __name__=='__main__':
    raise SystemExit(main(repo_root=ROOT,default_brand=ROOT/'marketing/brands/famtastic/video-studio/brand.json'))
