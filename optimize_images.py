#!/usr/bin/env python3
"""
Otimizar imagens: comprimir JPG/PNG, gerar WebP
IMPORTANTE: Preserva EXIF sem aplicar rotação manual
"""

import os
from pathlib import Path
from PIL import Image

IMAGES_DIR = Path("assets/images")
QUALITY = 85
MAX_WIDTH = 2000

def optimize_image(image_path):
    """Otimiza imagem sem alterar EXIF"""
    try:
        img = Image.open(image_path)
        
        # Redimensiona se necessário (SEM aplicar rotação EXIF)
        if img.width > MAX_WIDTH:
            ratio = MAX_WIDTH / img.width
            new_h = int(img.height * ratio)
            img = img.resize((MAX_WIDTH, new_h), Image.Resampling.LANCZOS)
        
        # Converte RGBA para RGB apenas para JPEG
        if image_path.suffix.lower() in ['.jpg', '.jpeg'] and img.mode in ('RGBA', 'LA', 'P'):
            rgb = Image.new('RGB', img.size, (255, 255, 255))
            rgb.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
            img = rgb
        
        # Salva original otimizado (preserva EXIF automaticamente)
        if image_path.suffix.lower() in ['.jpg', '.jpeg']:
            img.save(image_path, quality=QUALITY, optimize=True)
        else:
            img.save(image_path, quality=QUALITY, optimize=True)
        
        # Cria versão WebP (sem EXIF, mas sem rotação manual)
        webp_path = image_path.with_suffix('.webp')
        img.save(webp_path, 'WEBP', quality=QUALITY, method=6)
        
        orig_size = os.path.getsize(image_path)
        webp_size = os.path.getsize(webp_path)
        print(f"✓ {image_path.name:40} | Original: {orig_size//1024:6}KB | WebP: {webp_size//1024:6}KB")
        
        return orig_size, webp_size
    except Exception as e:
        print(f"✗ {image_path.name}: {e}")
        return 0, 0

def main():
    print("Otimizando imagens (preservando EXIF)...\n")
    
    images = list(IMAGES_DIR.glob('**/*.jpg')) + list(IMAGES_DIR.glob('**/*.JPG')) + \
             list(IMAGES_DIR.glob('**/*.jpeg')) + list(IMAGES_DIR.glob('**/*.JPEG')) + \
             list(IMAGES_DIR.glob('**/*.png')) + list(IMAGES_DIR.glob('**/*.PNG'))
    
    if not images:
        print("Nenhuma imagem encontrada")
        return
    
    print(f"Encontradas {len(images)} imagens\n")
    
    total_before = 0
    total_webp = 0
    
    for img_path in sorted(images):
        before, webp = optimize_image(img_path)
        total_before += before
        total_webp += webp
    
    total_original_optimized = sum(os.path.getsize(p) for p in images if p.exists())
    
    print(f"\nResumo:")
    print(f"  Imagens originais otimizadas: {total_original_optimized / 1024 / 1024:.1f} MB")
    print(f"  Total WebP gerado: {total_webp / 1024 / 1024:.1f} MB")
    print(f"  Economia com WebP: {((total_original_optimized - total_webp) / total_original_optimized * 100):.0f}%")

if __name__ == "__main__":
    main()
