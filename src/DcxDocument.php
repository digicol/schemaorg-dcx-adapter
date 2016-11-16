<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\ThingInterface;
use Digicol\SchemaOrg\Sdk\Utils;


class DcxDocument implements ThingInterface
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
                'documenttype-audio' => 'Clip',
                'documenttype-desktop_publishing' => 'DigitalDocument',
                'documenttype-email' => 'EmailMessage',
                'documenttype-illustrator' => 'DigitalDocument',
                'documenttype-image' => 'Photograph',
                'documenttype-office_text' => 'DigitalDocument',
                'documenttype-pdf' => 'DigitalDocument',
                'documenttype-postscript' => 'DigitalDocument',
                'documenttype-presentation' => 'DigitalDocument',
                'documenttype-spreadsheet' => 'DigitalDocument',
                'documenttype-story' => 'Article',
                'documenttype-text' => 'Article',
                'documenttype-video' => 'Clip'
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
                '@context' => Utils::getNamespaceContext(),
                '@type' => $this->getType(),
                'name' => [ [ '@value' => $data[ 'fields' ][ '_display_title' ][ 0 ][ 'value' ] ] ],
                'sameAs' => [ [ '@id' => $data[ '_id' ] ] ]
            ];

        // DateImported/DateCreated => dateCreated
        
        foreach ([ 'DateCreated', 'DateImported' ] as $tagdef)
        {
            if (! empty($data[ 'fields' ][ $tagdef ][ 0 ][ 'value' ]))
            {
                $result[ 'dateCreated' ] = [ ];
                
                foreach ($data[ 'fields' ][ $tagdef ] as $value)
                {
                    // Common DC-X hack - assume midnight means "just the date, no time"

                    if (! DcxUtils::isValidIso8601($value[ 'value' ]))
                    {
                        $datatype = 'Text';
                    }
                    elseif (strpos($value[ 'value' ], 'T00:00:00') !== false)
                    {
                        $datatype = 'Date';
                    }
                    else
                    {
                        $datatype = 'DateTime';
                    }

                    $result[ 'dateCreated' ][ ] =
                        [
                            '@value' => $value[ 'value' ],
                            '@type' => $datatype
                        ];
                }
                
                break;
            }
        }
        
        // dateModified
        
        $result[ 'dateModified' ] =
            [
                [
                    '@value' => $data[ 'properties' ][ '_modified' ],
                    '@type' => 'DateTime'
                ]
            ];

        // Provider/Source => provider

        foreach ([ 'Provider', 'Source' ] as $tagdef)
        {
            if (! empty($data[ 'fields' ][ $tagdef ][ 0 ][ 'value' ]))
            {
                $result[ 'provider' ] = [ ];

                foreach ($data[ 'fields' ][ $tagdef ] as $value)
                {
                    $result[ 'provider' ][ ] = [ '@value' => $value[ 'value' ] ];
                }
            }
        }

        // Creator => creator

        $map_tags = 
            [
                'Creator' => [ 'property' => 'creator' ],
                'URI' => [ 'property' => 'url', 'datatype' => 'URL' ]
            ];
        
        foreach ($map_tags as $tagdef => $map_config)
        {
            $property = $map_config[ 'property' ];

            if (! empty($data[ 'fields' ][ $tagdef ][ 0 ][ 'value' ]))
            {
                $result[ $property ] = [ ];

                foreach ($data[ 'fields' ][ $tagdef ] as $value)
                {
                    $property_value = [ '@value' => $value[ 'value' ] ];

                    if (! empty($map_config[ 'datatype' ]))
                    {
                        $property_value[ '@type' ] = $map_config[ 'datatype' ];
                    }

                    $result[ $property ][ ] = $property_value;
                }
            }
        }

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
                $body_property = 'text';
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

        // Description (for search results) = body text, shortened

        if (! empty($data[ 'fields' ][ 'body' ][ 0 ][ 'value' ]))
        {
            if ($data[ 'fields' ][ 'body' ][ 0 ][ '_type' ] === 'xhtml')
            {
                $body_text = DcxUtils::toPlainText($data[ 'fields' ][ 'body' ][ 0 ][ 'value' ]);
            }
            else
            {
                $body_text = $data[ 'fields' ][ 'body' ][ 0 ][ 'value' ];
            }
            
            if (mb_strlen($body_text) > 500)
            {
                $short_text = mb_substr($body_text, 0, 500) . '…';
            }
            else
            {
                $short_text = $body_text;
            }

            $result[ 'description' ] = [ [ '@value' => $short_text ] ];
        }
        
        // Files
        // TODO: Implement file variants

        if (isset($data[ '_files_index' ][ 'variant_type' ][ 'master' ]))
        {
            $thumbnails = [ ];
            
            foreach ([ 'thumbnail', 'layout', 'minihires', 'webm', 'mp4' ] as $file_type)
            {
                if (! isset($data[ '_files_index' ][ 'variant_type' ][ 'master' ][ $file_type ]))
                {
                    continue;
                }
                
                $key = $data[ '_files_index' ][ 'variant_type' ][ 'master' ][ $file_type ];
                $file_id = $data[ 'files' ][ $key ][ '_id' ];
                
                $thumbnails[ ] = $this->fileToMediaObject($data[ '_referenced' ][ 'dcx:file' ][ $file_id ]);
            }
            
            if (isset($data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'original' ]))
            {
                $key = $data[ '_files_index' ][ 'variant_type' ][ 'master' ][ 'original' ];
                $file_id = $data[ 'files' ][ $key ][ '_id' ];

                $media_object = $this->fileToMediaObject($data[ '_referenced' ][ 'dcx:file' ][ $file_id ]);
                
                $media_object[ 'thumbnail' ] = $thumbnails;

                $result[ 'associatedMedia' ] = [ $media_object ];
            }
            else
            {
                $result[ 'thumbnail' ] = $thumbnails;
            }
        }
        
        // Highlighting
        
        if (isset($data['_highlighting']))
        {
            // TODO: Standardize to schema.org? At least map to schema.org field names 
            $result['digicol:_highlighting'] = $data['_highlighting']; 
        }
        
        return $result;
    }


    /**
     * @param array $properties
     * @return array
     */
    public function getReconciledProperties(array $properties)
    {
        $result = Utils::reconcileThingProperties
        (
            $this->getType(),
            $properties
        );
        
        // Apply highlighting
        // TODO: Does it make sense to overwrite the name with highlighted stuff?
        // Are we sure no-one uses this for updates?
        // TODO: Standardize field names to schema.org?
        // TODO: Do it like \DCX_Document::getDisplayTitleTag()
        
        foreach (['Headline', 'Title', 'Filename'] as $tag)
        {
            if (empty($properties['digicol:_highlighting'][$tag][0]))
            {
                continue;
            }
            
            $result['name'][0]['@value'] = strtr
            (
                htmlspecialchars($properties['digicol:_highlighting'][$tag][0]),
                [
                    '~[' => '<mark>',
                    ']~' => '</mark>'
                ]
            );

            $result['name'][0]['@type'] = 'http://www.w3.org/1999/xhtml';
            
            break;
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
                        '_referenced' => [ 'dcx:file' => [ 's' => [ 'properties' => '*', 'info' => '*' ] ] ]
                    ]
            ],
            $api_obj,
            $this->params[ 'data' ]
        );
        
        return $ok;
    }


    /**
     * Get media object array
     * 
     * @param array $file
     * @return array
     */
    protected function fileToMediaObject(array $file)
    {
        $result = 
            [
                '@type' => $this->getMediaObjectType($file[ 'properties' ][ 'mimetype' ]),
                'contentUrl' => $file[ 'properties' ][ '_file_url' ],
                'contentSize' => $file[ 'properties' ][ 'size' ],
                'fileFormat' => $file[ 'properties' ][ 'mimetype' ]
            ];

        if (! empty($file[ 'properties' ][ 'displayname' ]))
        {
            $result[ 'name' ] = $file[ 'properties' ][ 'displayname' ]; 
        }
        
        if (! empty($file[ 'info' ][ 'ImageWidth' ]))
        {
            $result[ 'width' ] = intval($file[ 'info' ][ 'ImageWidth' ]); 
        }

        if (! empty($file[ 'info' ][ 'ImageHeight' ]))
        {
            $result[ 'height' ] = intval($file[ 'info' ][ 'ImageHeight' ]);
        }
        
        return $result;
    }


    /**
     * Get file media object type
     *
     * @return string schema.org type like "ImageObject" or "VideoObject"
     */
    protected function getMediaObjectType($mediatype)
    {
        list($type, ) = explode('/', $mediatype);

        $type_map =
            [
                'image' => 'ImageObject',
                'video' => 'VideoObject',
                'audio' => 'AudioObject'
            ];

        if (isset($type_map[ $type ]))
        {
            return $type_map[ $type ];
        }

        return 'MediaObject';
    }
}
