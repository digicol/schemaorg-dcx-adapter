<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractSearchAction;
use Digicol\SchemaOrg\Sdk\ItemListInterface;
use Digicol\SchemaOrg\Sdk\SearchActionInterface;
use Digicol\SchemaOrg\Sdk\StreamHandlerInterface;
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
        $dcxApiClient = $this->adapter->newDcxApiClient();

        $dcxApiClient->get
        (
            'document',
            $this->getApiParams(),
            $searchResponse
        );

        return new DcxItemList($this->getAdapter(), $this, ['search_response' => $searchResponse]);
    }


    /**
     * @param StreamHandlerInterface $streamHandler
     * @return int
     */
    public function streamResult(StreamHandlerInterface $streamHandler)
    {
        /** @var DcxAdapter $adapter */
        $adapter = $this->getAdapter();

        $dcxApiClient = $adapter->newDcxApiClient();
        $searchAction = $this;

        $eventListener = function (array $event) use ($streamHandler, $adapter, $searchAction) {
            if ($event['type'] === 'metadata') {
                $streamHandler->onListMetadata(new DcxItemList($adapter, $searchAction,
                    ['search_response' => $event['data']]));
            } elseif ($event['type'] === 'entry') {
                $streamHandler->onListItem(new DcxDocument($adapter, ['data' => $event['data']]));
            } elseif ($event['type'] === 'close') {
                $streamHandler->onComplete();
            }
        };

        $dcxApiClient->stream
        (
            'document',
            $this->getApiParams(),
            $eventListener
        );

        return 1;
    }


    /**
     * @return array
     */
    protected function getApiParams()
    {
        $params =
            [
                's' =>
                    [
                        'properties' => '*',
                        'fields' => '*',
                        'files' => '*',
                        '_referenced' => ['dcx:file' => ['s' => ['properties' => '*']]]
                    ],
                'q' =>
                    [
                        'channel' => [$this->getPotentialSearchAction()->getParam('id')],
                        '_limit' => Utils::getItemsPerPage($this->inputProperties, self::DEFAULT_PAGESIZE),
                        '_offset' => (Utils::getStartIndex($this->inputProperties) - 1)
                    ]
            ];

        if (! empty($this->getQuery())) {
            $params['q']['fulltext'] = [$this->getQuery()];
        }

        if (isset($this->inputProperties['dcx:filters']) && is_array($this->inputProperties['dcx:filters'])) {
            $params['q']['filters'] = $this->inputProperties['dcx:filters'];
        }

        if (! empty($this->inputProperties['dcx:request_filters'])) {
            $params['q']['request_filters'] = $this->inputProperties['dcx:request_filters'];
        }

        if (! empty($this->inputProperties['dcx:request_highlighting'])) {
            $params['q']['request_highlighting'] = $this->inputProperties['dcx:request_highlighting'];
        }

        return $params;
    }
}
