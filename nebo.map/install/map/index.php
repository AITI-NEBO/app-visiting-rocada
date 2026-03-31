<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Карта объектов");


use Bitrix\Crm\UI\NavigationBarPanel;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Filter\Settings;


CModule::includeModule('nebo.map');

// js/css
$APPLICATION->SetAdditionalCSS('/bitrix/themes/.default/bitrix24/crm-entity-show.css');
$bodyClass = $APPLICATION->GetPageProperty('BodyClass');
$APPLICATION->SetPageProperty('BodyClass', ($bodyClass ? $bodyClass . ' ' : '') . 'no-paddings grid-mode pagetitle-toolbar-field-view crm-toolbar');
$asset = Bitrix\Main\Page\Asset::getInstance();
$asset->addJs('/bitrix/js/crm/common.js');

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/bitrix/crm.deal.menu/component.php');
Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/bitrix/crm.deal.list/templates/.default/template.php');

\Bitrix\Crm\Settings\Crm::markAsInitiated();

// if not isset

$canUseAllCategories = ($arResult['CAN_USE_ALL_CATEGORIES'] ?? false);
$defaultCategoryId = ($canUseAllCategories ? -1 : 0);

if (empty($categoryID)) {
    $categoryID = preg_replace('#/crm/deal/map/category/(\d+)?.*#', '$1', $_SERVER['REQUEST_URI']);
}



$arResult['PATH_TO_DEAL_EDIT'] = ($arResult['PATH_TO_DEAL_EDIT'] ?? '');
$arResult['PATH_TO_DEAL_LIST'] = ($arResult['PATH_TO_DEAL_LIST'] ?? '');
$arResult['PATH_TO_DEAL_CATEGORY'] = ($arResult['PATH_TO_DEAL_CATEGORY'] ?? '');
$arResult['PATH_TO_DEAL_MAP'] = ($arResult['PATH_TO_DEAL_MAP'] ?? '');
$arResult['PATH_TO_DEAL_ACTIVITY'] = ($arResult['PATH_TO_DEAL_ACTIVITY'] ?? '');
// $arResult['PATH_TO_DEAL_KANBAN'] = ($arResult['PATH_TO_DEAL_KANBAN'] ?? '');

$arResult['PATH_TO_DEAL_KANBANCATEGORY'] = ($arResult['PATH_TO_DEAL_KANBANCATEGORY'] ?? '');
$arResult['PATH_TO_DEAL_CALENDARCATEGORY'] = ($arResult['PATH_TO_DEAL_CALENDARCATEGORY'] ?? '');
$arResult['PATH_TO_DEAL_IMPORT'] = ($arResult['PATH_TO_DEAL_IMPORT'] ?? '');

// csv and excel delegate to list
$context = \Bitrix\Main\Application::getInstance()->getContext();
$request = $context->getRequest();

// main menu
$APPLICATION->IncludeComponent(
    'bitrix:crm.control_panel',
    '',
    array(
        'ID' => 'DEAL_LIST',
        'ACTIVE_ITEM_ID' => 'DEAL',
        'PATH_TO_COMPANY_LIST' => isset($arResult['PATH_TO_COMPANY_LIST']) ? $arResult['PATH_TO_COMPANY_LIST'] : '',
        'PATH_TO_COMPANY_EDIT' => isset($arResult['PATH_TO_COMPANY_EDIT']) ? $arResult['PATH_TO_COMPANY_EDIT'] : '',
        'PATH_TO_CONTACT_LIST' => isset($arResult['PATH_TO_CONTACT_LIST']) ? $arResult['PATH_TO_CONTACT_LIST'] : '',
        'PATH_TO_CONTACT_EDIT' => isset($arResult['PATH_TO_CONTACT_EDIT']) ? $arResult['PATH_TO_CONTACT_EDIT'] : '',
        'PATH_TO_DEAL_LIST' => isset($arResult['PATH_TO_DEAL_LIST']) ? $arResult['PATH_TO_DEAL_LIST'] : '',
        'PATH_TO_DEAL_EDIT' => isset($arResult['PATH_TO_DEAL_EDIT']) ? $arResult['PATH_TO_DEAL_EDIT'] : '',
        'PATH_TO_DEAL_CATEGORY' => isset($arResult['PATH_TO_DEAL_CATEGORY']) ? $arResult['PATH_TO_DEAL_CATEGORY'] : '',
        'PATH_TO_DEAL_MAP' => isset($arResult['PATH_TO_DEAL_MAP']) ? $arResult['PATH_TO_DEAL_MAP'] : '',
        'PATH_TO_LEAD_LIST' => isset($arResult['PATH_TO_LEAD_LIST']) ? $arResult['PATH_TO_LEAD_LIST'] : '',
        'PATH_TO_LEAD_EDIT' => isset($arResult['PATH_TO_LEAD_EDIT']) ? $arResult['PATH_TO_LEAD_EDIT'] : '',
        'PATH_TO_QUOTE_LIST' => isset($arResult['PATH_TO_QUOTE_LIST']) ? $arResult['PATH_TO_QUOTE_LIST'] : '',
        'PATH_TO_QUOTE_EDIT' => isset($arResult['PATH_TO_QUOTE_EDIT']) ? $arResult['PATH_TO_QUOTE_EDIT'] : '',
        'PATH_TO_INVOICE_LIST' => isset($arResult['PATH_TO_INVOICE_LIST']) ? $arResult['PATH_TO_INVOICE_LIST'] : '',
        'PATH_TO_INVOICE_EDIT' => isset($arResult['PATH_TO_INVOICE_EDIT']) ? $arResult['PATH_TO_INVOICE_EDIT'] : '',
        'PATH_TO_REPORT_LIST' => isset($arResult['PATH_TO_REPORT_LIST']) ? $arResult['PATH_TO_REPORT_LIST'] : '',
        'PATH_TO_DEAL_FUNNEL' => isset($arResult['PATH_TO_DEAL_FUNNEL']) ? $arResult['PATH_TO_DEAL_FUNNEL'] : '',
        'PATH_TO_EVENT_LIST' => isset($arResult['PATH_TO_EVENT_LIST']) ? $arResult['PATH_TO_EVENT_LIST'] : '',
        'PATH_TO_PRODUCT_LIST' => isset($arResult['PATH_TO_PRODUCT_LIST']) ? $arResult['PATH_TO_PRODUCT_LIST'] : '',
        //'COUNTER_EXTRAS' => array('DEAL_CATEGORY_ID' => $categoryID)
    ),
    $component
);



// check rights
if (!\CCrmPerms::IsAccessEnabled()) {
    return false;
}

// check accessable
if (!Bitrix\Crm\Integration\Bitrix24Manager::isAccessEnabled(\CCrmOwnerType::Deal)) {
    $APPLICATION->IncludeComponent('bitrix:bitrix24.business.tools.info', '', array());
}
else {
    $entityType = \CCrmOwnerType::DealName;
    $isBitrix24Template = SITE_TEMPLATE_ID === 'bitrix24';

    // counters
    $APPLICATION->IncludeComponent(
        'bitrix:crm.entity.counter.panel',
        '',
        array(
            'ENTITY_TYPE_NAME' => $entityType,
            'EXTRAS' => array('DEAL_CATEGORY_ID' => $categoryID),
            'PATH_TO_ENTITY_LIST' =>
                $categoryID < 1
                    ? $arResult['PATH_TO_DEAL_KANBAN']
                    : CComponentEngine::makePathFromTemplate(
                    $arResult['PATH_TO_DEAL_KANBANCATEGORY'],
                    array('category_id' => $categoryID)
                )
        )
    );


    $userPermissions = CCrmPerms::GetCurrentUserPermissions();
    $map = array_fill_keys(CCrmDeal::GetPermittedToReadCategoryIDs($userPermissions), true);
    if ($canUseAllCategories) {
        $map['-1'] = true;
    }

    $APPLICATION->IncludeComponent(
        'bitrix:crm.deal_category.panel',
        $isBitrix24Template ? 'tiny' : '',
        [
            'PATH_TO_DEAL_LIST' => ($arResult['KANBAN_VIEW_MODE'] === \Bitrix\Crm\Kanban\ViewMode::MODE_ACTIVITIES ? $arResult['PATH_TO_DEAL_ACTIVITY'] : $arResult['PATH_TO_DEAL_KANBAN']),
            'PATH_TO_DEAL_CATEGORY' => '/crm/deal/map/category/#category_id#/',
            'PATH_TO_DEAL_EDIT' => $arResult['PATH_TO_DEAL_EDIT'],
            'PATH_TO_DEAL_MAP' => $arResult['PATH_TO_DEAL_KANBANCATEGORY'],
            'PATH_TO_DEAL_MAP_LIST' => $arResult['PATH_TO_DEAL_MAP_LIST'],
            'PATH_TO_DEAL_MAP_EDIT' => $arResult['PATH_TO_DEAL_MAP_EDIT'],
            'ENABLE_CATEGORY_ALL' => ($arResult['KANBAN_VIEW_MODE'] === \Bitrix\Crm\Kanban\ViewMode::MODE_ACTIVITIES ? 'Y' : 'N'),
            'CATEGORY_ID' => $categoryID,
        ],
        $component
    );



    \Bitrix\Crm\Kanban\Helper::setCategoryId($categoryID);

    $arResult['NAVIGATION_CONTEXT_ID'] = 'DEAL';

    $allObj = (new NavigationBarPanel(CCrmOwnerType::Deal, $categoryID))
        ->setItems([
            NavigationBarPanel::ID_KANBAN,
            NavigationBarPanel::ID_LIST,
            NavigationBarPanel::ID_ACTIVITY,
            NavigationBarPanel::ID_CALENDAR,
            NavigationBarPanel::ID_AUTOMATION,
        ], 'map')
        ->setBinding($arResult['NAVIGATION_CONTEXT_ID'])
        ->get();

    $mapObject = new Bitrix\Main\Web\Uri('/crm/deal/map/category/' . $categoryID . '/');

    $APPLICATION->IncludeComponent(
        'bitrix:crm.kanban.filter',
        '',
        [
            'ENTITY_TYPE' => $entityType,
            'VIEW_MODE' => $arResult['KANBAN_VIEW_MODE'],
            'NAVIGATION_BAR' => $allObj
        ],
        $component,
        ['HIDE_ICONS' => true]
    );

    $entity = \Bitrix\Crm\Kanban\Entity::getInstance($entityType ?? '', $arResult['KANBAN_VIEW_MODE'] ?? \Bitrix\Crm\Kanban\ViewMode::MODE_STAGES);
    if (!$entity) {
        return false;
    }

    $entity->setCategoryId($categoryID);

    $filterParams = [
        'LIMITS' => null,
        //'SHOW_AUTOMATION_VIEW' => $showAutomationView,
    ];

    $searchRestriction = \Bitrix\Crm\Restriction\RestrictionManager::getSearchLimitRestriction();
    $entityTypeID = $entity->getTypeId();
    if ($searchRestriction->isExceeded($entityTypeID)) {
        $filterParams['LIMITS'] = $searchRestriction->prepareStubInfo(
            ['ENTITY_TYPE_ID' => $entityTypeID]
        );
    }

    $filter = $entity->getGridFilter();

    $filterParams['GRID_ID'] = $entity->getGridId();
    $filterParams['FILTER_ID'] = $entity->getGridId();
    $filterParams['FILTER'] = $filter;

    $settings = new \Bitrix\Crm\Filter\DealSettings([
        'ID' => $filterParams['FILTER_ID'],
        'flags' => \Bitrix\Crm\Filter\DealSettings::FLAG_ENABLE_CLIENT_FIELDS,
    ]);
    $additionalFilter['CATEGORY_ID'] = $categoryID;
    $filters = Nebo\Map\Api\Filters::filterFormat($filterParams['FILTER_ID'], $additionalFilter);
    \Bitrix\Crm\Service\Container::getInstance()->getLocalization()->loadMessages();

}

$url = 'domain='.$_SERVER['SERVER_NAME'].'/map/v1/get&sessid=' . $_COOKIE['PHPSESSID'] . '&filters='. urlencode(json_encode($filters));


?>

    <iframe src='https://app-module-map-six.vercel.app/?<?= $url ?>' id="mapObjs" style="width:100%; height: 100%;">
    </iframe>

    <style>
        .workarea-content-paddings {
            padding: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
        }
    </style>

    <script>
        addEventListener("DOMContentLoaded", (event) => {

            BX.addCustomEvent('BX.Main.Filter:apply', function(filterId, action, filterInstance) {
                BX.ajax.runAction('nebo:map.api.Filters.filterFormat', {
                    data: {
                        filterID: <?= json_encode($filterParams['FILTER_ID']) ?>,
                        additionalFilter: <?= json_encode($additionalFilter) ?>,
                    },
                }).then(function(response) {
                    var iframe = document.getElementById('mapObjs');
                    iframe.src = 'https://app-module-map-six.vercel.app/?domain=<?=$_SERVER['SERVER_NAME']?>/map/v1/get&sessid=<?= $_COOKIE['PHPSESSID'] ?>&filters=' + encodeURIComponent(JSON.stringify(response['data']));
                }, function(response) {
                    //сюда будут приходить все ответы, у которых status !== 'success'
                    var iframe = document.getElementById('mapObjs');
                    iframe.src = iframe.src; // просто обновляемся
                });
            });

            var mapElement = document.getElementById('ui-nav-panel-item-map');
            var elements = document.querySelectorAll('[id^="ui-nav-panel"]');

            // Добавить класс '-active' к элементу, если он найден
            if (mapElement) {
                // Пройти по каждому элементу
                elements.forEach(function(element) {
                    // Проверить, содержит ли элемент класс, оканчивающийся на "-active"
                    element.classList.forEach(function(className) {
                        if (className.endsWith('-active')) {
                            // Удалить этот класс
                            element.classList.remove(className);
                        }
                    });
                });
                mapElement.classList.add('--active');
            }
        })
    </script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>