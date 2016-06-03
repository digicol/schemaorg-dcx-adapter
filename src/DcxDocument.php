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
     * Get identifier URI
     *
     * @return string
     */
    public function getSameAs()
    {
        if (! empty($this->params[ 'data' ][ '_id' ]))
        {
            return $this->params[ 'data' ][ '_id' ];
        }
        elseif (! empty($this->params[ 'sameAs' ]))
        {
            return $this->params[ 'sameAs' ];
        }
        else
        {
            return '';
        }
    }


    /**
     * Get item type
     *
     * @return string schema.org type like "ImageObject" or "Thing"
     */
    public function getType()
    {
        $this->load();
        
        $type_map =
            [
                'documenttype-audio' => 'AudioObject',
                'documenttype-desktop_publishing' => 'MediaObject',
                'documenttype-email' => 'EmailMessage',
                'documenttype-illustrator' => 'MediaObject',
                'documenttype-image' => 'ImageObject',
                'documenttype-office_text' => 'MediaObject',
                'documenttype-pdf' => 'MediaObject',
                'documenttype-postscript' => 'MediaObject',
                'documenttype-presentation' => 'MediaObject',
                'documenttype-spreadsheet' => 'MediaObject',
                'documenttype-story' => 'Article',
                'documenttype-text' => 'Article',
                'documenttype-video' => 'VideoObject'
            ];
        
        if (isset($this->params[ 'data' ][ 'fields' ][ 'Type' ][ 0 ][ '_id' ]))
        {
            // "dcxapi:tm_topic/documenttype-image" => "documenttype-image"
            list(, $type_key) = explode('/', $this->params[ 'data' ][ 'fields' ][ 'Type' ][ 0 ][ '_id' ]);
            
            if (isset($type_map[ $type_key ]))
            {
                return $type_map[ $type_key ];
            }
        }
        
        return 'Thing';
    }


    /**
     * Get all property values
     *
     * @return array
     */
    public function getProperties()
    {
        $this->load();
        
        $data = $this->params[ 'data' ];
        
        // Core properties
        
        $result =
            [
                'name' => [ [ '@value' => $data[ 'fields' ][ '_display_title' ][ 0 ][ 'value' ] ] ],
                'sameAs' => [ [ '@id' => $data[ '_id' ] ] ]
            ];

        // DateCreated => dateCreated
        
        // Body text depends on type

        if (! empty($data[ 'fields' ][ 'body' ][ 0 ][ 'value' ]))
        {
            $type = $this->getType();
            
            $body_map =
                [
                    'Article' => 'articleBody',
                    'ImageObject' => 'caption',
                    'NewsArticle' => 'articleBody',
                    'VideoObject' => 'caption'
                ];
        
            if (isset($body_map[ $type ]))
            {
                $body_property = $body_map[ $type ]; 
            }
            else
            {
                $body_property = 'description';
            }
            
            $body_value =
                [
                    '@value' => $data[ 'fields' ][ 'body' ][ 0 ][ 'value' ]
                ];
            
            if ($data[ 'fields' ][ 'body' ][ 0 ][ '_type' ] === 'xhtml')
            {
                $body_value[ '@type' ] = 'http://www.w3.org/1999/xhtml';
            }
            
            $result[ $body_property ] = [ $body_value ];
        }

        if (isset($data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'thumbnail' ]))
        {
            $key = $data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'thumbnail' ];
            $file_id = $data[ 'files' ][ $key ][ '_id' ];

            $result[ 'image' ] = [ [ '@id' => $data[ '_referenced' ][ 'dcx:file' ][ $file_id ][ 'properties' ][ '_file_url' ] ] ];
        }

        return $result;
    }


    protected function load()
    {
        if (! empty($this->params[ 'data' ]))
        {
            return 0;
        }

        // TODO: Add error handling
        return $this->loadDetails($this->params['sameAs']);
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
        
        return $ok;
    }
}
