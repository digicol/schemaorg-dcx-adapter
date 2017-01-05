<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractItemList;
use Digicol\SchemaOrg\Sdk\AdapterInterface;
use Digicol\SchemaOrg\Sdk\ItemListInterface;
use Digicol\SchemaOrg\Sdk\SearchActionInterface;


class DcxItemList extends AbstractItemList implements ItemListInterface
{
    /** @var DcxAdapter */
    protected $adapter;


    /**
     * @param array $params
     */
    public function __construct(AdapterInterface $adapter, SearchActionInterface $search_action, array $params)
    {
        parent::__construct($adapter, $search_action, $params);

        $this->prepareItems();
    }


    protected function prepareItems()
    {
        $this->items = [];
        $this->outputProperties['numberOfItems'] = 0;

        $response = $this->params['search_response'];

        if ((! is_array($response)) || (! isset($response['entries']))) {
            return;
        }

        $this->outputProperties['opensearch:startIndex'] = $response['startIndex'];
        $this->outputProperties['opensearch:itemsPerPage'] = $response['itemsPerPage'];
        $this->outputProperties['numberOfItems'] = $response['totalResults'];

        if (isset($response['_available_filters'])) {
            $this->outputProperties['dcx:_available_filters'] = $response['_available_filters'];
        }

        foreach ($response['entries'] as $i => $entryData) {
            $this->items[] = new DcxDocument
            (
                $this->getAdapter(),
                ['data' => $entryData]
            );
        }
    }
}
