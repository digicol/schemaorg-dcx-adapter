<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\DcxSdk\DcxApiClient;
use Digicol\SchemaOrg\Sdk\AdapterInterface;
use Digicol\SchemaOrg\Sdk\PotentialSearchActionInterface;


class DcxAdapter implements AdapterInterface
{
    /** @var array */
    protected $params = [];

    /** @var DcxApiClient */
    protected $dcxApiClient;


    /**
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }


    /** @return array */
    public function getParams()
    {
        return $this->params;
    }


    /**
     * @return PotentialSearchActionInterface[]
     */
    public function getPotentialSearchActions()
    {
        $dcxApiClient = $this->newDcxApiClient();

        $httpStatus = $dcxApiClient->get(
            'channel/?q[_mode]=my_channels',
            [],
            $data
        );
        
        if (($httpStatus >= 300) || empty($data['entries'])) {
            return [];
        }

        $result = [];

        foreach ($data['entries'] as $channelData) {
            $channelId = basename($channelData['_id']);
            
            $result[$channelId] = new DcxPotentialSearchAction
            (
                $this,
                [
                    'name' => $channelData['properties']['_label'],
                    'description' => (! empty($channelData['properties']['remark']) ? $channelData['properties']['remark'] : ''),
                    'type' => 'channel',
                    'id' => $channelId
                ]
            );
        }

        return $result;
    }


    /**
     * @param string $uri sameAs identifying URL
     * @return DcxDocument
     */
    public function newThing($uri)
    {
        // ToDo: Check whether $uri begins with "document/" or some other supported object type
        return new DcxDocument($this, ['sameAs' => $uri]);
    }


    /**
     * @return DcxApiClient
     */
    public function newDcxApiClient()
    {
        if (! is_object($this->dcxApiClient)) {
            $params = [];

            if (! empty($this->params['http_useragent'])) {
                $params['http_useragent'] = $this->params['http_useragent'];
            }

            $this->dcxApiClient = new DcxApiClient
            (
                $this->params['url'],
                $this->params['credentials'],
                $params
            );
        }

        return $this->dcxApiClient;
    }
}
