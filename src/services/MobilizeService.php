<?php
/**
 * Mobilize plugin for Craft CMS 3.x
 *
 * Mobilize America API Integration for Events
 *
 * @link      http://www.github.com/mwcbrent
 * @copyright Copyright (c) 2019 Brent Sanders
 */

namespace mwcbrent\mobilize\services;

use GuzzleHttp\Client;
use mwcbrent\mobilize\Mobilize;

use Craft;
use craft\base\Component;

/**
 * MobilizeService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Brent Sanders
 * @package   Mobilize
 * @since     1
 */
class MobilizeService extends Component
{

    protected $apiKey;
    protected $cacheDuration;
    protected $organizationID;
    protected $client;

    public function __construct()
    {
        $settings = Mobilize::$plugin->getSettings();
        if ( ! $settings->apiKey) {
            throw new Exception("You must add a Mobilize America API Key");
        }
        if ( ! $settings->organizationID) {
            throw new Exception("You must add a Mobilize America Organization ID");
        }
        $this->apiKey         = $settings->apiKey;
        $this->organizationID = $settings->organizationID;
        $this->client         = new Client([
            'base_uri' => 'https://events.mobilizeamerica.io/api/v1/',
            'timeout'  => 20.0,
        ]);
    }

    public function eventList($options)
    {
        $args = [
            'organization_id' => $this->organizationID,
            'timeslot_start'  => 'gte_' . time()
        ];
        if (array_key_exists('limit', $options)) {
            $args['per_page'] = $options['limit'];
        } else {
            $args['per_page'] = 4;
        }

        if ($options['startDate'] !== null and $options['startDate'] !== "") {
            $args['timeslot_start'] = 'gte_' . strtotime($options['startDate']);
        }

        if ($options['endDate'] !== null and $options['endDate'] !== "") {
            $args['timeslot_end'] = 'lte_' . strtotime($options['endDate']);
        }

        if (array_key_exists('zipcode', $options)) {
            $args['zipcode'] = $options['zipcode'];
        }

        $cacheKey = 'mobilize-allEvents-' . implode('-', $args);
        $events   = Craft::$app->cache->get($cacheKey);
        if ( ! $events) {
            $response = $this->client->request('GET', 'events', [
                'query' => $args,
            ]);
            if ($response->getStatusCode() != 200) {
                throw new Exception("Error: " . $response->getMessage());
            }
            $event_json = json_decode($response->getBody()->getContents());
            $events     = $event_json->data;
            Craft::$app->cache->set($cacheKey, $events, 3600);
        }

        return $events;
    }
}
