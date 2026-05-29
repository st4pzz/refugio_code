#!/usr/bin/env python3
"""
Rotate selected images 90 degrees clockwise.

Usage examples:
  python rotate_images_right.py assets/images/foto.webp
  python rotate_images_right.py --list imagens_para_rotacionar.txt
  python rotate_images_right.py --list imagens.txt --no-backup

The list file should contain one image path per line. Blank lines and lines
starting with # are ignored.
"""

from __future__ import annotations

import argparse
import shutil
import sys
from datetime import datetime
from pathlib import Path


SUPPORTED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Rotate one or more images 90 degrees clockwise.",
    )
    parser.add_argument(
        "images",
        nargs="*",
        help="Image paths to rotate. Globs like assets/images/*.webp are supported.",
    )
    parser.add_argument(
        "--list",
        dest="list_file",
        help="Text file with one image path per line.",
    )
    parser.add_argument(
        "--quality",
        type=int,
        default=90,
        help="JPEG/WebP quality when saving. Default: 90.",
    )
    parser.add_argument(
        "--backup-dir",
        default="backups/rotated-images",
        help="Directory where backups are stored. Default: backups/rotated-images.",
    )
    parser.add_argument(
        "--no-backup",
        action="store_true",
        help="Overwrite files without creating backups.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Show what would be rotated without changing files.",
    )
    return parser.parse_args()


def read_list_file(path: Path) -> list[str]:
    if not path.exists():
        raise FileNotFoundError(f"List file not found: {path}")

    entries: list[str] = []
    for line in path.read_text(encoding="utf-8").splitlines():
        clean = line.strip().strip('"').strip("'")
        if not clean or clean.startswith("#"):
            continue
        entries.append(clean)
    return entries


def expand_inputs(raw_paths: list[str]) -> list[Path]:
    paths: list[Path] = []
    for raw in raw_paths:
        raw = raw.strip().strip('"').strip("'")
        if not raw:
            continue

        if any(char in raw for char in "*?[]"):
            matches = sorted(Path().glob(raw))
            paths.extend(matches)
        else:
            paths.append(Path(raw))

    seen: set[Path] = set()
    unique: list[Path] = []
    for path in paths:
        resolved = path.resolve()
        if resolved in seen:
            continue
        seen.add(resolved)
        unique.append(path)
    return unique


def backup_image(image_path: Path, backup_root: Path, run_id: str) -> Path:
    try:
        relative_path = image_path.resolve().relative_to(Path.cwd().resolve())
    except ValueError:
        relative_path = Path(image_path.name)

    backup_path = backup_root / run_id / relative_path
    backup_path.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(image_path, backup_path)
    return backup_path


def save_rotated_image(rotated, image_path: Path, quality: int) -> None:
    suffix = image_path.suffix.lower()

    if suffix in {".jpg", ".jpeg"}:
        if rotated.mode not in ("RGB", "L"):
            rotated = rotated.convert("RGB")
        rotated.save(image_path, quality=quality, optimize=True, progressive=True)
        return

    if suffix == ".webp":
        if rotated.mode not in ("RGB", "RGBA"):
            rotated = rotated.convert("RGB")
        rotated.save(image_path, "WEBP", quality=quality, method=6)
        return

    if suffix == ".png":
        rotated.save(image_path, optimize=True)
        return

    raise ValueError(f"Unsupported extension: {image_path.suffix}")


def rotate_image(image_path: Path, quality: int, backup_root: Path, run_id: str, no_backup: bool, dry_run: bool) -> bool:
    if image_path.suffix.lower() not in SUPPORTED_EXTENSIONS:
        print(f"SKIP unsupported: {image_path}")
        return False

    if not image_path.exists():
        print(f"MISS not found: {image_path}")
        return False

    if dry_run:
        print(f"DRY  rotate right: {image_path}")
        return True

    try:
        from PIL import Image, ImageOps
    except ImportError as exc:
        raise SystemExit(
            "Pillow is required. Install it with: python -m pip install Pillow"
        ) from exc

    backup_path = None
    if not no_backup:
        backup_path = backup_image(image_path, backup_root, run_id)

    with Image.open(image_path) as image:
        normalized = ImageOps.exif_transpose(image)
        rotated = normalized.transpose(Image.Transpose.ROTATE_270)
        save_rotated_image(rotated, image_path, quality)

    if backup_path:
        print(f"OK   rotated: {image_path} | backup: {backup_path}")
    else:
        print(f"OK   rotated: {image_path}")
    return True


def main() -> int:
    args = parse_args()
    raw_paths = list(args.images)

    if args.list_file:
        raw_paths.extend(read_list_file(Path(args.list_file)))

    image_paths = expand_inputs(raw_paths)
    if not image_paths:
        print("No images provided.")
        return 1

    backup_root = Path(args.backup_dir)
    run_id = datetime.now().strftime("%Y%m%d-%H%M%S")
    rotated_count = 0

    for image_path in image_paths:
        try:
            if rotate_image(
                image_path=image_path,
                quality=args.quality,
                backup_root=backup_root,
                run_id=run_id,
                no_backup=args.no_backup,
                dry_run=args.dry_run,
            ):
                rotated_count += 1
        except Exception as exc:
            print(f"FAIL {image_path}: {exc}", file=sys.stderr)

    print(f"\nDone. Images processed: {rotated_count}/{len(image_paths)}")
    return 0 if rotated_count else 1


if __name__ == "__main__":
    raise SystemExit(main())
