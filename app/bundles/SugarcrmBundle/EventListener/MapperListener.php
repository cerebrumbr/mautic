<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SugarcrmBundle\EventListener;

use Mautic\MapperBundle\Event\MapperFormEvent;
use Mautic\MapperBundle\Event\MapperDashboardEvent;

class MapperListener
{
    /**
     * Add Sugar CRM to Mapper
     *
     * @param MapperDashboardEvent $event
     */
    public function onFetchIcons(MapperDashboardEvent $event)
    {
        $config = array(
            'name'        => 'Sugar CRM',
            'bundle' => 'sugarcrm',
            'icon'        => 'app/bundles/SugarcrmBundle/Assets/images/sugarcrm_128.png'
        );

        $event->addApplication($config);
    }

    /**
     * Add Sugar CRM fields to register client
     *
     * @param MapperFormEvent $event
     */
    public function onFormBuild(MapperFormEvent $event)
    {
        $data = array();

        $field = array(
            'child' => 'apikeys',
            'type' => 'apikeys',
            'params' => array(
                'label'       => 'mautic.sugarcrm.form.api.keys',
                'required'    => false,
                'label_attr'  => array('class' => 'control-label')
            )
        );

        $event->addField($field);
    }
}