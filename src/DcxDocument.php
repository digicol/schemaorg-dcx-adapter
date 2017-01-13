<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractThing;
use Digicol\SchemaOrg\Sdk\ThingInterface;
use Digicol\SchemaOrg\Sdk\Utils;


class DcxDocument extends AbstractThing implements ThingInterface
{
    /** @var DcxAdapter */
    protected $adapter;


    /**
     * Get identifier URI
     *
     * @return string
     */
    public function getSameAs()
    {
        if (! empty($this->params['data']['_id'])) {
            return $this->params['data']['_id'];
        } elseif (! empty($this->params['sameAs'])) {
            return $this->params['sameAs'];
        } else {
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

        $typeMap =
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

        if (isset($this->params['data']['fields']['Type'][0]['_id'])) {
            // "dcxapi:tm_topic/documenttype-image" => "documenttype-image"
            list(, $type_key) = explode('/', $this->params['data']['fields']['Type'][0]['_id']);

            if (isset($typeMap[$type_key])) {
                return $typeMap[$type_key];
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
        $ok = $this->load();

        // TODO: Improve error handling
        if ($ok < 0) {
            return [];
        }

        $data = $this->params['data'];

        // Core properties

        $result =
            [
                '@context' => Utils::getNamespaceContext(),
                '@type' => $this->getType(),
                'name' => [['@value' => $data['fields']['_display_title'][0]['value']]],
                'sameAs' => [['@id' => $data['_id']]]
            ];

        // DateImported/DateCreated => dateCreated

        foreach (['DateCreated', 'DateImported'] as $tagdef) {
            if (! empty($data['fields'][$tagdef][0]['value'])) {
                $result['dateCreated'] = [];

                foreach ($data['fields'][$tagdef] as $value) {
                    // Common DC-X hack - assume midnight means "just the date, no time"

                    if (! DcxUtils::isValidIso8601($value['value'])) {
                        $datatype = 'Text';
                    } elseif (strpos($value['value'], 'T00:00:00') !== false) {
                        $datatype = 'Date';
                    } else {
                        $datatype = 'DateTime';
                    }

                    $result['dateCreated'][] =
                        [
                            '@value' => $value['value'],
                            '@type' => $datatype
                        ];
                }

                break;
            }
        }

        // dateModified

        $result['dateModified'] =
            [
                [
                    '@value' => $data['properties']['_modified'],
                    '@type' => 'DateTime'
                ]
            ];

        // Provider/Source => provider

        foreach (['Provider', 'Source'] as $tagdef) {
            if (! empty($data['fields'][$tagdef][0]['value'])) {
                $result['provider'] = [];

                foreach ($data['fields'][$tagdef] as $value) {
                    $result['provider'][] = ['@value' => $value['value']];
                }
            }
        }

        // Creator => creator

        $mapTags =
            [
                'Creator' => ['property' => 'creator'],
                'URI' => ['property' => 'url', 'datatype' => 'URL']
            ];

        foreach ($mapTags as $tagdef => $mapConfig) {
            $property = $mapConfig['property'];

            if (! empty($data['fields'][$tagdef][0]['value'])) {
                $result[$property] = [];

                foreach ($data['fields'][$tagdef] as $value) {
                    $propertyValue = ['@value' => $value['value']];

                    if (! empty($mapConfig['datatype'])) {
                        $propertyValue['@type'] = $mapConfig['datatype'];
                    }

                    $result[$property][] = $propertyValue;
                }
            }
        }

        // Body text depends on type

        if (! empty($data['fields']['body'][0]['value'])) {
            $type = $this->getType();

            $bodyMap =
                [
                    'Article' => 'articleBody',
                    'ImageObject' => 'caption',
                    'NewsArticle' => 'articleBody',
                    'VideoObject' => 'caption'
                ];

            if (isset($bodyMap[$type])) {
                $bodyProperty = $bodyMap[$type];
            } else {
                $bodyProperty = 'text';
            }

            $bodyValue =
                [
                    '@value' => $data['fields']['body'][0]['value']
                ];

            if ($data['fields']['body'][0]['_type'] === 'xhtml') {
                $bodyValue['@type'] = 'http://www.w3.org/1999/xhtml';
            }

            $result[$bodyProperty] = [$bodyValue];
        }

        // Description (for search results) = body text, shortened

        if (! empty($data['fields']['body'][0]['value'])) {
            if ($data['fields']['body'][0]['_type'] === 'xhtml') {
                $bodyText = DcxUtils::toPlainText($data['fields']['body'][0]['value']);
            } else {
                $bodyText = $data['fields']['body'][0]['value'];
            }

            if (mb_strlen($bodyText) > 500) {
                $shortText = mb_substr($bodyText, 0, 500) . '…';
            } else {
                $shortText = $bodyText;
            }

            $result['description'] = [['@value' => $shortText]];
        }

        // Files
        // TODO: Implement file variants

        if (isset($data['_files_index']['variant_type']['master'])) {
            $thumbnails = [];

            foreach (['thumbnail', 'layout', 'minihires', 'webm', 'mp4'] as $fileType) {
                if (! isset($data['_files_index']['variant_type']['master'][$fileType])) {
                    continue;
                }

                $key = $data['_files_index']['variant_type']['master'][$fileType];
                $fileId = $data['files'][$key]['_id'];

                $thumbnails[] = $this->fileToMediaObject($data['_referenced']['dcx:file'][$fileId]);
            }

            if (isset($data['_files_index']['variant_type']['master']['original'])) {
                $key = $data['_files_index']['variant_type']['master']['original'];
                $fileId = $data['files'][$key]['_id'];

                $mediaObject = $this->fileToMediaObject($data['_referenced']['dcx:file'][$fileId]);

                $mediaObject['thumbnail'] = $thumbnails;

                $result['associatedMedia'] = [$mediaObject];
            } else {
                $result['thumbnail'] = $thumbnails;
            }
        }

        // Highlighting

        if (isset($data['_highlighting'])) {
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

        foreach (['Headline', 'Title', 'Filename'] as $tag) {
            if (empty($properties['digicol:_highlighting'][$tag][0])) {
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
        if (! empty($this->params['data'])) {
            return 0;
        }

        // TODO: Add error handling
        return $this->loadDetails($this->params['sameAs']);
    }


    protected function loadDetails($uri)
    {
        $dcxApi = $this->adapter->newDcxApi();

        $dcxApi->urlToObjectId($uri, $objectType, $objectId);

        $ok = $dcxApi->getObject
        (
            [
                'object_type' => $objectType,
                'object_id' => $objectId,
                's' =>
                    [
                        'properties' => '*',
                        'fields' => '*',
                        'files' => '*',
                        '_referenced' => ['dcx:file' => ['s' => ['properties' => '*', 'info' => '*']]]
                    ]
            ],
            $apiObj,
            $this->params['data']
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
                '@type' => $this->getMediaObjectType($file['properties']['mimetype']),
                'contentUrl' => $file['properties']['_file_url'],
                'contentSize' => $file['properties']['size'],
                'fileFormat' => $file['properties']['mimetype']
            ];

        if (! empty($file['properties']['displayname'])) {
            $result['name'] = $file['properties']['displayname'];
        }

        if (! empty($file['info']['ImageWidth'])) {
            $result['width'] = intval($file['info']['ImageWidth']);
        }

        if (! empty($file['info']['ImageHeight'])) {
            $result['height'] = intval($file['info']['ImageHeight']);
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
        list($type,) = explode('/', $mediatype);

        $typeMap =
            [
                'image' => 'ImageObject',
                'video' => 'VideoObject',
                'audio' => 'AudioObject'
            ];

        if (isset($typeMap[$type])) {
            return $typeMap[$type];
        }

        return 'MediaObject';
    }
}
