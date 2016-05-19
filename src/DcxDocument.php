<?php

namespace Digicol\SchemaOrg\Dcx;


class DcxDocument implements \Digicol\SchemaOrg\ThingInterface
{
    /** @var DcxAdapter */
    protected $adapter;

    /** @var array $params */
    protected $params = [ ];


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
        // TODO: Use Type tagdef
        return 'CreativeWork';
    }


    /**
     * Get all property values
     *
     * @return array
     */
    public function getProperties()
    {
        if (empty($this->params[ 'data' ]))
        {
            // TODO: Add error handling
            $this->loadDetails($this->params['sameAs']);
        }
        
        $data = $this->params[ 'data' ];
        
        $result =
            [
                'name' => $data[ 'fields' ][ '_display_title' ][ 0 ][ 'value' ],
                'description' => '',
                'sameAs' => $data[ '_id' ]
            ];

        // TODO: How to handle xml:lang, datatype (HTML, datetime) in schema.org JSON-LD?

        if (isset($data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'thumbnail' ]))
        {
            $key = $data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'thumbnail' ];
            $file_id = $data[ 'files' ][ $key ][ '_id' ];

            $result[ 'image' ] = $data[ '_referenced' ][ 'dcx:file' ][ $file_id ][ 'properties' ][ '_file_url' ];
        }

        return $result;
    }


    protected function loadDetails($uri)
    {
        $dcx_api = $this->adapter->newDcxApi();

        $dcx_api->urlToObjectId($uri, $object_type, $object_id);
        
        $ok = $dcx_api->getObject
        (
            [
                'object_type' => $object_type,
                'object_id' => $object_id,
                's' =>
                    [
                        'properties' => '*',
                        'fields' => '*',
                        'files' => '*',
                        '_referenced' => [ 'dcx:file' => [ 's' => [ 'properties' => '*' ] ] ]
                    ]
            ],
            $api_obj,
            $this->params[ 'data' ]
        );
        error_log(print_r($this->params[ 'data' ], true));
        
        return $ok;
    }
}
