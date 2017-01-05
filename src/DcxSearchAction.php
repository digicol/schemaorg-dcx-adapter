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
                        '_referenced' => ['dcx:file' => ['s' => ['properties' => '*']]]
                    ],
                'query' =>
                    [
                        'channel' => [$this->getPotentialSearchAction()->getParam('id')],
                        '_limit' => Utils::getItemsPerPage($this->inputProperties, self::DEFAULT_PAGESIZE),
                        '_offset' => (Utils::getStartIndex($this->inputProperties) - 1)
                    ]
            ];

        if (! empty($this->getQuery())) {
            $params['query']['fulltext'] = [$this->getQuery()];
        }

        if (isset($this->inputProperties['dcx:filters']) && is_array($this->inputProperties['dcx:filters'])) {
            $params['query']['filters'] = $this->inputProperties['dcx:filters'];
        }

        if (! empty($this->inputProperties['dcx:request_filters'])) {
            $params['query']['request_filters'] = $this->inputProperties['dcx:request_filters'];
        }

        if (! empty($this->inputProperties['dcx:request_highlighting'])) {
            $params['query']['request_highlighting'] = $this->inputProperties['dcx:request_highlighting'];
        }

        $dcxApi = $this->adapter->newDcxApi();

        $ok = $dcxApi->getObjects
        (
            $params,
            $apiObj,
            $searchResponse
        );

        return new DcxItemList($this->getAdapter(), $this, ['search_response' => $searchResponse]);
    }
}
