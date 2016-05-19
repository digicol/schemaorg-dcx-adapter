<?php

namespace Digicol\SchemaOrg\Dcx;


class DcxAdapter implements \Digicol\SchemaOrg\AdapterInterface
{
    /** @var array */
    protected $params = [ ];

    /** @var \DCX_Application */
    protected $app;

    /** @var \DCX_Api */
    protected $dcx_api;


    /**
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }


    /** @return array */
    public function describeSearchActions()
    {
        $result = [ ];

        $this->initDcx();

        // TODO: Use DCX_Api instead

        $permitted_channel_ids = $this->app->user->getChannelsByPermDef('dcx_channel.show_in_menu');

        if (! is_array($permitted_channel_ids))
        {
            return $result;
        }

        $dcx_channel = new \DCX_Channel($this->app);

        foreach ($permitted_channel_ids as $channel_id)
        {
            $ok = $dcx_channel->load($channel_id);

            if ($ok < 0)
            {
                continue;
            }

            $result[ ] =
                [
                    'name' => $dcx_channel->getLabel(),
                    'description' => $dcx_channel->getRemark(),
                    'type' => 'channel',
                    'id' => $dcx_channel->getId()
                ];
        }

        return $result;
    }


    /**
     * @param array $search_params
     * @return DcxSearchAction
     */
    public function newSearchAction(array $search_params)
    {
        return new DcxSearchAction($this, $search_params);
    }


    /**
     * @param string $uri sameAs identifying URL
     * @return DcxDocument
     */
    public function newThing($uri)
    {
        return new DcxDocument($this, [ 'sameAs' => $uri ]);
    }


    /**
     * @return \DCX_Api
     */
    public function newDcxApi()
    {
        if (! is_object($this->dcx_api))
        {
            $this->initDcx();
            $this->dcx_api = new \DCX_Api($this->app);
        }

        return $this->dcx_api;
    }


    protected function initDcx()
    {
        global $app;

        putenv('DC_CONFIGDIR=' . $this->params[ 'dc_configdir' ]);
        putenv('DC_APP=' . $this->params[ 'dc_app' ]);

        require_once $this->params[ 'dc_systemdir' ] . '/include/init.inc.php';

        $this->app = $app;
    }
}