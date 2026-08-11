"""Generate lightweight, semantic image derivatives for editorial SEO pages."""

from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "assets" / "images" / "seo"
OUTPUT.mkdir(parents=True, exist_ok=True)

IMAGES = {
    "chacara-refugio-cuscuzeiro-analandia.webp": "assets/images/imagens_a_rotacionar/Noturno.webp",
    "pedra-do-cuscuzeiro-analandia.webp": "assets/images/cuscuzeiro.webp",
    "varanda-refugio-cuscuzeiro-analandia.webp": "assets/images/varanda.webp",
    "piscina-refugio-cuscuzeiro-analandia.webp": "assets/images/piscina.webp",
    "churrasqueira-refugio-cuscuzeiro.webp": "assets/images/churrasqueira.webp",
    "paisagem-analandia-cuscuzeiro.webp": "assets/images/cuscuzeiro_cuscoville_terrenos.webp",
    "ecoturismo-analandia.webp": "assets/images/foto_ecoturismo.webp",
    "passeio-ao-ar-livre-analandia.webp": "assets/images/ciclismo.webp",
}

for filename, relative_source in IMAGES.items():
    source = ROOT / relative_source
    target = OUTPUT / filename
    with Image.open(source) as image:
        image = ImageOps.exif_transpose(image).convert("RGB")
        image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
        image.save(target, "WEBP", quality=78, method=6)
        print(f"{target.relative_to(ROOT)} | {image.width}x{image.height} | {target.stat().st_size} bytes")
