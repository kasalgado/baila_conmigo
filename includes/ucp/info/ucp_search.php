<?php

class ucp_search_info
{
    function module()
    {
        return array(
            'filename' => 'ucp_search',
            'title' => 'UCP_SEARCH',
            'version' => '1.0.0',
            'modes' => array(
                'profile' => array(
                    'title' => 'UCP_SEARCH_PROFILE',
                    'auth' => '',
                    'cat' => array('UCP_SEARCH'),
                ),
                'forum' => array(
                    'title' => 'UCP_SEARCH_FORUM',
                    'auth' => '',
                    'cat' => array('UCP_SEARCH'),
                ),
            ),
        );
    }

    function install() {}

    function uninstall() {}
}
