<?php
namespace Nebo\Map\Api;

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Filter\Options;
use Bitrix\Main\UI\Filter\Settings;

class Filters
{
    public static function filterFormat($filterID, $additionalFilter = []) {
        $filterOptions = new Options($filterID);
        $filters = $filterOptions->getFilter();
        $filters['STATUSES.ENTITY_ID'] = ['DEAL_STAGE', "DEAL_STAGE_{$additionalFilter['CATEGORY_ID']}"];

        unset($filters['PRESET_ID']);
        unset($filters['FILTER_ID']);
        unset($filters['FILTER_APPLIED']);
        if (!$filters['FIND']) unset($filters['FIND']);
        else {
            $filters['SEARCH_CONTENT'] = $filters['FIND'];
            unset($filters['FIND']);
        }

        foreach ($filters as $k => $v)
        {
            if (preg_match('/%?[a-zA-Z_0-9]+_(numsel|datesel)/', $k)) {
                unset($filters[$k]);
                continue;
            }
            // Check if first key character is aplpha and key is not immutable
            if (is_array($arImmutableFilters) && (
                    preg_match('/^[a-zA-Z]/', $k) !== 1
                    || in_array($k, $arImmutableFilters, true))
            )
            {
                continue;
            }

            if (\Bitrix\Crm\Service\ParentFieldManager::isParentFieldName($k))
            {
                $filters[$k] = \Bitrix\Crm\Service\ParentFieldManager::transformEncodedFilterValueIntoInteger($k, $v);
                continue;
            }


            $arMatch = array();
            if($k === 'ORIGINATOR_ID')
            {
                // HACK: build filter by internal entities
                $filters['=ORIGINATOR_ID'] = $v !== '__INTERNAL' ? $v : null;
                unset($filters[$k]);
            }
            elseif (preg_match('/(.*)_from$/i'.BX_UTF_PCRE_MODIFIER, $k, $arMatch))
            {
                if ($arMatch[1] === 'ACTIVE_TIME_PERIOD')
                {
                    continue;
                }

                \Bitrix\Crm\UI\Filter\Range::prepareFrom($filters, $arMatch[1], $v);
            }
            elseif (preg_match('/(.*)_to$/i'.BX_UTF_PCRE_MODIFIER, $k, $arMatch))
            {
                if ($arMatch[1] === 'ACTIVE_TIME_PERIOD')
                {
                    continue;
                }

                if ($v != '' && ($arMatch[1] == 'DATE_CREATE' || $arMatch[1] == 'DATE_MODIFY') && !preg_match('/\d{1,2}:\d{1,2}(:\d{1,2})?$/'.BX_UTF_PCRE_MODIFIER, $v))
                {
                    $v = CCrmDateTimeHelper::SetMaxDayTime($v);
                }
                \Bitrix\Crm\UI\Filter\Range::prepareTo($filters, $arMatch[1], $v);
            }
            elseif (is_array($arResult['FILTER2LOGIC']) && in_array($k, $arResult['FILTER2LOGIC']) && $v !== false)
            {
                // Bugfix #26956 - skip empty values in logical filter
                $v = trim($v);
                if($v !== '')
                {
                    $filters['?'.$k] = $v;
                }
                unset($filters[$k]);
            }
            elseif ($k != 'ID' && $k != 'LOGIC' && $k != '__INNER_FILTER' && $k != '__JOINS' && $k != '__CONDITIONS' && mb_strpos($k, 'UF_') !== 0 && preg_match('/^[^\=\%\?\>\<]{1}/', $k) === 1  && $v !== false)
            {
                $filters['%'.$k] = $v;
                unset($filters[$k]);
            }

        }

        $filters = array_merge($filters ?? [], $additionalFilter ?? []);
        return $filters;
    }
}