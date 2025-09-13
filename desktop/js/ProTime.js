


function togglePassword() {
  var input = document.getElementById('passwordInput');
  var icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}


set_sortable(document.getElementById('table_cmd'),'tbody','')

function set_sortable(Element_id,draggable,filter){
  new Sortable(Element_id, {
    delay: 500,
    draggable: draggable,
    direction: 'vertical',
    filter: filter,
    preventOnFilter: false,
    chosenClass: 'dragSelected',
    animation: 150,
    ghostClass: 'blue-background-class',
    onUpdate: function(evt) {
      jeeFrontEnd.modifyWithoutSave = true
    }
  })
}

function setSelect() {
  const select = document.getElementById('select_mois');

  if (!select) return;
  select.innerHTML=''
  const today = new Date();
  for (let i = 0; i < 12; i++) {
    const d = new Date(today.getFullYear(), today.getMonth() - i, 15);
    const val = d.toISOString().slice(0, 7);
    const label = d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    const option = document.createElement('option');
    option.value = val;
    option.textContent = label.charAt(0).toUpperCase() + label.slice(1);
    select.appendChild(option);
  }
}

function chargerTableauPointage() {
  const mois = document.getElementById('select_mois').value ;
  const eqId = document.querySelector('.eqLogicAttr[data-l1key="id"]').value;
  const container = document.getElementById('table_pointage_container');

  container.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';

  fetch('plugins/ProTime/core/ajax/ProTime.ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'getTablePointages',
      eqlogic_id: eqId,
      mois: mois
    })
  })
    .then(r => r.json())
    .then(data => container.innerHTML = data.result)
    .catch(() => container.innerHTML = '<div class="alert alert-danger">❌ Erreur de chargement du tableau</div>');
}

function addCmdToTable(_cmd) {
  if (typeof _cmd !== 'object') _cmd = { configuration: {} };
  if (!_cmd.configuration) _cmd.configuration = {};

  let tr = `
      <tr class="cmd" data-cmd_id="${init(_cmd.id)}">
          <td>
          	<input class="cmdAttr form-control input-sm" data-l1key="id">

          </td>
          <td>           
           <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">
          </td>
          <td>
          	<input class="cmdAttr form-control type input-sm" data-l1key="type" value="${_cmd.type}" disabled style="margin-bottom:5px;">
            <input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none">
          </td>

          <td>
          `
  if (typeof jeeFrontEnd !== 'undefined' && jeeFrontEnd.jeedomVersion !== 'undefined') {
    tr += `<span class="cmdAttr" data-l1key="htmlstate"></span>`;
  }
  tr += `
      </td>
      <td>
          <span>
          	<label class="checkbox-inline">
            	<input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized"/> {{Historiser}}
            </label>
            <label class="checkbox-inline">
        		<input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}
      		</label>
          </span>
      </td>
      <td>
      `;
  if (is_numeric(_cmd.id)) {
    tr += `
        <a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a>
        <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>
      `;
  }
  tr += `
        </td>
    </tr>
    `;
  document.getElementById('table_cmd').insertAdjacentHTML('beforeend', tr);

  const _tr = document.getElementById('table_cmd').lastChild;
  _tr.setJeeValues(_cmd, '.cmdAttr');
  jeedom.cmd.changeType(_tr, init(_cmd.subType));
  _tr.querySelector('.cmdAttr[data-l1key=type],.cmdAttr[data-l1key=subType]').setAttribute("disabled", true);



  setSelect();

  chargerTableauPointage();
}