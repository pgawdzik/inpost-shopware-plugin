#!/usr/bin/env python3
"""Build a deterministic Shopware installation archive using only Python's stdlib."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import zipfile
from pathlib import Path


PLUGIN_NAME = "CluewareInpostShipping"
ROOT_FILES = ("composer.json", "README.md", "CHANGELOG.md", "LICENSE")
DIRECTORIES = ("src", "docs", "scripts")
REQUIRED_FILES = (
    "src/CluewareInpostShipping.php",
    "src/Resources/config/services.xml",
    "src/Resources/config/config.xml",
    "src/Resources/config/routes.xml",
    "src/Resources/app/storefront/dist/storefront/js/clueware-inpost-shipping/clueware-inpost-shipping.js",
    "src/Resources/public/administration/.vite/entrypoints.json",
    "src/Resources/public/administration/.vite/manifest.json",
)
EXCLUDED_PARTS = {".git", "node_modules", "vendor", "__pycache__"}
TEXT_SUFFIXES = {".php", ".js", ".css", ".scss", ".json", ".xml", ".twig", ".svg", ".md", ".py"}


def archive_bytes(path: Path) -> bytes:
    data = path.read_bytes()
    if path.suffix.lower() in TEXT_SUFFIXES or path.name == "LICENSE":
        return data.replace(b"\r\n", b"\n")
    return data


def build(root: Path, output: Path) -> Path:
    composer = json.loads((root / "composer.json").read_text(encoding="utf-8-sig"))
    version = composer["version"]
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:[.-][A-Za-z0-9.-]+)?", version):
        raise ValueError(f"Unexpected plugin version: {version!r}")
    if composer["extra"]["shopware-plugin-class"] != "Clueware\\InpostShipping\\CluewareInpostShipping":
        raise ValueError("The plugin class does not match the release directory.")

    for required in (*ROOT_FILES, *REQUIRED_FILES):
        if not (root / required).is_file():
            raise FileNotFoundError(f"Required release file is missing: {required}")

    selected = [root / name for name in ROOT_FILES]
    for directory in DIRECTORIES:
        selected.extend(
            path
            for path in (root / directory).rglob("*")
            if path.is_file()
            and not EXCLUDED_PARTS.intersection(path.relative_to(root).parts)
            and path.name not in {".DS_Store", "Thumbs.db"}
            and path.suffix not in {".pyc", ".log"}
        )
    selected = sorted(set(selected), key=lambda path: path.relative_to(root).as_posix())

    output.mkdir(parents=True, exist_ok=True)
    archive_path = output / f"{PLUGIN_NAME}-{version}.zip"
    with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in selected:
            name = f"{PLUGIN_NAME}/{path.relative_to(root).as_posix()}"
            entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(entry, archive_bytes(path), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

    with zipfile.ZipFile(archive_path) as archive:
        bad_member = archive.testzip()
        if bad_member is not None:
            raise ValueError(f"Archive integrity check failed: {bad_member}")
        expected = {f"{PLUGIN_NAME}/{path.relative_to(root).as_posix()}" for path in selected}
        if set(archive.namelist()) != expected:
            raise ValueError("Archive contents differ from the selected source files.")
        for path in selected:
            name = f"{PLUGIN_NAME}/{path.relative_to(root).as_posix()}"
            if archive.read(name) != archive_bytes(path):
                raise ValueError(f"Archive content differs from source: {name}")

    digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    checksum_path = archive_path.with_suffix(".zip.sha256")
    checksum_path.write_text(f"{digest}  {archive_path.name}\n", encoding="utf-8")
    print(json.dumps({"archive": str(archive_path), "sha256": digest, "files": len(selected), "bytes": archive_path.stat().st_size}, indent=2))
    return archive_path


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path, default=root / "dist")
    args = parser.parse_args()
    build(root, args.output_dir.resolve())


if __name__ == "__main__":
    main()
