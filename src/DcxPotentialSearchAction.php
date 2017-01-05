<?php

namespace Digicol\SchemaOrg\Dcx;

use Digicol\SchemaOrg\Sdk\AbstractPotentialSearchAction;
use Digicol\SchemaOrg\Sdk\PotentialSearchActionInterface;
use Digicol\SchemaOrg\Sdk\SearchActionInterface;


class DcxPotentialSearchAction extends AbstractPotentialSearchAction implements PotentialSearchActionInterface
{
    /** @return array */
    public function describeInputProperties()
    {
        return [];
    }


    /**
     * @return SearchActionInterface
     */
    public function newSearchAction()
    {
        return new DcxSearchAction($this->getAdapter(), $this, []);
    }
}
