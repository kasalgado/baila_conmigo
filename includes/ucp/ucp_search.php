<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB')) {
    exit;
}

/**
 * Class description....
 */
class ucp_search
{
    const MAX_AGE_VALUE = 80;
    const MIN_AGE_VALUE = 13;
    const MAX_HEIGHT_VALUE = 180;
    const MIN_HEIGHT_VALUE = 148;

    var $u_action;

    /**
     *
     * @param type $id
     * @param type $mode
     */
    function main($id, $mode)
    {
        global $request, $user, $db, $template, $phpbb_container, $auth, $phpEx;

        $submit = $request->variable('submit', false, false, \phpbb\request\request_interface::POST);
        $phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
        $phpEx = substr(strrchr(__FILE__, '.'), 1);

        switch ($mode) {
            case 'profile':
                if ($submit) {
                    include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

                    // Start session management
                    $user->session_begin();
                    $auth->acl($user->data);
                    $user->setup('search');

                    $age_range = array(
                        'pf_user_between_start' => utf8_normalize_nfc(request_var('pf_user_between_start', '', true)),
                        'pf_user_between_end' => utf8_normalize_nfc(request_var('pf_user_between_end', '', true)),
                    );
                    $height_range = array(
                        'pf_user_height_start' => utf8_normalize_nfc(request_var('pf_user_height_start', '', true)),
                        'pf_user_height_end' => utf8_normalize_nfc(request_var('pf_user_height_end', '', true)),
                    );
                    $photo = utf8_normalize_nfc(request_var('pf_user_photo', '', true));
                    $form_fields = array(
                        'pf_user_gender',
                        'pf_user_body',
                        'pf_user_about',
                        'pf_user_location',
                        'pf_user_salsa',
                        'pf_user_bachata',
                        'pf_user_regueton',
                        'pf_user_electronica',
                    );

                    $params = array();
                    foreach ($form_fields as $name) {
                        $value = utf8_normalize_nfc(request_var($name, '', true));
                        if ($name == 'pf_user_about' && $value != '' || $name != 'pf_user_about' && $value != '1') {
                            $params[$name] = $value;
                        }
                    }

                    $result = $this->prepareSQL($params, $age_range, $height_range, $photo, array('u.user_id'));
                    $count = 0;

                    if ($result) {
                        while ($row = $db->sql_fetchrow($result)) {
                            $user_id = $row['user_id'];
                            $this->setTemplateVars($user_id, $phpbb_root_path, $phpEx);
                            $count++;
                        }
                        $matches = $user->lang('FOUND_SEARCH_MATCHES', $count);
                    } else {
                        $matches = $user->lang('FOUND_SEARCH_MATCHES', $count);
                    }

                    $db->sql_freeresult($result);

                    $template->assign_vars(array(
                        'L_TITLE' => $user->lang['UCP_SEARCH_PROFILE'],
                        'L_UCP_KEYWORDS' => $user->lang['UCP_KEYWORDS'],
                        'SEARCH_MATCHES' => $matches,
                    ));

                } else {
                    $age = range(self::MIN_AGE_VALUE, self::MAX_AGE_VALUE);
                    foreach ($age as $option) {
                        $template->assign_block_vars('age_range', array(
                            'option' => $option,
                        ));
                    }

                    $height = range(self::MIN_HEIGHT_VALUE, self::MAX_HEIGHT_VALUE);
                    foreach ($height as $index => $option) {
                        if ($index > 1) {
                            $template->assign_block_vars('height_range', array(
                                'index' => $index,
                                'option' => $option,
                            ));
                        }
                    }
                }

                $cp = $phpbb_container->get('profilefields.manager');
                $cp->generate_profile_fields('search', $user->get_iso_lang_id());

                $this->tpl_name = $submit ? 'profile_search_results' : 'ucp_search';
                $this->page_title = 'UCP_SEARCH_PROFILE';
                break;

            case 'forum':
                redirect(append_sid('search.' . $phpEx));
                break;
        }
    }

    /**
     * Prepare SQL query
     *
     * @global type $db
     * @param array $params
     * @param array $age_range
     * @param array $height_range
     * @param bool $photo
     * @param type $fields
     * @return type
     */
    function prepareSQL(array $params, array $age_range, array $height_range, $photo = false, $fields = array())
    {
        global $db;

        $selected_fields = '';
        $conditions = '';
        $search_age = '';

        if (empty($fields)) {
            $selected_fields = '* ';
        } else {
            foreach($fields as $field) {
                $selected_fields .= $field;
                if(next($fields) == true) {
                    $selected_fields = ', ';
                }
            }
        }

        if($params) {
            $conditions .= ' AND ';
            foreach($params as $param => $value) {
                $conditions .= $param == 'pf_user_about'
                    ? $param . ' LIKE "%' . $value . '%"'
                    : $param . ' = ' . $value;
                if(next($params) == true) {
                    $conditions .= ' AND ';
                }
            }
        }

        $age_range['pf_user_between_end'] = $age_range['pf_user_between_end'] < 48 ?: '48';

        $sql = "SELECT " . $selected_fields .
            " FROM " . PROFILE_FIELDS_DATA_TABLE . " as pf, " . USERS_TABLE . " as u " .
            " WHERE pf.user_id = u.user_id" .
            " AND pf.pf_user_height <= " . $height_range['pf_user_height_end'] .
            " AND pf.pf_user_height >= " . $height_range['pf_user_height_start'] .
            " AND UNIX_TIMESTAMP(STR_TO_DATE(u.user_birthday,'%d-%m-%Y')) >= UNIX_TIMESTAMP(NOW() - INTERVAL " . (int) ($age_range['pf_user_between_end'] + 1) . " YEAR)" .
            " AND UNIX_TIMESTAMP(STR_TO_DATE(u.user_birthday,'%d-%m-%Y')) <= UNIX_TIMESTAMP(NOW() - INTERVAL " . (int) $age_range['pf_user_between_start'] . " YEAR)" . $conditions;
        $sql .= $photo == true ? ' AND u.user_avatar IS TRUE' : '';

        return $db->sql_query($sql);
    }

    /**
     * Prepare variables for template
     *
     * @global type $user
     * @global type $db
     * @global type $template
     * @global type $config
     * @global type $phpbb_container
     * @param type $user_id
     * @param type $phpbb_root_path
     * @param type $phpEx
     */
    function setTemplateVars($user_id, $phpbb_root_path, $phpEx)
    {
        global $user, $db, $template, $config, $phpbb_container;

        $sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . $user_id;
        $result = $db->sql_query($sql);
        $member = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$member) {
            trigger_error('NO_USER');
        }

        // What colour is the zebra
        $sql = 'SELECT friend, foe
            FROM ' . ZEBRA_TABLE . "
            WHERE zebra_id = $user_id
                    AND user_id = {$user->data['user_id']}";

        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $friend = ($row['friend']) ? true : false;
        $db->sql_freeresult($result);

        // Only check if the user is logged in
        if ($user->data['is_registered']) {
            if (!class_exists('p_master')) {
                include($phpbb_root_path . 'includes/functions_module.' . $phpEx);
            }
            $module = new p_master();
            $module->list_modules('ucp');
            $module->list_modules('mcp');

            $zebra_enabled = ($module->loaded('ucp_zebra')) ? true : false;
            $friends_enabled = ($module->loaded('ucp_zebra', 'friends')) ? true : false;

            unset($module);
        }

        if ($config['load_onlinetrack']) {
            $sql = 'SELECT MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline
                    FROM ' . SESSIONS_TABLE . "
                    WHERE session_user_id = $user_id";
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            $member['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
            $member['session_viewonline'] = (isset($row['session_viewonline'])) ? $row['session_viewonline'] : 0;
            unset($row);
        }

        // Custom Profile Fields
        $profile_fields = array();
        if ($config['load_cpf_viewprofile']) {
            $cp = $phpbb_container->get('profilefields.manager');
            $profile_fields = $cp->grab_profile_fields_data($user_id);
            $profile_fields = (isset($profile_fields[$user_id])) ? $cp->generate_profile_fields_template_data($profile_fields[$user_id]) : array();
        }

        $template_data = phpbb_show_profile($member, false, false);

        $template_data = array_merge($template_data, array(
            'S_CUSTOM_FIELDS' => (isset($profile_fields['row']) && sizeof($profile_fields['row'])) ? true : false,
            'S_ZEBRA' => ($user->data['user_id'] != $user_id && $user->data['is_registered'] && $zebra_enabled) ? true : false,
            'U_ADD_FRIEND' => (!$friend && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
            'U_REMOVE_FRIEND' => ($friend && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;usernames[]=' . $user_id) : '',
        ));

        if (!empty($profile_fields['row'])) {
            $template_data = array_merge($template_data, $profile_fields['row']);
        }

        $template->assign_block_vars('searchresults', $template_data);

        if (!empty($profile_fields['blockrow'])) {
            foreach ($profile_fields['blockrow'] as $field_data) {
                $template->assign_block_vars('searchresults.custom_fields', $field_data);
            }
        }
    }

    function prepareAgeRange()
    {
        $range = range(self::MIN_AGE_VALUE, self::MAX_AGE_VALUE);
    }
}