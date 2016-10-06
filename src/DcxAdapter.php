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
    public function getParams()
    {
        $this->initDcx();
        
        $result = $this->params;
        
        $result['logged_in'] = $this->app->isLoggedIn();
        $result['login_url_pattern'] = $this->app->base_url . 'login?redirect=%s';
        $result['logout_url_pattern'] = $this->app->base_url . 'login?logout=1&redirect=%s';
        
        return $result;
    }


    /** @return array */
    public function describeSearchActions()
    {
        $result = [ ];

        $dcx_api = $this->newDcxApi();

        // TODO: Use DCX_Api navigation page instead

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

            $dcx_api->getPage('searchform', $searchform);

            $order = $this->app->cache->getConfigValue('dcx_channel.order', $channel_id);

            if (! is_numeric($order))
            {
                $order = 0;
            }

            $result[ $dcx_channel->getId() ] =
                [
                    'name' => $dcx_channel->getLabel(),
                    'description' => $dcx_channel->getRemark(),
                    'order' => $order,
                    'type' => 'channel',
                    'id' => $dcx_channel->getId(),
                    'searchform' => $searchform
                ];
        }

        uasort($result, [ $this, 'channelSortCallback' ]);

        return $result;
    }


    public function channelSortCallback($a, $b)
    {
        $a = $a['order'];
        $b = $b['order'];

        if ($a === $b)
        {
            return 0;
        }
        
        return ($a > $b ? 1 : -1);
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

        if (is_object($this->app))
        {
            return;
        }

        putenv('DC_CONFIGDIR=' . $this->params[ 'dc_configdir' ]);
        putenv('DC_APP=' . $this->params[ 'dc_app' ]);

        require_once $this->params[ 'dc_systemdir' ] . '/include/init.inc.php';

        $this->app = $app;
    }
}
