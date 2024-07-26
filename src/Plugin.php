<?php

namespace codingmammoth\craftsemontowebsitemonitor;

use Craft;
use codingmammoth\craftsemontowebsitemonitor\models\Settings;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\Controller;
use yii\base\Event;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\events\RegisterCpNavItemsEvent;
use yii\web\Response;
use craft\web\View;

/**
 * Semonto Website Monitor plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Coding Mammoth <info@semonto.com>
 * @copyright Coding Mammoth
 * @license MIT
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;


    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::setAlias('@plugin', __DIR__);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['health'] = 'semonto-website-monitor/endpoint/health';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['settings/plugins/semonto-website-monitor/website-monitoring'] = 'semonto-website-monitor/plugin/website-monitoring';
                $event->rules['settings/plugins/semonto-website-monitor/server-monitoring'] = 'semonto-website-monitor/plugin/server-monitoring';
            }
        );

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'settings/plugins/semonto-website-monitor',
                    'label' => 'Semonto',
                    'icon' => '@plugin/icon-mask.svg'
                ];
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettingsResponse(): Response
    {
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
        Craft::$app->getView()->startJsBuffer();

        $this->view->registerAssetBundle(PluginBundle::class);

        $imageUrl = \Craft::$app->assetManager->getPublishedUrl(
            '@codingmammoth/craftsemontowebsitemonitor/resources',
            true,
            'semonto-dashboard.png'
        );

        $semontoLogoUrl = \Craft::$app->assetManager->getPublishedUrl(
            '@codingmammoth/craftsemontowebsitemonitor/resources',
            true,
            'semonto-logo.png'
        );


        $html = Craft::$app->getView()->renderPageTemplate('semonto-website-monitor/semonto-website-monitor', [
            'semontoDashboard' => $imageUrl,
            'semontoLogoUrl' => $semontoLogoUrl
        ]);

        $response = new Response();
        $response->content = $html;

        return $response;
    }
}
