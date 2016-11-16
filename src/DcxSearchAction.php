<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractSearchAction;
use Digicol\SchemaOrg\Sdk\ItemListInterface;
use Digicol\SchemaOrg\Sdk\SearchActionInterface;
use Digicol\SchemaOrg\Sdk\Utils;


class DcxSearchAction extends AbstractSearchAction implements SearchActionInterface 
{
    /** @var DcxAdapter */
    protected $adapter;
    
    const DEFAULT_PAGESIZE = 20;


    /**
     * @return ItemListInterface
     */
    public function getResult()
    {
        $params =
            [
                'object_type' => 'document',
                's' =>
                    [
                        'properties' => '*',
                        'fields' => '*',
                        'files' => '*',
                        '_referenced' => [ 'dcx:file' => [ 's' => [ 'properties' => '*' ] ] ]
                    ],
                'query' =>
                    [
                        'channel' => [ $this->getPotentialSearchAction()->getParam('id') ],
                        '_limit' => Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE),
                        '_offset' => (Utils::getStartIndex($this->input_properties) - 1)
                    ]
            ];

        if (! empty($this->getQuery()))
        {
            $params['query']['fulltext'] = [ $this->getQuery() ];
        }

        if (isset($this->input_properties['dcx:filters']) && is_array($this->input_properties['dcx:filters']))
        {
            $params['query']['filters'] = $this->input_properties['dcx:filters'];
        }

        if (! empty($this->input_properties['dcx:request_filters']))
        {
            $params['query']['request_filters'] = $this->input_properties['dcx:request_filters'];
        }

        if (! empty($this->input_properties['dcx:request_highlighting']))
        {
            $params['query']['request_highlighting'] = $this->input_properties['dcx:request_highlighting'];
        }

        $dcx_api = $this->adapter->newDcxApi();

        $ok = $dcx_api->getObjects
        (
            $params,
            $api_obj,
            $search_response
        );
        
        return new DcxItemList($this->getAdapter(), $this, [ 'search_response' => $search_response ]);
    }
}
