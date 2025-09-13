<?php
  require_once __DIR__ . '/../../../../core/php/core.inc.php';




if (init('action') == 'getTablePointages') {
  $id = (int) init('eqlogic_id'); // ✅ Cast en entier
  $moisStr = init('mois');
  $mois = DateTime::createFromFormat('Y-m', $moisStr);
  $html = ProTime::renderTablePointage($id, $mois);
  ajax::success($html);
}