#!/bin/bash

INSTALL_DIR="/var/www/html/plugins/ProTime/core/class/../../resources/"
PROGRESS_FILE=/tmp/jeedom/ProTime/dependance #remplacez template par l'ID de votre plugin
echo "📦 Installation dans : $INSTALL_DIR"
cd "$INSTALL_DIR" || exit 1

echo "🧠 Vérification de Node.js..."
if ! command -v node &> /dev/null; then
  echo "⛔ Node.js n'est pas installé. Veuillez l’installer avant de continuer."
  exit 1
fi
echo 0 > ${PROGRESS_FILE}
echo "🧾 Création du package.json..."
cat <<EOF > package.json
{
  "name": "protime-playwright",
  "version": "1.0.0",
  "description": "Script autonome Playwright pour le plugin ProTime",
  "main": "scrape-pointages.js",
  "scripts": {
    "start": "node scrape-pointages.js"
  },
  "dependencies": {
    "playwright": "^1.44.0",
    "cheerio": "^1.0.0-rc.12",
    "dayjs": "^1.11.10"
  }
}
EOF


echo 30 > ${PROGRESS_FILE}
echo "📥 Installation des dépendances..."
PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm install
echo 50 > ${PROGRESS_FILE}
echo "📦 Tentative d'installation de Chromium via Playwright..."
npx playwright install chromium || echo "⚠️ Chromium Playwright non pris en charge sur ce système. Utilisation du Chromium système si disponible."
echo 90 > ${PROGRESS_FILE}
echo "🔐 Attribution des permissions..."
echo 100 > ${PROGRESS_FILE}
sudo chmod 777 /var/www/html/plugins/ProTime/resources/* -R
sudo chmod +x /var/www/html/plugins/ProTime/resources/install.sh
sudo chmod +x /var/www/html/plugins/ProTime/resources/scrape-pointages.js

echo "✅ Installation terminée dans : $INSTALL_DIR"
rm ${PROGRESS_FILE}