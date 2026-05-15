#!/usr/bin/env python3
"""
Adicionar loading="lazy" a todas as tags <img> do HTML
"""

import re
from pathlib import Path

html_file = Path("index.php")
content = html_file.read_text(encoding='utf-8')

def add_lazy_loading(match):
    tag = match.group(0)
    # Nao adiciona em hero-img (acima da dobra) e logo-img
    if 'hero-img' in tag or 'logo-img' in tag:
        return tag
    # Se ja tem loading, retorna sem alterar
    if 'loading=' in tag:
        return tag
    # Adiciona loading="lazy" antes do fechamento >
    return tag.replace('>', ' loading="lazy">', 1)

new_content = re.sub(r'<img[^>]*>', add_lazy_loading, content)
html_file.write_text(new_content, encoding='utf-8')

added = len(re.findall(r'loading="lazy"', new_content))
print(f"OK - Adicionado loading=lazy em {added} imagens")
