<?php

namespace Digicol\SchemaOrg\Dcx;


use Symfony\Component\Security\Core\User\UserInterface;

class DcxUser implements UserInterface
{
    protected $userid;
    protected $password;
    protected $displayname;
    protected $roles;

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return mixed
     */
    public function getRoles()
    {
        return $this->roles;
    }

    public function load(\DCX_User $dcxuser )
    {
        if ($dcxuser->getDn() === false)
        {
            return -1;
        }
        $this->username = $dcxuser->getDn();
        $this->displayname = $dcxuser->getDisplayName();
        $this->roles = array();
        return 1;
    }

    public function getUserId()
    {
        return $this->userid;
    }

    public function getDisplayName()
    {
        return $this->displayname;
    }

    public function matchPassword( $password )
    {
        return true;
    }

    public function hasrole( $role )
    {
        return true;
    }

    public function getSalt()
    {
        return 'salt';
    }

    public function getUsername()
    {
        return $this->displayname;
    }

    public function eraseCredentials()
    {
      $this->password = '';
    }
}