<?php
/**
 * options.php — Страница настроек модуля rocada.visits
 * URL: /bitrix/admin/settings.php?mid=rocada.visits&lang=ru
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

define('ADMIN_MODULE_NAME', 'rocada.visits');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);
Loader::includeModule('crm');
Loader::includeModule('rocada.visits');

$moduleId = ADMIN_MODULE_NAME;

// ── UF-поля сущностей ──────────────────────────────────────────────────────
function pwaGetUfFields(string $entityId = 'CRM_DEAL'): array
{
    $fields = [];
    $ute    = new CUserTypeEntity();
    $rs     = $ute->GetList([], ['ENTITY_ID' => $entityId]);
    while ($f = $rs->Fetch()) {
        $code  = $f['FIELD_NAME'];
        $label = '';
        $full  = $ute->GetByID($f['ID']);
        if ($full) {
            $label = $full['EDIT_FORM_LABEL']['ru']
                  ?? $full['LIST_FILTER_LABEL']['ru']
                  ?? $full['LIST_COLUMN_LABEL']['ru']
                  ?? '';
        }
        if (empty($label)) {
            $label = $f['XML_ID'] ?? '';
        }
        $fields[$code] = $label ? "$label ($code)" : $code;
    }
    return $fields;
}

function getPwaDealUfFields(): array { return pwaGetUfFields('CRM_DEAL'); }

// ── Активные пользователи ──────────────────────────────────────────────────
function pwaGetActiveUsers(): array
{
    $users = [];
    $rs    = \Bitrix\Main\UserTable::getList([
        'filter' => ['=ACTIVE' => 'Y'],
        'select' => ['ID', 'NAME', 'LAST_NAME', 'WORK_POSITION'],
        'order'  => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
    ]);
    while ($row = $rs->fetch()) {
        $label = trim(($row['LAST_NAME'] ?? '') . ' ' . ($row['NAME'] ?? ''));
        if ($row['WORK_POSITION']) {
            $label .= ' (' . $row['WORK_POSITION'] . ')';
        }
        $users[(int)$row['ID']] = $label;
    }
    return $users;
}

// ── Воронки и стадии CRM ───────────────────────────────────────────────────
function getPwaCrmCategories(): array
{
    $cats = [];
    foreach (\Bitrix\Crm\Category\DealCategory::getAll(true) as $cat) {
        $cats[] = ['id' => (int)$cat['ID'], 'name' => $cat['NAME'] ?? 'Основная'];
    }
    return $cats;
}

function getPwaCrmStages(): array
{
    $stages = [];
    foreach (\Bitrix\Crm\Category\DealCategory::getAll(true) as $cat) {
        $catId      = (int)$cat['ID'];
        $catName    = $cat['NAME'] ?? 'Основная';
        $statusType = $catId === 0 ? 'DEAL_STAGE' : 'DEAL_STAGE_' . $catId;
        foreach (\CCrmStatus::GetStatusList($statusType) as $id => $name) {
            $stages[] = [
                'id'          => $id,
                'name'        => $name,
                'category_id' => $catId,
                'label'       => "[$catName] $name",
            ];
        }
    }
    return $stages;
}

// ── Обработка сохранения POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {

    // ① Направления — весь массив приходит как JSON из hidden input
    $directionsJson = trim($_POST['pwa_directions_json'] ?? '');
    if ($directionsJson) {
        $decoded = json_decode($directionsJson, true);
        if (is_array($decoded)) {
            // Нормализация: убираем пустые и валидируем обязательные поля
            $normalised = [];
            foreach ($decoded as $d) {
                if (empty($d['id']) || empty($d['name'])) continue;

                // Нормализуем динамические статусы завершения
                $resultStatuses = [];
                foreach ((array)($d['result_statuses'] ?? []) as $st) {
                    if (empty($st['name'])) continue;
                    $resultStatuses[] = [
                        'id'           => preg_replace('/[^\w\-]/', '', $st['id'] ?? ('rs_' . uniqid())),
                        'name'         => mb_substr(trim($st['name']), 0, 100),
                        'color'        => preg_match('/^#[0-9a-fA-F]{3,6}$/', $st['color'] ?? '') ? $st['color'] : '#0066ff',
                        'stage'        => trim($st['stage'] ?? ''),
                        'photo_fields' => array_values(array_filter((array)($st['photo_fields'] ?? []))),
                        'is_successful'=> !empty($st['is_successful']),
                    ];
                }

                $normalised[] = [
                    'id'               => preg_replace('/[^\w\-]/', '', $d['id']),
                    'name'             => mb_substr(trim($d['name']), 0, 100),
                    'icon'             => $d['icon'] ?? 'briefcase',
                    'completion_type'  => in_array($d['completion_type'] ?? '', ['sales', 'service']) ? $d['completion_type'] : 'sales',
                    'pipelines'        => array_values(array_filter(array_map('intval', $d['pipelines'] ?? []))),
                    'stages_today'     => array_values(array_filter($d['stages_today']    ?? [])),
                    'stages_tomorrow'  => array_values(array_filter($d['stages_tomorrow'] ?? [])),
                    'lat_field'        => $d['lat_field']        ?? '',
                    'lng_field'        => $d['lng_field']        ?? '',
                    'comment_field'    => $d['comment_field']    ?? '',
                    'visit_date_field' => $d['visit_date_field'] ?? '',
                    'deal_fields'      => array_values(array_filter($d['deal_fields'] ?? [])),
                    'allowed_users'    => array_values(array_filter(array_map('intval', $d['allowed_users'] ?? []))),
                    'result_statuses'  => $resultStatuses,
                ];
            }

            Option::set($moduleId, 'pwa_directions', json_encode($normalised, JSON_UNESCAPED_UNICODE));
        }
    }

    // ② Глобальный доступ к PWA
    $pwaAllowed = array_values(array_filter(array_map('intval', (array)($_POST['pwa_allowed_users'] ?? []))));
    Option::set($moduleId, 'pwa_allowed_users', json_encode($pwaAllowed));

    // ③ Поля Компании / Контакта — глобальные
    Option::set($moduleId, 'pwa_company_fields', json_encode(array_values(array_filter((array)($_POST['company_fields'] ?? [])))));
    Option::set($moduleId, 'pwa_contact_fields', json_encode(array_values(array_filter((array)($_POST['contact_fields'] ?? [])))));

    $APPLICATION->AuthForm('Настройки сохранены');
}

// ── Загрузка текущих значений ─────────────────────────────────────────────
$currentDirections = json_decode(Option::get($moduleId, 'pwa_directions', '[]'), true) ?? [];

// Backward compatibility: если нет структуры (старый формат) — конвертируем
if (!empty($currentDirections) && !isset($currentDirections[0]['result_stages'])) {
    foreach ($currentDirections as &$d) {
        $d['result_stages'] = $d['result_stages'] ?? [
            'order'     => Option::get($moduleId, 'deal_stage_result_order',     ''),
            'refuse'    => Option::get($moduleId, 'deal_stage_result_refuse',    ''),
            'callback'  => Option::get($moduleId, 'deal_stage_result_callback',  ''),
            'completed' => Option::get($moduleId, 'deal_stage_result_completed', ''),
        ];
        $d['pipelines']   = $d['pipelines']   ?? [];
        $d['deal_fields'] = $d['deal_fields'] ?? [];
    }
    unset($d);
}

$companyFieldsSelected = json_decode(Option::get($moduleId, 'pwa_company_fields', '[]'), true) ?? [];
$contactFieldsSelected = json_decode(Option::get($moduleId, 'pwa_contact_fields', '[]'), true) ?? [];
$pwaAllowedUsers       = json_decode(Option::get($moduleId, 'pwa_allowed_users',  '[]'), true) ?? [];

$ufFieldsDeal    = getPwaDealUfFields();
$ufFieldsCompany = pwaGetUfFields('CRM_COMPANY');
$ufFieldsContact = pwaGetUfFields('CRM_CONTACT');
$crmStages       = getPwaCrmStages();
$crmCategories   = getPwaCrmCategories();
$activeUsers     = pwaGetActiveUsers();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'tab_directions', 'TAB' => 'Направления',    'ICON' => ''],
    ['DIV' => 'tab_fields',     'TAB' => 'Поля карточек',  'ICON' => ''],
    ['DIV' => 'tab_access',     'TAB' => 'Доступ',         'ICON' => ''],
]);

// ── Хелпер для HTML <select> (только для статичных табов) ─────────────────
function pwaSel(string $name, array $options, $selected, bool $multiple = false): string
{
    $mult = $multiple ? ' multiple size="8"' : '';
    $nm   = $multiple ? $name . '[]' : $name;
    $html = "<select name=\"$nm\" id=\"$name\"$mult>";
    if (!$multiple) {
        $html .= '<option value=""></option>';
    }
    foreach ($options as $val => $label) {
        $sel   = $multiple
            ? (in_array($val, (array)$selected) ? ' selected' : '')
            : ($val === $selected ? ' selected' : '');
        $html .= "<option value=\"" . htmlspecialchars($val) . "\"$sel>" . htmlspecialchars($label) . '</option>';
    }
    return $html . '</select>';
}
?>
<style>
.pwa-dir-card{border:1px solid #d0d0d0;border-radius:4px;margin-bottom:16px;background:#fafafa;}
.pwa-dir-head{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#eee;border-radius:4px 4px 0 0;cursor:pointer;}
.pwa-dir-head h3{margin:0;flex:1;font-size:14px;}
.pwa-dir-body{padding:12px 16px;}
.pwa-dir-body table{width:100%;border-collapse:collapse;}
.pwa-dir-body td{padding:4px 6px;vertical-align:top;}
.pwa-dir-body td:first-child{width:200px;font-weight:bold;white-space:nowrap;}
.pwa-dir-body select[multiple]{width:100%;min-height:100px;}
.pwa-dir-body select:not([multiple]){width:100%;max-width:400px;}
.pwa-dir-body input[type=text]{width:100%;max-width:400px;padding:3px 6px;box-sizing:border-box;}
.pwa-dir-badge{font-size:11px;color:#888;margin-left:6px;}
.pwa-add-dir{margin:8px 0 16px;}
/* Поиск по спискам */
.pwa-sel-wrap{display:flex;flex-direction:column;gap:3px;width:100%;}
.pwa-sel-search{padding:4px 7px;border:1px solid #bbb;border-radius:3px;font-size:13px;box-sizing:border-box;width:100%;max-width:400px;}
.pwa-sel-search:focus{outline:none;border-color:#7095d3;}
</style>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= $moduleId ?>&lang=<?= LANGUAGE_ID ?>">
<?php $tabControl->Begin(); ?>

<?php
// ─── ТАБ: Направления ──────────────────────────────────────────────────────
$tabControl->BeginNextTab();
?>
<tr><td colspan="2">
<input type="hidden" name="pwa_directions_json" id="pwa_directions_json" value="">

<div style="padding:8px 0 4px">
    <p style="color:#888;margin:0 0 8px">
        Каждое направление — отдельная группа сделок с собственными воронкой, стадиями, полями и доступами.<br>
        <b>Пустой список сотрудников = направление видят все.</b>
    </p>
    <button type="button" class="pwa-add-dir" id="js-add-dir" style="padding:6px 14px;">+ Добавить направление</button>
</div>

<div id="js-directions-list"></div>

<?php
// Передаём данные в JS
$jsData = [
    'directions' => $currentDirections,
    'stages'     => $crmStages,
    'categories' => $crmCategories,
    'ufFields'   => array_map(null, array_keys($ufFieldsDeal), array_values($ufFieldsDeal)),
    'users'      => array_map(fn($id, $name) => ['id' => $id, 'name' => $name], array_keys($activeUsers), array_values($activeUsers)),
];
// Конвертируем ufFields в [{id,name}]
$jsData['ufFields'] = [];
foreach ($ufFieldsDeal as $code => $label) {
    $jsData['ufFields'][] = ['id' => $code, 'name' => $label];
}
?>
<script>
(function() {
    const DATA = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE) ?>;
    let directions = DATA.directions.length ? DATA.directions : [];
    const stages     = DATA.stages;
    const categories = DATA.categories;
    const ufFields   = DATA.ufFields;
    const users      = DATA.users;

    const ICONS = ['briefcase','wrench','star','truck','phone','map','settings','users'];

    function uid() {
        return 'dir_' + Date.now() + '_' + Math.floor(Math.random()*1000);
    }

    function newDir() {
        return {
            id: uid(), name: 'Новое направление', icon: 'briefcase',
            completion_type: 'sales',
            pipelines: [], stages_today: [], stages_tomorrow: [],
            lat_field: '', lng_field: '', comment_field: '', visit_date_field: '',
            deal_fields: [], allowed_users: [],
            result_statuses: []
        };
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Поиск по select ────────────────────────────────────────────────────
    // Оборачивает select в контейнер с текстовым поиском
    function wrapWithSearch(selectHtml, inputId, placeholder) {
        placeholder = placeholder || 'Поиск…';
        return `<div class="pwa-sel-wrap">
            <input type="text" id="${inputId}_search" class="pwa-sel-search" placeholder="${placeholder}" autocomplete="off"
                oninput="filterSelect(this, document.getElementById('${inputId}'))">
            ${selectHtml}
        </div>`;
    }

    // ── Построение <select> с необязательным поиском ───────────────────────
    function buildSelect(id, items, selected, multiple, valKey, labelKey, withSearch) {
        valKey   = valKey   || 'id';
        labelKey = labelKey || 'name';
        const mult = multiple ? ' multiple' : '';
        const size = multiple ? ' size="8"' : '';
        let html = `<select id="${id}" style="width:100%;min-width:200px"${mult}${size}>`;
        if (!multiple) html += '<option value=""></option>';
        for (const item of items) {
            const v   = item[valKey];
            const lbl = item[labelKey] ?? item.label ?? v;
            const sel = multiple
                ? (Array.isArray(selected) && (selected.includes(v) || selected.map(String).includes(String(v))))
                : (String(selected) === String(v));
            html += `<option value="${escHtml(String(v))}"${sel?' selected':''}>${escHtml(lbl)}</option>`;
        }
        html += '</select>';
        return withSearch ? wrapWithSearch(html, id, 'Поиск…') : html;
    }

    // ── Построение <select> со стадиями (с фильтром по category_id) ────────
    function buildStageSelectFiltered(id, selectedStages, multiple, filterCatIds) {
        // filterCatIds: null/[] = показать все, иначе — только из этих категорий
        const filtered = (filterCatIds && filterCatIds.length)
            ? stages.filter(s => filterCatIds.includes(s.category_id))
            : stages;

        const mult = multiple ? ' multiple' : '';
        const size = multiple ? ' size="10"' : '';
        let html = `<select id="${id}" style="width:100%;min-width:240px"${mult}${size}>`;
        if (!multiple) html += '<option value=""></option>';
        let lastCat = null;
        for (const s of filtered) {
            if (s.category_id !== lastCat) {
                if (lastCat !== null) html += '</optgroup>';
                const cat = categories.find(c => c.id === s.category_id);
                html += `<optgroup label="${escHtml(cat ? cat.name : 'Воронка ' + s.category_id)}">`;
                lastCat = s.category_id;
            }
            const sel = multiple
                ? (Array.isArray(selectedStages) && selectedStages.includes(s.id))
                : (String(selectedStages) === s.id);
            html += `<option value="${escHtml(s.id)}"${sel?' selected':''}>${escHtml(s.name)}</option>`;
        }
        if (lastCat !== null) html += '</optgroup>';
        html += '</select>';
        return wrapWithSearch(html, id, 'Поиск стадии…');
    }

    // ── Рендер строки статуса завершения ──────────────────────────────────
    function renderStatus(dirId, st, isService) {
        const sid = st.id;
        const stageHtml = buildStageSelectFiltered('st_stage_'+sid, st.stage||'', false, null);
        const photoHtml = isService
            ? `<tr><td style="padding-left:16px;font-size:12px;color:#888">Фото-поля:</td><td colspan="4">${buildSelect('st_pf_'+sid, ufFields, st.photo_fields||[], true, 'id', 'name', true)}<br><small>Куда записывать фото (Ctrl+клик)</small></td></tr>`
            : '';
        const successHtml = `<tr><td style="padding-left:16px;font-size:12px;color:#888"></td><td colspan="4"><label><input type="checkbox" class="st-success"${st.is_successful?' checked':''}> Успешный визит (требует заполнение ИНФОПОВОДА)</label></td></tr>`;
        return `<div class="rs-row" data-sid="${sid}" style="border:1px solid #ddd;border-radius:3px;padding:8px;margin-bottom:6px;background:#fff">
          <table style="width:100%;border-collapse:collapse"><tr>
            <td style="width:24px"><button type="button" onclick="removeStatus('${dirId}','${sid}')" style="color:red;background:none;border:none;cursor:pointer;font-size:14px;padding:0">✕</button></td>
            <td style="width:180px;padding:0 4px"><input type="text" class="st-name" placeholder="Название" value="${escHtml(st.name||'')}" style="width:100%;padding:3px 5px"></td>
            <td style="width:36px;padding:0 4px"><input type="color" class="st-color" value="${st.color||'#0066ff'}" title="Цвет" style="width:32px;height:28px;padding:1px;border:1px solid #ccc;border-radius:3px;cursor:pointer"></td>
            <td style="padding:0 4px;font-size:12px;color:#666">&nbsp;Стадия:</td>
            <td>${stageHtml}</td>
          </tr>${photoHtml}${successHtml}</table>
        </div>`;
    }

    // ── Получить выбранные pipeline id из DOM карточки ────────────────────
    function getSelectedPipelines(card) {
        const sel = card.querySelector('[id^="cat_"]');
        if (!sel) return [];
        return [...sel.selectedOptions].map(o => parseInt(o.value, 10)).filter(n => !isNaN(n));
    }

    // ── Перестроить stageSelect при смене воронки ─────────────────────────
    window.onPipelineChange = function(sel, dirId) {
        const card = document.querySelector(`.pwa-dir-card[data-id="${dirId}"]`);
        if (!card) return;
        const chosen = [...sel.selectedOptions].map(o => parseInt(o.value, 10)).filter(n => !isNaN(n));

        // Запоминаем текущие выбранные значения стадий
        const todayEl    = card.querySelector('#today_'+dirId);
        const tomorrowEl = card.querySelector('#tomorrow_'+dirId);
        const curToday    = todayEl    ? [...todayEl.selectedOptions].map(o => o.value)    : [];
        const curTomorrow = tomorrowEl ? [...tomorrowEl.selectedOptions].map(o => o.value) : [];

        // Перестраиваем
        const todayWrap    = todayEl    ? todayEl.closest('.pwa-sel-wrap')    : null;
        const tomorrowWrap = tomorrowEl ? tomorrowEl.closest('.pwa-sel-wrap') : null;

        const tmp = document.createElement('div');

        if (todayWrap) {
            tmp.innerHTML = buildStageSelectFiltered('today_'+dirId, curToday, true, chosen);
            todayWrap.replaceWith(tmp.firstElementChild);
        }
        if (tomorrowWrap) {
            tmp.innerHTML = buildStageSelectFiltered('tomorrow_'+dirId, curTomorrow, true, chosen);
            tomorrowWrap.replaceWith(tmp.firstElementChild);
        }
    };

    // ── Live-поиск по <select> ─────────────────────────────────────────────
    window.filterSelect = function(input, selectEl) {
        if (!selectEl) return;
        const q = input.value.toLowerCase().trim();
        for (const opt of selectEl.options) {
            opt.style.display = (!q || opt.text.toLowerCase().includes(q)) ? '' : 'none';
        }
    };

    // ── Рендер одной карточки направления ─────────────────────────────────
    function renderCard(dir) {
        const isService = (dir.completion_type||'sales') === 'service';
        const statuses  = dir.result_statuses || [];

        const chosenPipelines = (dir.pipelines||[]).map(Number);

        const catsHtml     = buildSelect('cat_'+dir.id, categories, chosenPipelines, true, 'id', 'name', true);
        // Вставляем onchange через post-processing (dataset approach проще)
        const catsWrapped  = catsHtml.replace(
            `id="cat_${dir.id}"`,
            `id="cat_${dir.id}" onchange="onPipelineChange(this,'${dir.id}')"`
        );

        const todayHtml    = buildStageSelectFiltered('today_'+dir.id,    dir.stages_today    || [], true, chosenPipelines);
        const tomorrowHtml = buildStageSelectFiltered('tomorrow_'+dir.id, dir.stages_tomorrow || [], true, chosenPipelines);

        const ufHtml    = buildSelect('uf_'+dir.id, ufFields, dir.deal_fields    || [], true, 'id', 'name', true);
        const latHtml   = buildSelect('lat_'+dir.id, ufFields, dir.lat_field    || '', false, 'id', 'name', true);
        const lngHtml   = buildSelect('lng_'+dir.id, ufFields, dir.lng_field    || '', false, 'id', 'name', true);
        const comHtml   = buildSelect('com_'+dir.id, ufFields, dir.comment_field|| '', false, 'id', 'name', true);
        const vdHtml    = buildSelect('vd_'+dir.id,  ufFields, dir.visit_date_field||'', false, 'id', 'name', true);
        const usersHtml = buildSelect('usr_'+dir.id, users, (dir.allowed_users||[]).map(Number), true, 'id', 'name', true);

        const statusesHtml = statuses.map(s => renderStatus(dir.id, s, isService)).join('');

        return `<div class="pwa-dir-card" data-id="${dir.id}">
          <div class="pwa-dir-head" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
            <h3>▼ <span class="dir-title">${escHtml(dir.name)}</span></h3>
            <button type="button" style="color:red;background:none;border:none;cursor:pointer;font-size:16px" onclick="event.stopPropagation();removeDir('${dir.id}')">✕ Удалить</button>
          </div>
          <div class="pwa-dir-body"><table>
            <tr><td>Название:</td><td><input type="text" class="dir-name" value="${escHtml(dir.name)}" oninput="this.closest('.pwa-dir-card').querySelector('.dir-title').textContent=this.value"></td></tr>
            <tr><td>Иконка:</td><td><select class="dir-icon">${ICONS.map(i=>`<option value="${i}"${dir.icon===i?' selected':''}>${i}</option>`).join('')}</select></td></tr>
            <tr><td>Тип завершения:</td><td>
              <select class="dir-ctype" onchange="onCtypeChange(this,'${dir.id}')" style="width:100%;max-width:300px">
                <option value="sales"${!isService?' selected':''}>Продажи (статус → стадия)</option>
                <option value="service"${isService?' selected':''}>Сервис (статус + фото в поля)</option>
              </select>
              <br><small>Определяет экран завершения в PWA</small>
            </td></tr>
            <tr><td>Воронки CRM:<br><small>Пусто=все. Стадии ниже фильтруются по выбранным воронкам.</small></td><td>${catsWrapped}</td></tr>
            <tr><td colspan="2"><hr style="margin:6px 0"></td></tr>
            <tr><td>Стадии «Сегодня»:</td><td>${todayHtml}</td></tr>
            <tr><td>Стадии «Завтра»:</td><td>${tomorrowHtml}</td></tr>
            <tr><td colspan="2"><hr style="margin:6px 0"><b>Поля сделки</b></td></tr>
            <tr><td>Доп. поля:</td><td>${ufHtml}<br><small>Ctrl+клик</small></td></tr>
            <tr><td>Поле широты:</td><td>${latHtml}</td></tr>
            <tr><td>Поле долготы:</td><td>${lngHtml}</td></tr>
            <tr><td>Поле комментария:</td><td>${comHtml}</td></tr>
            <tr><td>Поле даты визита:</td><td>${vdHtml}</td></tr>
            <tr><td colspan="2"><hr style="margin:6px 0">
              <b>Статусы завершения</b>
              <span style="font-weight:normal;color:#888;font-size:12px"> — видны сотруднику в PWA. Пустая стадия = не менять.</span>
            </td></tr>
            <tr><td colspan="2">
              <div id="statuses_${dir.id}">${statusesHtml}</div>
              <button type="button" onclick="addStatus('${dir.id}')" style="margin-top:4px;padding:4px 12px;cursor:pointer">➕ Добавить статус</button>
            </td></tr>
            <tr><td colspan="2"><hr style="margin:6px 0"><b>Доступ</b></td></tr>
            <tr><td>Сотрудники:<br><small>Пусто=все</small></td><td>${usersHtml}<br><small>Ctrl+клик</small></td></tr>
          </table></div>
        </div>`;
    }

    // ── Сбор данных из DOM карточки ────────────────────────────────────────
    function collectDir(card) {
        const id = card.dataset.id;
        const getMulti  = sel => [...card.querySelector(sel).selectedOptions].map(o => o.value);
        const getSingle = sel => { const el = card.querySelector(sel); return el ? el.value : ''; };
        const isService = getSingle('.dir-ctype') === 'service';
        const resultStatuses = [...card.querySelectorAll('.rs-row')].map(row => {
            const sid = row.dataset.sid;
            const st = {
                id:    sid,
                name:  (row.querySelector('.st-name')?.value || '').trim(),
                color: row.querySelector('.st-color')?.value || '#0066ff',
                stage: row.querySelector('select[id^="st_stage_"]')?.value || '',
                is_successful: row.querySelector('.st-success')?.checked || false,
            };
            const pfEl = row.querySelector('select[id^="st_pf_"]');
            st.photo_fields = (isService && pfEl) ? [...pfEl.selectedOptions].map(o => o.value) : [];
            return st;
        }).filter(s => s.name);
        return {
            id,
            name:             getSingle('.dir-name').trim() || 'Без названия',
            icon:             getSingle('.dir-icon'),
            completion_type:  getSingle('.dir-ctype') || 'sales',
            pipelines:        getMulti('#cat_'+id).map(Number),
            stages_today:     getMulti('#today_'+id),
            stages_tomorrow:  getMulti('#tomorrow_'+id),
            deal_fields:      getMulti('#uf_'+id),
            lat_field:        getSingle('#lat_'+id),
            lng_field:        getSingle('#lng_'+id),
            comment_field:    getSingle('#com_'+id),
            visit_date_field: getSingle('#vd_'+id),
            allowed_users:    getMulti('#usr_'+id).map(Number),
            result_statuses:  resultStatuses,
        };
    }

    // ── Рендер всего списка ────────────────────────────────────────────────
    function renderAll() {
        const list = document.getElementById('js-directions-list');
        list.innerHTML = directions.map(renderCard).join('');
    }

    window.removeDir = function(id) {
        if (!confirm('Удалить направление?')) return;
        directions = directions.filter(d => d.id !== id);
        renderAll();
    };
    window.addStatus = function(dirId) {
        const card = document.querySelector(`.pwa-dir-card[data-id="${dirId}"]`);
        if (!card) return;
        const isService = card.querySelector('.dir-ctype')?.value === 'service';
        const sid = 'rs_' + Date.now();
        const tmp = document.createElement('div');
        tmp.innerHTML = renderStatus(dirId, {id:sid,name:'',color:'#0066ff',stage:'',photo_fields:[],is_successful:false}, isService);
        card.querySelector('#statuses_' + dirId).appendChild(tmp.firstElementChild);
    };
    window.removeStatus = function(dirId, sid) {
        document.querySelector(`.rs-row[data-sid="${sid}"]`)?.remove();
    };
    window.onCtypeChange = function(sel, dirId) {
        const card = document.querySelector(`.pwa-dir-card[data-id="${dirId}"]`);
        const isService = sel.value === 'service';
        const container = card.querySelector('#statuses_' + dirId);
        const current = [...container.querySelectorAll('.rs-row')].map(row => ({
            id: row.dataset.sid,
            name:  row.querySelector('.st-name')?.value || '',
            color: row.querySelector('.st-color')?.value || '#0066ff',
            stage: row.querySelector('select[id^="st_stage_"]')?.value || '',
            is_successful: row.querySelector('.st-success')?.checked || false,
            photo_fields: [],
        }));
        container.innerHTML = current.map(s => renderStatus(dirId, s, isService)).join('');
    };

    document.getElementById('js-add-dir').addEventListener('click', function() {
        directions.push(newDir());
        renderAll();
    });

    document.querySelector('form').addEventListener('submit', function() {
        const cards = document.querySelectorAll('#js-directions-list .pwa-dir-card');
        const result = [];
        cards.forEach(card => result.push(collectDir(card)));
        document.getElementById('pwa_directions_json').value = JSON.stringify(result);
    });

    renderAll();
})();
</script>
</td></tr>

<?php
// ─── ТАБ: Поля карточек (Компания / Контакт — глобальные) ─────────────────
$tabControl->BeginNextTab();
?>
<tr>
    <td><label for="company_fields">Поля Компании</label></td>
    <td>
        <div class="pwa-sel-wrap">
            <input type="text" class="pwa-sel-search" placeholder="Поиск поля…" autocomplete="off"
                oninput="filterSelect(this, document.getElementById('company_fields'))">
            <select name="company_fields[]" id="company_fields" multiple size="10" style="width:100%;max-width:500px">
                <?php foreach ($ufFieldsCompany as $val => $lbl): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= in_array($val, $companyFieldsSelected) ? ' selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <br><small>Поля компании, видимые в карточке (Ctrl+клик — множественный выбор)</small>
    </td>
</tr>
<tr>
    <td><label for="contact_fields">Поля Контакта</label></td>
    <td>
        <div class="pwa-sel-wrap">
            <input type="text" class="pwa-sel-search" placeholder="Поиск поля…" autocomplete="off"
                oninput="filterSelect(this, document.getElementById('contact_fields'))">
            <select name="contact_fields[]" id="contact_fields" multiple size="10" style="width:100%;max-width:500px">
                <?php foreach ($ufFieldsContact as $val => $lbl): ?>
                    <option value="<?= htmlspecialchars($val) ?>"<?= in_array($val, $contactFieldsSelected) ? ' selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <br><small>Поля контакта, видимые в карточке (Ctrl+клик — множественный выбор)</small>
    </td>
</tr>

<?php
// ─── ТАБ: Доступ ───────────────────────────────────────────────────────────
$tabControl->BeginNextTab();
?>
<tr>
    <td colspan="2"><p style="color:#888">
        <b>Глобальный доступ к PWA</b><br>
        Пустой список — приложение доступно всем активным сотрудникам.<br>
        Доступ к конкретным направлениям настраивается в каждом направлении отдельно.
    </p></td>
</tr>
<tr>
    <td><label for="pwa_allowed_users">Сотрудники с доступом</label></td>
    <td>
        <div class="pwa-sel-wrap">
            <input type="text" class="pwa-sel-search" placeholder="Поиск сотрудника…" autocomplete="off"
                oninput="filterSelect(this, document.getElementById('pwa_allowed_users'))">
            <?= pwaSel('pwa_allowed_users', $activeUsers, $pwaAllowedUsers, true) ?>
        </div>
        <br><small>Пусто = доступ для всех. Ctrl+клик — множественный выбор.</small>
    </td>
</tr>

<?php
$tabControl->Buttons([
    'btnSave'  => true,
    'btnApply' => true,
    'btnReset' => true,
]);
$tabControl->End();
?>
<?= bitrix_sessid_post() ?>
</form>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
