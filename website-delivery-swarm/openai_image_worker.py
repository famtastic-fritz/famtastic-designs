#!/usr/bin/env python3
"""Compatibility launcher for the native macOS image-only worker.

The actual worker is Swift so it can keep the API key inside macOS Security
framework memory. This launcher has no credential-reading or networking code.
"""
from __future__ import annotations

import os
import pathlib
import shutil
import sys


def main() -> None:
    swift = shutil.which("xcrun")
    worker = pathlib.Path(__file__).with_suffix(".swift")
    if not swift or not worker.is_file():
        print("OPENAI_IMAGE_WORKER_ERROR: Native macOS image worker is unavailable", file=sys.stderr)
        raise SystemExit(2)
    os.execvp(swift, [swift, "swift", str(worker), *sys.argv[1:]])


if __name__ == "__main__":
    main()
