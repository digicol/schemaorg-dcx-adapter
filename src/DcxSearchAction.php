<?php

namespace Digicol\SchemaOrg\Dcx;


class DcxSearchAction implements \Digicol\SchemaOrg\SearchActionInterface
{
    const DEFAULT_PAGESIZE = 20;

    /** @var DcxAdapter */
    protected $adapter;

    /** @var array $params */
    protected $params = [ ];

    /** @var array */
    protected $input_properties = [ ];

    /** @var array */
    protected $search_response = [ ];


    public function __construct(DcxAdapter $adapter, array $params)
    {
        $this->adapter = $adapter;
        $this->params = $params;
    }


    /**
     * Get item type
     *
     * @return string schema.org type like "ImageObject" or "Thing"
     */
    public function getType()
    {
        return 'SearchAction';
    }


    /**
     * Get identifier URI
     *
     * @return string
     */
    public function getSameAs()
    {
        return '';
    }


    /** @return array */
    public function getParams()
    {
        return $this->params;
    }


    /** @return array */
    public function describeInputProperties()
    {
        return [ ];
    }


    /**
     * Set search parameters
     *
     * Common values that should be supported:
     *   query (string)
     *   opensearch:count (int; items per page)
     *   opensearch:startPage (int; 1 for the first page)
     *
     * @param array $values
     * @return int
     */
    public function setInputProperties(array $values)
    {
        $this->input_properties = $values;

        return 1;
    }


    /**
     * Get search parameters
     *
     * @return array
     */
    public function getInputProperties()
    {
        return $this->input_properties;
    }


    /**
     * @return int
     */
    public function execute()
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
                        'channel' => [ $this->params[ 'id' ] ],
                        '_limit' => \Digicol\SchemaOrg\Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE),
                        '_offset' => (\Digicol\SchemaOrg\Utils::getStartIndex($this->input_properties, self::DEFAULT_PAGESIZE) - 1)
                    ]
            ];

        if (! empty($this->input_properties['query']))
        {
            $params['query']['fulltext'] = [ $this->input_properties['query'] ];
        }

        if (isset($this->input_properties['dcx:filters']) && is_array($this->input_properties['dcx:filters']))
        {
            $params['query']['filters'] = $this->input_properties['dcx:filters'];
        }

        if (! empty($this->input_properties['dcx:request_filters']))
        {
            $params['query']['request_filters'] = $this->input_properties['dcx:request_filters'];
        }

        $dcx_api = $this->adapter->newDcxApi();

        $ok = $dcx_api->getObjects
        (
            $params,
            $api_obj,
            $this->search_response
        );

        return $ok;
    }


    /**
     * Get all property values
     *
     * @return array
     */
    public function getProperties()
    {
        $result = \Digicol\SchemaOrg\Utils::getSearchActionSkeleton();

        if ((! is_array($this->search_response)) || (! isset($this->search_response[ 'entries' ])))
        {
            return $result;
        }

        $result[ 'query' ] = (isset($this->input_properties['query']) ? $this->input_properties['query'] : '');

        if (is_array($this->search_response) && isset($this->search_response[ 'totalResults' ]))
        {
            $total_results = $this->search_response[ 'totalResults' ];
        }
        else
        {
            $total_results = 0;
        }

        $result[ 'result' ][ 'numberOfItems' ] = $total_results;
        $result[ 'result' ][ 'opensearch:itemsPerPage' ] = \Digicol\SchemaOrg\Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE);
        $result[ 'result' ][ 'opensearch:startIndex' ] = \Digicol\SchemaOrg\Utils::getStartIndex($this->input_properties, self::DEFAULT_PAGESIZE);

        foreach ($this->search_response[ 'entries' ] as $i => $entry_data)
        {
            $result[ 'result' ][ 'itemListElement' ][ ] =
                [
                    '@type' => 'ListItem',
                    'position' => ($i + 1),
                    'item' => new DcxDocument($this->adapter, [ 'data' => $entry_data ])
                ];
        }

        if (isset($this->search_response[ '_available_filters' ]))
        {
            $result[ 'dcx:_available_filters' ] = $this->search_response[ '_available_filters' ]; 
        }
        
        return $result;
    }
    
}
