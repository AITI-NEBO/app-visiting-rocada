<?php
namespace Nebo\Map\Controller;

use \Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Engine\Controller;
use \Nebo\Map\Api\Objects as ObjectsApi;

class Objects extends Controller
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
         'get' => [
               '+prefilters' => [],
               '-prefilters' => [
                  ActionFilter\Authentication::class,
                  ActionFilter\Csrf::class,
               ]
         ],
         'visits' => [
               '+prefilters' => [],
               '-prefilters' => [
                  ActionFilter\Authentication::class,
                  ActionFilter\Csrf::class,
               ]
         ],
      ];
   }

   public static function visitsAction()
   {
      header("Access-Control-Allow-Origin: *");
      $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

      return ObjectsApi::visits($request['sessid']);
   }

   public static function getAction()
   {
      header("Access-Control-Allow-Origin: *");
      $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

      return ObjectsApi::get($request['sessid'], json_decode($request['filters'], 1));
   }

}