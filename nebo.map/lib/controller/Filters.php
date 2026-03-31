<?php
namespace Nebo\Map\Controller;

use \Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Engine\Controller;
use \Nebo\Map\Api\Filters as FiltersApi;

class Filters extends Controller
{

   /**
    * Правила обработки действий в контроллере
    * @return array
    */
   public function configureActions()
   {
      // Получаем объект запроса
      $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

      return [
         'filterFormat' => [
               '+prefilters' => [],
               '-prefilters' => [
                  ActionFilter\Authentication::class,
                  ActionFilter\Csrf::class,
               ]
         ],
          'filterCategory' => [
              '+prefilters' => [],
              '-prefilters' => [
                  ActionFilter\Authentication::class,
                  ActionFilter\Csrf::class,
              ]
          ],
      ];
   }

   public static function filterFormatAction($filterID, $additionalFilter)
   {
      header("Access-Control-Allow-Origin: *");
      return FiltersApi::filterFormat($filterID, $additionalFilter);
   }

   public static function filterCategoryAction() {
       return \CUserOptions::GetOption('crm','current_deal_category');
   }

}