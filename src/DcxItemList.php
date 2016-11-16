<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractItemList;
use Digicol\SchemaOrg\Sdk\AdapterInterface;
use Digicol\SchemaOrg\Sdk\ItemListInterface;
use Digicol\SchemaOrg\Sdk\SearchActionInterface;
use Digicol\SchemaOrg\Sdk\Utils;


class DcxItemList extends AbstractItemList implements ItemListInterface 
{
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
        $this->items = [ ];
        
        $response = $this->params[ 'search_response' ];
        
        if ((! is_array($response)) || (! isset($response['entries'])))
        {
            return;
        }

        if (is_array($this->search_response) && isset($this->search_response['totalResults']))
        {
            $total_results = $this->search_response['totalResults'];
        }
        else
        {
            $total_results = 0;
        }

        $result['result']['numberOfItems'] = $total_results;
        $result['result']['opensearch:itemsPerPage'] = Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE);
        $result['result']['opensearch:startIndex'] = Utils::getStartIndex($this->input_properties, self::DEFAULT_PAGESIZE);

        foreach ($this->search_response['entries'] as $i => $entry_data)
        {
            $result['result']['itemListElement'][] =
                [
                    '@type' => 'ListItem',
                    'position' => ($i + 1),
                    'item' => new DcxDocument($this->adapter, [ 'data' => $entry_data ])
                ];
        }

        if (isset($this->search_response['_available_filters']))
        {
            $result['dcx:_available_filters'] = $this->search_response['_available_filters'];
        }

        return $result;
    }
}
