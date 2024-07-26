<?php

namespace codingmammoth\craftsemontowebsitemonitor\controllers;

use craft\web\Controller;

class EndpointController extends Controller
{
    public const ALLOW_ANONYMOUS_LIVE = 1;
    public const ALLOW_ANONYMOUS_OFFLINE = 2;

    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    // index.php gets executed at the /health endpoint, and will respond with JSON.
    public function actionHealth()
    {
        require_once __DIR__ . "/../ServerHealthFramework/index.php";

        exit();
    }
}
