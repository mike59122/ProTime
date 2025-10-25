<?php



  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class ProTime extends eqLogic {

public function copie_html_png($eqLogic){
  $sourceDir = __DIR__ . '/../../resources/';
  $destinationDir = __DIR__ . '/../../archives/';

  // 🔧 Crée le dossier destination s’il n’existe pas
  if (!is_dir($destinationDir)) {
      mkdir($destinationDir, 0755, true);
  }

  // 📁 Liste tous les fichiers HTML dans le dossier source
  $htmlFiles = glob($sourceDir . '*.html');

  foreach ($htmlFiles as $filePath) {
      $filename = basename($filePath); // ex: debug-missing-10-2025.html
      $horodatage = date('H:i'); // ex: 04:12

      // 🧩 Nouveau nom avec horodatage
      $newFilename = pathinfo($filename, PATHINFO_FILENAME) . ' ' . $horodatage . '(' . $eqLogic->getId() .')' . '.html';
      $destinationPath = $destinationDir . $newFilename;

      // 📤 Copie
      if (copy($filePath, $destinationPath)) {
         self::add_log('info',  "✅ Copié : $filename → $newFilename");
      } else {
           self::add_log('error',  "❌ Échec : $filename");
      }
  }

   // 📁 Liste tous les fichiers png dans le dossier source
  $pngFiles = glob($sourceDir . '*.png');

  foreach ($pngFiles as $filePath) {
      $filename = basename($filePath); // ex: debug-missing-10-2025.png
      $horodatage = date('H:i'); // ex: 04:12

      // 🧩 Nouveau nom avec horodatage
      $newFilename = pathinfo($filename, PATHINFO_FILENAME) . ' ' . $horodatage . '(' . $eqLogic->getId() .')' . '.png';
      $destinationPath = $destinationDir . $newFilename;

      // 📤 Copie
      if (copy($filePath, $destinationPath)) {
         self::add_log('info',  "✅ Copié : $filename → $newFilename");
      } else {
           self::add_log('error',  "❌ Échec : $filename");
      }
  }


}
  public function traiterPointages($moisArray, $username, $password, $urlBase, $eqLogic) {
    $script = __DIR__ . '/../../resources/scrape-pointages.js';
    $html='';

    // Construction de la commande
    $jsonMois = json_encode($moisArray);
    $cmd = "node " . $script . " " .
      escapeshellarg($username) . " " .
      escapeshellarg($password) . " " .
      escapeshellarg($urlBase) . " " .
      escapeshellarg($jsonMois);

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
      self::add_log('debug', '$returnCode scrape-pointages.js : ' .$returnCode);
      self::add_log('debug', 'Retour scrape-pointages.js : ' . implode("\n", $output));
      self::add_log('info', "⛔ Erreur lors de l'exécution du script scrape-pointages.js" );
      $cmd = cmd::byEqLogicIdAndLogicalId(  $eqLogic->getId(),  "erreur");
        if (is_object($cmd)){
          if ($cmd->execCmd() != 1){
            $cmd->event(1);
          }
        }
         self::copie_html_png($eqLogic);
      return;
    }

    foreach ($moisArray as $mois){

      $fichier_html =  __DIR__ . '/../../resources/result-'.$mois.'.html';
      $fichiersHtml = glob(__DIR__ . '/../../resources/*.html');
      if (file_exists($fichier_html) && count($fichiersHtml) == 1) {

        $html = file_get_contents($fichier_html);
        if (self::verifierMaintenance($html)) {
          self::add_log('warning', "🚧 Page détectée comme en maintenance (avant login).");
          return 'maintenance';
        }
        self::getAllPointages($html, $eqLogic, substr($mois, 0, 4));
         self::add_log('info', $mois);
        $moisCourant = new DateTime('first day of this month');
         self::add_log('info',$moisCourant->format('Y-m-d'));
        if($mois == $moisCourant->format('Y-m-d')){
          foreach ([
            'pointage_jour' => date('Y-m-d'),
            'pointage_veille' => date('Y-m-d', strtotime('-1 day'))
          ] as $logicalId => $dateTest) {
            $cmd = $this->getCmd(null, $logicalId);
            if (is_object($cmd)) {
              $pointages = self::getPointagesParDate($eqLogic->getId(), $dateTest);
              $present = isset($pointages[0]["heure_debut"]) && !empty($pointages[0]["heure_debut"]) ? 1 : 0;
              $cmd->event($present);
              self::add_log('info', "Présence pour $logicalId ($dateTest) : " . ($present ? "Oui" : "Non"));
            }
          }
          $cmd = cmd::byEqLogicIdAndLogicalId(  $eqLogic->getId(),  "erreur");
          if (is_object($cmd)){
            if ($cmd->execCmd() != 0){
              $cmd->event(0);
            }
          }
        }
      } else {
        $cmd = cmd::byEqLogicIdAndLogicalId(  $eqLogic->getId(),  "erreur");
        if (is_object($cmd)){
          if ($cmd->execCmd() != 1){
            $cmd->event(1);
          }
        }
       
       
        self::add_log('error', "⛔ Fichier illisible : $fichier_html");
      }
    }
    self::copie_html_png($eqLogic);
    return $html;

  }

  static function add_log($level = 'debug', $Log) {
    if (is_array($Log)) $Log = json_encode($Log);
    if (is_object($Log)) $Log = json_encode($Log);
    if(count(debug_backtrace(false, 2)) == 1){
      $function_name = debug_backtrace(false, 2)[0]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }else{
      $function_name = debug_backtrace(false, 2)[1]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }
    $msg =  $function_name .' (' . $ligne . '): '.$Log;
    log::add("ProTime", $level, $msg);
  }

  public static function dependancy_info() {

    $cheminInstall = "/var/www/html/plugins/" . __CLASS__  . '/resources';
    $nodeModules = $cheminInstall . '/node_modules';
    $playwrightPackage = $nodeModules . '/playwright/package.json';

    $return = array();
    $return['log'] = 'ProTime_dependance';

    $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
    $return['state'] = 'ok';


    if (!is_dir($nodeModules) || !file_exists($playwrightPackage)) {
      $return['state'] = 'nok';
    }

    return $return;   

  }

  public static function dependancy_install() {
    log::remove(__CLASS__ . '_dependance');
    return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_dependance'));
  }

  public static function GetVersionPlugin() {
    $pluginVersion = 'introuvable';
    try {
      $path = dirname(__FILE__) . '/../../plugin_info/info.json';
      if (!file_exists($path)) {
        self::add_log('warning', 'Fichier info.json manquant');
        return $pluginVersion;
      }

      $data = json_decode(file_get_contents($path), true);
      if (!is_array($data)) {
        self::add_log('warning', 'Impossible de décoder info.json');
        return $pluginVersion;
      }

      $pluginVersion = $data['pluginVersion'] ?? 'introuvable';

    } catch (Exception $e) {
      self::add_log('warning', 'Erreur: ' . $e->getMessage());
    }

    self::add_log('info', 'Version du plugin : ' . $pluginVersion);
    return $pluginVersion;
  }

  public static function GetPluginInfo() {
    $version = self::GetVersionPlugin();
    $count = count(eqLogic::byType('ProTime'));
    self::add_log('info', "🧠 Plugin ProTime → Version : $version, équipements : $count");
  }

  public function postSave() {
    $Cmd = $this->getCmd(null, 'refresh');
    if (!is_object($Cmd)) {
      $Cmd = new cmd();
      $Cmd->setName('Rafraîchir');
      $Cmd->setEqLogic_id($this->getId());
      $Cmd->setLogicalId('refresh');
      $Cmd->setType('action');
      $Cmd->setSubType('other');
      $Cmd->setOrder('1');
      $Cmd->save();
    }
    $Cmd = $this->getCmd(null, 'pointage_jour');
    if (!is_object($Cmd)) {
      $Cmd = new cmd();
      $Cmd->setName("Pointage aujourd'hui");
      $Cmd->setEqLogic_id($this->getId());
      $Cmd->setLogicalId('pointage_jour');
      $Cmd->setType('info');
      $Cmd->setSubType('binary');
      $Cmd->setOrder('2');
      $Cmd->save();
    }
    $Cmd = $this->getCmd(null, 'pointage_veille');
    if (!is_object($Cmd)) {
      $Cmd = new cmd();
      $Cmd->setName("Pointage hier");
      $Cmd->setEqLogic_id($this->getId());
      $Cmd->setLogicalId('pointage_veille');
      $Cmd->setType('info');
      $Cmd->setSubType('binary');
      $Cmd->setOrder('3');
      $Cmd->save();
    }
    $cmd = $this->getCmd(null, 'refresh_last_12_months');
    if (!is_object($cmd)) {
      $cmd = new ProTimeCmd();
      $cmd->setName('Rafraîchir les 12 derniers mois');
      $cmd->setLogicalId('refresh_last_12_months');
      $cmd->setType('action');
      $cmd->setSubType('other');
      $cmd->setEqLogic_id($this->getId());
      $cmd->save();
    } 
    $cmd = $this->getCmd(null, 'erreur');
    if (!is_object($cmd)) {
      $cmd = new ProTimeCmd();
      $cmd->setName('Erreur Protime');
      $cmd->setEqLogic_id($this->getId());
      $cmd->setLogicalId('erreur');
      $cmd->setType('info');
      $cmd->setSubType('binary');
      $Cmd->setOrder('4');
      $cmd->save();
    }
  }

  public static function installerTablePointage() {
    // Vérifie si la table existe
    $check = DB::Prepare("SHOW TABLES LIKE 'protime_pointage'", []);
    if (is_array($check) && count($check) > 0) {
      //self::add_log('info', "✅ La table 'protime_pointage' existe déjà.");
      return;
    }

    // Crée la table avec clé UNIQUE sur eqlogic_id + date
    $sql = "
          CREATE TABLE IF NOT EXISTS protime_pointage (
          eqlogic_id INT NOT NULL,
          date DATE NOT NULL,
          heure_debut TIME DEFAULT NULL,
          heure_fin TIME DEFAULT NULL,
          duree TIME DEFAULT NULL,
          absence TEXT DEFAULT NULL,
          PRIMARY KEY (eqlogic_id, date)
          )";

    DB::Prepare($sql, []);
    self::add_log('info', "📦 Table 'protime_pointage' créée avec succès.");
  }

  public static function insererPointage($eqLogic, $ligne, $annee) {
    try {
      if ($ligne['date'] === 'Total') {
        // 📌 Ligne de synthèse → on ignore
        return;
      }


      // Nettoyage & préparation
      $eqlogic_id = $eqLogic->getId();
      $dateStr = preg_replace('/^...\. /', '', $ligne['date']); // "lun. 01/07" → "01/07"
      $dt = DateTime::createFromFormat('d/m/Y', $dateStr . '/' . $annee);

      if (!$dt) throw new Exception("Date invalide : " . $ligne['date']);
      $date = $dt->format('Y-m-d');

      $debut  = (!empty($ligne['début']) && $ligne['début'] !== '-') 
        ? (is_object($ligne['début']) ? $ligne['début']->format('H:i') : $ligne['début']) 
        : null;
      $fin    = (!empty($ligne['fin']) && $ligne['fin'] !== '-') 
        ? (is_object($ligne['fin']) ? $ligne['fin']->format('H:i') : $ligne['fin']) 
        : null;

      $duree  = (!empty($ligne['durée'])) ? $ligne['durée'] : null;
      $absence = (!empty($ligne['absence']) && $ligne['absence'] !== '-') ? $ligne['absence'] : null;

      // UPSERT SQL
      $sql = "INSERT INTO protime_pointage 
      (eqlogic_id, date, heure_debut, heure_fin, duree, absence)
      VALUES (:eqlogic_id, :date, :debut, :fin, :duree, :absence)
      ON DUPLICATE KEY UPDATE
        heure_debut = VALUES(heure_debut),
        heure_fin = VALUES(heure_fin),
        duree = VALUES(duree),
        absence = VALUES(absence);";


      DB::Prepare($sql, [
        'eqlogic_id' => $eqlogic_id,
        'date' => $date,
        'debut' => $debut,
        'fin' => $fin,
        'duree' => $duree,
        'absence' => $absence
      ]);

      //self::add_log('info', "📥 Ligne insérée ou mise à jour → $date ($eqlogic_id)");
    } catch (Exception $e) {
      self::add_log('error', "❌ Erreur insertion ($eqlogic_id / {$ligne['date']}) : " . $e->getMessage());
    }

  }

  public static function getPointagesParDate($eqlogic_id, $date = null): array {
    try {
      if ($date) {
        $sql = "SELECT * FROM protime_pointage WHERE eqlogic_id = :id AND date = :d ORDER BY date ASC";
        $params = ['id' => $eqlogic_id, 'd' => $date];


      }

      $rows = DB::Prepare($sql,$params, DB::FETCH_TYPE_ALL);

      //self::add_log('info', "📊 Pointages récupérés pour l'équipement $eqlogic_id : " . count($rows) . " ligne(s)");


      return $rows;
    } catch (Exception $e) {
      self::add_log('error', "❌ Erreur récupération des pointages : " . $e->getMessage());
      return [];
    }
  }


  public static function renderTablePointage(int $eqLogicId, DateTime $mois): string {//Fonction pour AJAX

    
    

    $startDate = $mois->format('Y-m-01');
    $endDate   = $mois->format('Y-m-t');

    $sql = "SELECT * FROM protime_pointage WHERE eqlogic_id = :id AND date BETWEEN :start AND :end ORDER BY date ASC";
    $pointages = DB::Prepare($sql, ['id' => $eqLogicId, 'start' => $startDate, 'end' => $endDate], DB::FETCH_TYPE_ALL);
    $html = '<table class="table table-striped table-bordered">';
    $html .= '<thead><tr><th>Date</th><th>Début</th><th>Fin</th><th>Durée</th><th>Absence</th></tr></thead><tbody>';

    foreach ($pointages as $ligne) {
      setlocale(LC_TIME, 'fr_FR.UTF-8'); // ou 'fr_FR.utf8' selon ton système
      $date = strftime('%A %d %B %Y', strtotime($ligne['date']));
      $html .= "<tr>";
      $html .= '<td>' . $date . '</td>';
      $html .= '<td>' . ($ligne['heure_debut'] ?? '-') . '</td>';
      $html .= '<td>' . ($ligne['heure_fin'] ?? '-') . '</td>';
      $html .= '<td>' . ($ligne['duree'] ?? '-') . '</td>';
      $html .= '<td>' . ($ligne['absence'] ?? '-') . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
  }

  private static function extraireHeure($texte): ?DateTime {
    $texte = trim($texte);
    if ($texte === '' || $texte === '-') return null;

    $heureStr = substr($texte, 0, 5);
    return DateTime::createFromFormat('H:i', $heureStr) ?: null;
  }

  public function RecupInfos() {
    self::installerTablePointage();

    $start = microtime(true);
    $username = $this->getConfiguration('username');
    $password = $this->getConfiguration('password');
    $urlBase = $this->getConfiguration('url_login');
    $eqLogic = $this;

    try {
      $aujourdHui = new DateTime();
      $moisCourant = new DateTime('first day of this month');
      $moisPrecedent = new DateTime('first day of last month');
      $dernierJourPrecedent = new DateTime('last day of last month');

      // 📋 Par défaut, on veut le mois courant
      $moisAExtraire = [$moisCourant];

      // 🔍 Vérifie si le dernier jour du mois précédent est incomplet
      if ($aujourdHui->format('d') === '01') {
        $dateDernierJour = $dernierJourPrecedent->format('Y-m-d');
        $pointages = self::getPointagesParDate($eqLogic->getId(), $dateDernierJour);
        if (!empty($pointages[0]["heure_debut"]) && empty($pointages[0]["heure_fin"]) && !empty($pointages[0]["absence"])) {
          $moisAExtraire[] = $moisPrecedent;
          self::add_log('info', "Pointage incomplet le $dateDernierJour → mois précédent à recharger.");
        } else {
          self::add_log('info', "Dernier jour du mois précédent est complet.");
        }
      }

      // 🔁 Extraction des mois nécessaires

      $moisEnvoyes = [];

      foreach ($moisAExtraire as $mois) {

        $moisEnvoyes[] = $mois->format('Y-m-d');       // ✅ Ajout dans le tableau
        //self::add_log('info', $moisEnvoyes[]);
        //$url = $urlBase . '?date=' . $mois->format('Y-m-d');
        //self::add_log('info',  Extraction du mois : $moisTxt → $url");

        // Traitement local si nécessaire
        //$html = self::traiterPointages($mois->format('Y-m-d'), $username, $password, $urlBase, $eqLogic);

      }

      self::traiterPointages($moisEnvoyes, $username, $password, $urlBase, $eqLogic);

      // 📌 Mise à jour des commandes jour & veille


      // ⏱️ Durée
      $end = microtime(true);
      $duree = round($end - $start, 2);


      self::add_log('info', "⏱️ Durée totale d’exécution : {$duree} sec");

    }catch (Throwable $e) {
      $message = $e->getMessage();
      if (empty($message)) {
        $message = '⚠️ Aucun élément trouvé dans le délai imparti. Timeout Selenium ?';
        self::add_log('warning', "[{$this->getName()}] Erreur : $message");
      }else{
        self::add_log('error', "[{$this->getName()}] Erreur : $message");
        self::add_log('error', 'Trace : ' . $e->getTraceAsString());
      }



    }

  }

  public function RecupInfosDerniersMois() {
    self::installerTablePointage();
    $start = microtime(true);
    $username = $this->getConfiguration('username');
    $password = $this->getConfiguration('password');
    $urlBase = $this->getConfiguration('url_login');
    $eqLogic = $this;

    self::add_log('info', 'Démarrage extraction des 12 derniers mois manuelle');

    $moisAExtraire = [];
    for ($i = 0; $i < 12; $i++) {
      $mois = new DateTime('first day of this month');
      $mois->modify("-$i month");
      $moisAExtraire[] = $mois;
    }

    foreach ($moisAExtraire as $mois) {
      $url = $urlBase . '?date=' . $mois->format('Y-m-d');
      self::add_log('info', "Extraction : " . $mois->format('Y-m') . " → $url");
      //$html = self::naviguerEtRecupererHtml($url, $username, $password);
      $html = self::traiterPointages($mois->format('Y-m-d'), $username, $password, $urlBase, $eqLogic);
      if ($html != "maintenance") {
        self::getAllPointages($html, $eqLogic,$mois->format('Y'));
      }
      foreach ([
        'pointage_jour' => date('Y-m-d'),
        'pointage_veille' => date('Y-m-d', strtotime('-1 day'))
      ] as $logicalId => $dateTest) {
        $cmd = $this->getCmd(null, $logicalId);
        if (is_object($cmd)) {
          $pointages = self::getPointagesParDate($eqLogic->getId(), $dateTest);
          $present = isset($pointages[0]["heure_debut"]) && !empty($pointages[0]["heure_debut"]) ? 1 : 0;
          $cmd->event($present);
          self::add_log('info', "Présence pour $logicalId ($dateTest) : " . ($present ? "Oui" : "Non"));
        }
      }
    }

    $end = microtime(true);
    $duree = round($end - $start, 2);
    self::add_log('info', "Extraction terminée : {$duree} sec");

    exec('sudo pkill -f chromedriver');
    exec('sudo pkill -f chromium');
  }



  private static function verifierMaintenance(string $html): bool {
    $signes = [
      'site en maintenance',
      'maintenance technique',
      'indisponible',
      'mise à jour',
      '503 Service Unavailable',
      'temporarily unavailable',
      'maintenance en cours',
      'une erreur est survenue',
      'maintenance'
    ];

    $html = strtolower($html);
    foreach ($signes as $motif) {
      if (strpos($html, strtolower($motif)) !== false) {
        return true;
      }
    }

    return false;
  }

  private static function nettoyerLibelleAbsence(string $raw): ?string {
    try {
      $libelle = strtolower(trim($raw));
      if ($libelle === '' || $libelle === '-') return null;

      $mapping = [
        'economische werkloosheid'		 	=> 'Chômage économique',
        'anciënniteitsverlof'     		 	=> 'Congé ancienneté',
        'recuperatie uren'        		 	=> 'Récup',
        'betaalde feestdag'      		  	=> 'Jour férié',
        'vakantie'				 		  	=> 'Congé',
        'staking'				 		  	=> 'Grève',
        'ir'				   	 		  	=> 'Repos compensatoire',
        'ir vj' 				 		  	=> 'Repos compensatoire année précédente',
        'or/cpbw'				  		 	=> 'Réunion syndicale',
        'ziekte'				 		  	=> 'Maladie',
        'vov'					 		  	=> 'Formation syndicale',
        'syndicale vorming'		 		  	=> 'Formation syndicale',
        'klein verlet'			  		 	=> 'Petit chômage',
        'opleiding intern binnen uren'		=> 'Formation'
      ];

      foreach ($mapping as $motCle => $traduction) {
        if (strpos($libelle, $motCle) !== false) {
          return $traduction;
        }
      }
    } catch (Exception $e) {
      self::add_log('error', '[' . $eqLogic->getName() . '] Erreur : ' . $e->getMessage());


    }
    return ucfirst($libelle); // retourne le libellé original si non reconnu
  }

  public static function getAllPointages($html,$eqLogic,$annee) :array{

    $resultats = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $rows = $dom->getElementsByTagName('tr');

    foreach ($rows as $row) {
      $cells = $row->getElementsByTagName('td');
      if ($cells->length >= 2) {




        $duree='';
        $date =   preg_replace('/^...\. /', '',trim($cells->item(0)->textContent));
        $absenceBrut = $cells->item(6)->textContent ?? '';
        $absence = self::nettoyerLibelleAbsence($absenceBrut);


        $heureDebut = self::extraireHeure($cells->item(1)->textContent);
        $heureFin = self::extraireHeure($cells->item(2)->textContent);

        if ($heureDebut instanceof DateTime && $heureFin instanceof DateTime) {
          try {
            $heureDebutCalc = self::arrondirQuartHeure(clone $heureDebut, 'debut');
            $heureFinCalc   = self::arrondirQuartHeure(clone $heureFin, 'fin');

            if ($heureFinCalc < $heureDebutCalc) {
              $heureFinCalc->modify('+1 day');
            }

            $interval = $heureDebutCalc->diff($heureFinCalc);
            $duree = $interval->format('%H:%I');

            $heureDebutStr = $heureDebut->format('H:i');
            $heureFinStr   = $heureFin->format('H:i');
          } catch (Throwable $e) {
            self::add_log('error', 'Erreur calcul durée : ' . $e->getMessage());
            $duree = '';
            $heureDebutStr = $heureDebut instanceof DateTime ? $heureDebut->format('H:i') : null;
            $heureFinStr   = $heureFin instanceof DateTime ? $heureFin->format('H:i') : null;
          }
        } else {
          $duree = '';
          $heureDebutStr = $heureDebut instanceof DateTime ? $heureDebut->format('H:i') : null;
          $heureFinStr   = $heureFin instanceof DateTime ? $heureFin->format('H:i') : null;
        }





        $resultats[] = [
          'date' => $date,
          'début' => $heureDebutStr,
          'fin' => $heureFinStr,
          'durée' => $duree,
          'absence' => $absence
        ];

      }

    } 
    self::add_log('info', "Insertion ou mise à jour des données en base de données (". $eqLogic->getName() . ")");
    foreach ($resultats as $ligne) {
      self::insererPointage($eqLogic, $ligne,$annee);
    }


    return $resultats;


  }

  private static function arrondirQuartHeure(?DateTime $dt, string $sens = 'debut'): ?DateTime {
    if (!$dt) return null;

    $minutes = (int) $dt->format('i');
    $modulo = $minutes % 15;

    if ($sens === 'debut' && $modulo !== 0) {
      $dt->modify('+' . (15 - $modulo) . ' minutes');
    } elseif ($sens === 'fin' && $modulo !== 0) {
      $dt->modify('-' . $modulo . ' minutes');
    }

    return $dt;
  }

  public function preUpdate() {
    if (trim($this->getConfiguration('username')) == '') {
      throw new Exception(__('Le champ Email ne peut pas être vide', __FILE__));
    }
    if (trim($this->getConfiguration('url_login')) == '') {
      throw new Exception(__('Le champ URL de connexion ne peut pas être vide', __FILE__));
    }
    if (trim($this->getConfiguration('password')) == '') {
      throw new Exception(__('Le champ Mot de passe ne peut pas être vide', __FILE__));
    }
  }
public static function cronHourly() {
  $now = time();
  $day = date('w', $now); // 0 = dimanche, 5 = vendredi, 6 = samedi
  $hour = date('G', $now); // heure en format 0–23
 
  
  // ⏳ Ignorer si l'heure est impaire
  if ($hour % 2 !== 0) {
    self::add_log('info', "⏳ Heure impaire ($hour h) → contrôle ignoré.");
    return;
  }

  // ⛔ Blocage du vendredi 23h00 au dimanche 22h00 sauf le samedi de 00h00 à 02h00
  if (($day == 5 && $hour >= 23) || ($day == 6 && $hour >= 2) || ($day == 0 && $hour < 22)) {
    self::add_log('info', "⏳ Cron ignoré (plage bloquée du vendredi 23h au dimanche 22h).");
    return;
  }

  // 🔁 Traitement normal
  $eqLogics = eqLogic::byType('ProTime');
  foreach ($eqLogics as $eqLogic) {
    try {
      self::add_log('info', "------------------------------------------------");
      self::add_log('info', "Vérification séquentielle Vérification (heure paire) → " . $eqLogic->getHumanName());
      $eqLogic->RecupInfos();
    } catch (Exception $e) {
      self::add_log('error', "Erreur sur " . $eqLogic->getHumanName() . " : " . $e->getMessage());
    }
    sleep(1);
  }

  self::add_log('info', "✅ Fin du contrôle (heure paire) pour tous les équipements ProTime.");
}

  /*public static function cronHourly() {

    $eqLogics = eqLogic::byType('ProTime');
    foreach ($eqLogics as $eqLogic) {
      try {
        self::add_log('info', "------------------------------------------------");
        self::add_log('info', "Vérification séquentielle (cronHourly) → " . $eqLogic->getHumanName());

        // ⚙️ Lancement du contrôle (bloquant)
        $eqLogic->RecupInfos();        
      } catch (Exception $e) {
        self::add_log('error', "Erreur sur " . $eqLogic->getHumanName() . " : " . $e->getMessage());     
      }

      // 💤 Petite pause entre deux si tu veux temporiser
      sleep(1);
    }
  }*/

}




class ProTimeCmd extends cmd {

  /*public function dontRemoveCmd() {
    return ($this->getLogicalId() == 'refresh');
  }*/




  public function execute($_options = null) {
    $eqLogic = $this->getEqLogic();
    if ($this->getLogicalId() == 'refresh') {
      $eqLogic::add_log('info', 'execution refresh');
      $eqLogic->RecupInfos();
      return;
    }
    if ($this->getLogicalId() == 'refresh_last_12_months') {
      $eqLogic::add_log('info', 'execution refresh 12 derniers mois');
      $eqLogic->RecupInfosDerniersMois(); // méthode que tu vas créer juste en dessous
    }

  }
}
?>