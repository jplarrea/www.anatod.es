#!/usr/bin/env python3
"""Sincroniza claves de JSON servido sin modificar HTML/JS ni ejecutar PHP."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return json.loads(path.read_text())


def sync():
    settings = read(ROOT / 'languages/settings.json')
    for language in settings['languages']:
        if language == 'es':
            continue
        path = ROOT / 'languages' / (language + '.json')
        catalog = read(path)
        # La minificación del feed puede cambiar espacios del HTML y, por tanto,
        # la clave exacta. El slug identifica el mismo artículo en ambos feeds.
        for folder, filename, fields in [
            ('blog', 'posts', ['title', 'excerpt', 'content_html']),
            ('events', 'eventos', ['title', 'detail']),
        ]:
            sources = {item.get('id', item['slug']): item for item in read(ROOT / folder / (filename + '.json'))}
            for served in read(ROOT / folder / (filename + '.min.json')):
                original = sources.get(served.get('id', served['slug']))
                if original is None:
                    continue
                for field in fields:
                    text = original.get(field)
                    target = served.get(field)
                    key = f'{folder}.{original["id"]}.{field}' if isinstance(original.get('id'), int) else text
                    translation = catalog.get(key, catalog.get(text))
                    if isinstance(target, str) and isinstance(translation, str) and translation.strip():
                        if isinstance(original.get('id'), int):
                            catalog.setdefault(key, translation)
                        else:
                            catalog[target] = translation
                if folder == 'blog':
                    text = original.get('image', {}).get('alt')
                    target = served.get('image', {}).get('alt')
                    if isinstance(target, str) and isinstance(catalog.get(text), str):
                        catalog[target] = catalog[text]
                        if isinstance(original.get('id'), int):
                            catalog.setdefault(f'blog.{original["id"]}.image_alt', catalog[text])
        path.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + '\n')
        served_path = path.with_name(language + '.min.json')
        served_path.write_text(json.dumps(catalog, ensure_ascii=False, separators=(',', ':')) + '\n')
        print(f'{language}: {len(catalog)} claves sincronizadas')


if __name__ == '__main__':
    sync()
