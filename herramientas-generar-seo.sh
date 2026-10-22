#!/bin/bash
# =============================================================================
#  generar-seo.sh  <URL_BASE>            p.ej.  https://midominio.com
#  Escribe robots.txt y sitemap.xml en el webroot de SkillSwap.
#  Reejecutalo cada vez que cambie el dominio (tunel, hosting, dominio propio).
# =============================================================================
set -euo pipefail
BASE="${1:?Uso: generar-seo.sh https://tu-dominio.com   (sin barra final)}"
BASE="${BASE%/}"
WEB="/var/www/html/skillswap"

cat > "$WEB/robots.txt" <<EOF
# SkillSwap - robots.txt
# Ultima actualizacion: $(date +%Y-%m-%d)

User-agent: *
Allow: /skillswap/
Allow: /skillswap/index.php
Allow: /skillswap/assets/

# Zonas privadas / de aplicacion (no indexar)
Disallow: /skillswap/modules/
Disallow: /skillswap/admin/
Disallow: /skillswap/includes/
Disallow: /skillswap/config/
Disallow: /skillswap/database/
Disallow: /skillswap/pruebas/

Sitemap: ${BASE}/skillswap/sitemap.xml
EOF

cat > "$WEB/sitemap.xml" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>${BASE}/skillswap/</loc>
    <lastmod>$(date +%Y-%m-%d)</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
</urlset>
EOF

echo "Generado en $WEB :"
echo "  - robots.txt   (Sitemap -> ${BASE}/skillswap/sitemap.xml)"
echo "  - sitemap.xml"
