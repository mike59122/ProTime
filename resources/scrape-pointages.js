const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const cheerio = require('cheerio');
const { execSync } = require('child_process');
// 📥 Paramètres en ligne de commande

const LOG_PATH = path.join(__dirname, '../../../log/ProTime');

// 🧠 Logging
function getTimestamp() {
  const now = new Date.toLocaleString('fr-FR', {
    timeZone: 'Europe/Paris',
    hour12: false
  }).replace(',', '');


  const pad = (n) => n.toString().padStart(2, '0');

  const year = now.getFullYear();
  const month = pad(now.getMonth() + 1); // mois = 0-indexé
  const day = pad(now.getDate());
  const hours = pad(now.getHours());
  const minutes = pad(now.getMinutes());
  const seconds = pad(now.getSeconds());

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}function getTimestamp() {
  const now = new Date();

  const pad = (n) => n.toString().padStart(2, '0');

  const year = now.getFullYear();
  const month = pad(now.getMonth() + 1);
  const day = pad(now.getDate());
  const hours = pad(now.getHours());
  const minutes = pad(now.getMinutes());
  const seconds = pad(now.getSeconds());

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}


function log(message, level = 'INFO', showConsole = true) {
  
const now = new Date();
const timestamp = getTimestamp();

  // 📍 Récupérer la ligne d'appel via la stack trace
  const stack = new Error().stack;
  const callerLine = stack.split('\n')[2] || ''; // ligne 2 = appelant direct
  const match = callerLine.match(/:(\d+):\d+\)?$/);
  const lineNumber = match ? match[1] : '???';

  const line = `[${timestamp}][${level.toUpperCase()}] : Scrape-pointage.js (${lineNumber}): ${message}\n`;
  fs.appendFileSync(LOG_PATH, line);
}

// 🔍 Détection du chemin Chromium
function detectChromiumPath() {
  try {
    const path = execSync('which chromium-browser || which chromium || which google-chrome', { encoding: 'utf8' }).trim();
    if (path) {
      //log(`Chromium détecté : ${path}`);
      return path;
    }
  } catch {
    log('Aucun Chromium système détecté.');
  }
  return null;
}

// 🧹 Suppression des fichiers HTML
function cleanHtmlFiles() {
  fs.readdirSync(__dirname).forEach(file => {
    if (file.endsWith('.html')) {
      try {
        fs.unlinkSync(path.join(__dirname, file));
        // log(`Supprimé : ${file}`);
      } catch (err) {
        log(`Erreur suppression ${file} : ${err.message}`);
      }
    }
  });
}



// 🔐 Authentification SSO + chargement de la page
async function startSession(page, targetUrl, dateParam, username, password) {
  const fullUrl = `${targetUrl}?date=${dateParam}`;
  //log(`Ouverture de la page : ${fullUrl}`);
  await page.goto(fullUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });

  if (page.url().includes('fromunion.myprotime.eu')) {
    const isLoggedIn = await page.$('.hubMenu');
    if (isLoggedIn) {
      log('Session déjà active.');
    }
  }

  if (page.url().includes('https://authentication.myprotime.eu/Account')) {
    // 🔐 Authentification SSO
    log('Remplissage du formulaire SSO');
    await safeWaitForSelector(page, 'input[name="username"]', 'Champ Email');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);

    log('Soumission du formulaire SSO');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 });

    await page.waitForURL(fullUrl, { timeout: 30000 });
    log(`Retour sur le site principal`);

  }
  if (!page.url().includes('https://authentication.myprotime.eu/tenants/')) {
    log('Attente de redirection vers le serveur d’authentification...');
    await page.waitForURL(fullUrl, { timeout: 30000 });
    log('Redirection terminée');
  }


  await safeWaitForSelector(page, '._timecardWrapper_1qtro_5', 'Bloc Timecard');
  const html = await page.content();
  fs.writeFileSync(path.join(__dirname, `result-${dateParam}.html`), html);
  log(`Extraction terminée pour ${dateParam}`);
}

// 🔍 Attente sécurisée d’un élément
async function safeWaitForSelector(page, selector, label = selector, timeout = 15000) {
  try {
    log(`Attente de l’élément : ${label}`);
    await page.waitForSelector(selector, { timeout });
    log(`Élément détecté : ${label}`);
    return true;
  } catch (err) {
    log(`Élément non trouvé (${label})`);
    try {
      const html = await page.content();
      const safeName = label.replace(/[^a-z0-9]/gi, '_').toLowerCase();
      fs.writeFileSync(path.join(__dirname, `debug-missing-${safeName}.html`), html);
    } catch (e) {
      log(`Impossible de capturer le HTML : ${e.message}`);
    }
    return false;
  }
}

// 🚀 Script principal
(async () => {
  cleanHtmlFiles();
  const executablePath = detectChromiumPath();

  const browser = await chromium.launch({
    headless: true,
    executablePath, // facultatif si tu veux un binaire spécifique
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu'
    ]
  });

  const context = await browser.newContext(); // session temporaire



  const page = await context.newPage();
  const [username, password, url, jsonMois] = process.argv.slice(2);
  const moisAExtraire = JSON.parse(jsonMois); 



  for (const mois of moisAExtraire) {
    log(`=====================================================================`);
    log(`Extraction du mois : ${mois}`);
    try {
      await startSession(page, url, mois, username, password);

    } catch (err) {
      log(`Erreur sur ${mois} : ${err.message}`);
    }
  }

await page.close();
await context.close(); // ou browser.close()
process.exit(0);
})();