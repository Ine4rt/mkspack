#!/usr/bin/env python3
"""Assemble index.html + style.css + game.js en un fichier HTML autonome.

Deux sorties :
  dist/neon-surge.html   page complète, à déposer sur n'importe quel hébergeur
  dist/embed.html        même contenu sans <!doctype>/<html>/<head>/<body>,
                         pour les hôtes qui fournissent eux-mêmes l'enveloppe
"""
import pathlib, re

root = pathlib.Path(__file__).parent
html = (root / 'index.html').read_text(encoding='utf-8')
css  = (root / 'style.css').read_text(encoding='utf-8')
js   = (root / 'game.js').read_text(encoding='utf-8')

assert '</script>' not in js, "game.js contient une balise fermante qui casserait l'inlining"

html = html.replace('<link rel="stylesheet" href="style.css">',
                    '<style>\n' + css + '\n</style>')
html = html.replace('<script src="game.js"></script>',
                    '<script>\n' + js + '\n</script>')
html = html.replace('<link rel="manifest" href="manifest.json">', '')

out = root / 'dist'
out.mkdir(exist_ok=True)
(out / 'neon-surge.html').write_text(html, encoding='utf-8')

# Version « corps seul » : on retire l'enveloppe du document.
body = html
body = re.sub(r'(?is)^.*?<head[^>]*>', '', body)
body = body.replace('</head>', '').replace('</html>', '')
body = re.sub(r'(?is)<body[^>]*>', '', body).replace('</body>', '')
body = re.sub(r'(?im)^\s*<meta[^>]*>\s*$\n?', '', body)
(out / 'embed.html').write_text(body.strip() + '\n', encoding='utf-8')

for f in ('neon-surge.html', 'embed.html'):
    print(f'{f:20} {(out / f).stat().st_size / 1024:.1f} Ko')
