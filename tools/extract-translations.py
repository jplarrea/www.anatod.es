#!/usr/bin/env python3
"""Extrae catálogos sin ejecutar PHP ni modificar HTML/JS minificados."""
import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
# Textos de arrays que se pasan dinámicamente a _l().
DYNAMIC_TEXTS = [
    'Español', 'Euskara', 'English',
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
]
LITERAL = re.compile(r"\b_l\(\s*'((?:\\.|[^'\\])*)'", re.S)


def php_unquote(value):
    return re.sub(r"\\([\\'])", r'\1', value)


def extract():
    texts = set(DYNAMIC_TEXTS)
    settings = json.loads((ROOT / 'languages/settings.json').read_text())
    texts.update(language['name'] for language in settings['languages'].values())
    def add(value):
        if isinstance(value, str) and value.strip():
            texts.add(value)

    files = list((ROOT / '_views').glob('*.html'))
    files += list(ROOT.glob('*.php'))
    files += [ROOT / 'blog/index.php', ROOT / 'blog/event/index.php', ROOT / 'events/index.php', ROOT / 'languages/bootstrap.php']
    for path in files:
        for match in LITERAL.finditer(path.read_text()):
            add(php_unquote(match[1]))
    for filename in ['site.json', 'site.min.json']:
        config = json.loads((ROOT / 'assets/config' / filename).read_text())
        for region in [config['defaults'], *config['domains'].values()]:
            for group in ['texts', 'messages']:
                for key, value in region.get(group, {}).items():
                    if key != 'common.locale':
                        add(value)
            add(region.get('country'))
            add(region.get('contact', {}).get('message'))
            add(region.get('contact', {}).get('label'))
        for value in config['pricing']['currencies'].values():
            add(value)
    for filename in ['blog/posts.json', 'blog/posts.min.json']:
        for post in json.loads((ROOT / filename).read_text()):
            for key in ['title', 'excerpt', 'content_html']:
                add(post.get(key))
            add(post.get('image', {}).get('alt'))
    for filename in ['events/eventos.json', 'events/eventos.min.json']:
        for event in json.loads((ROOT / filename).read_text()):
            for key in ['title', 'detail']:
                add(event.get(key))
    for filename in ['assets/config/clients.json', 'assets/config/clients.min.json']:
        for client in json.loads((ROOT / filename).read_text()):
            for key in ['nombre', 'localidad', 'pais']:
                add(client.get(key))
    return sorted(texts)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Verifica cobertura sin escribir archivos')
    args = parser.parse_args()
    texts = extract()
    errors = []
    settings = json.loads((ROOT / 'languages/settings.json').read_text())
    catalog_languages = [code for code in settings['languages'] if code != 'es']
    for language in catalog_languages:
        path = ROOT / 'languages' / (language + '.json')
        previous = json.loads(path.read_text()) if path.exists() else {}
        if args.check:
            missing = set(texts) - previous.keys()
            if missing:
                errors.append(f'{path.name}: faltan {len(missing)} textos')
            if any(not isinstance(value, str) for value in previous.values()):
                errors.append(f'{path.name}: las traducciones deben ser cadenas')
            for source, translation in previous.items():
                if translation:
                    placeholders = lambda value: sorted(re.findall(r'%(?:[0-9]+\$)?[sd]', value))
                    if placeholders(source) != placeholders(translation):
                        errors.append(f'{path.name}: marcadores incompatibles en {source[:80]!r}')
        else:
            values = {text: previous.get(text, '') for text in texts}
            # No borrar traducciones anteriores al retirar una frase del sitio.
            values.update({key: value for key, value in previous.items() if key not in values})
            path.write_text(json.dumps(values, ensure_ascii=False, indent=2) + '\n')
    if errors:
        raise SystemExit('\n'.join(errors))
    print(f'{len(texts)} textos únicos; catálogos ' + ('verificados' if args.check else 'actualizados'))


if __name__ == '__main__':
    main()
