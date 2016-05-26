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
     * @return int
     */
    public function execute()
    {
        if (! empty($this->input_properties['q']))
        {
            $fulltext = $this->input_properties['q'];
        }
        else
        {
            $fulltext = '';
        }

        $dcx_api = $this->adapter->newDcxApi();

        $ok = $dcx_api->getObjects
        (
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
                        'fulltext' => [ $fulltext ],
                        '_limit' => \Digicol\SchemaOrg\Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE),
                        '_offset' => (\Digicol\SchemaOrg\Utils::getStartIndex($this->input_properties, self::DEFAULT_PAGESIZE) - 1)
                    ]
            ],
            $api_obj,
            $this->search_response
        );

        return $ok;
    }


    /**
     * Get search results
     *
     * @return array Array of objects implementing ThingInterface
     */
    public function getResult()
    {
        $result = [ ];

        if ((! is_array($this->search_response)) || (! isset($this->search_response[ 'entries' ])))
        {
            return $result;
        }

        foreach ($this->search_response[ 'entries' ] as $entry_data)
        {
            $result[ ] = new DcxDocument($this->adapter, [ 'data' => $entry_data ]);
        }

        return $result;
    }


    /**
     * Get search result metadata
     *
     * The array should contain at least these three values:
     *
     *   opensearch:totalResults (int)
     *   opensearch:startIndex (int; 1 for the first document)
     *   opensearch:itemsPerPage (int)
     *
     * @return array
     */
    public function getResultMeta()
    {
        if (is_array($this->search_response) && isset($this->search_response[ 'totalResults' ]))
        {
            $total_results = $this->search_response[ 'totalResults' ];
        }
        else
        {
            $total_results = 0;
        }

        return
            [
                'opensearch:totalResults' => $total_results,
                'opensearch:startIndex' => \Digicol\SchemaOrg\Utils::getStartIndex($this->input_properties, self::DEFAULT_PAGESIZE),
                'opensearch:itemsPerPage' => \Digicol\SchemaOrg\Utils::getItemsPerPage($this->input_properties, self::DEFAULT_PAGESIZE)
            ];
    }

}
