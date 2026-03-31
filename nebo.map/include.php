<?php

$moduleId = strtolower(basename(__DIR__));

\Bitrix\Main\Loader::registerAutoLoadClasses(
    $moduleId,
    [
        // api
        'Nebo\Map\Api\Objects' => 'lib/api/Objects.php',
        'Nebo\Map\Api\Filters' => 'lib/api/Filters.php',

        // controllers
        'Nebo\Map\Controller\Objects' => 'lib/controller/Objects.php',
        'Nebo\Map\Controller\Filters' => 'lib/controller/Filters.php',

        'Nebo\Map\Events\Main' => 'lib/events/Main.php',

        'Nebo\Map\Install\Events' => 'lib/install/Events.php',
        'Nebo\Map\Install\Files' => 'lib/install/Files.php',


    ]
);

?>