<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Entity\PointsChangeLog;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;

/**
 * Class CampaignSubscriber
 *
 * @package Mautic\LeadBundle\EventListener
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * @var IpLookupHelper
     */
    protected $ipLookupHelper;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var FieldModel
     */
    protected $leadFieldModel;

    /**
     * CampaignSubscriber constructor.
     *
     * @param MauticFactory  $factory
     * @param IpLookupHelper $ipLookupHelper
     * @param LeadModel      $leadModel
     * @param FieldModel     $leadFieldModel
     */
    public function __construct(MauticFactory $factory, IpLookupHelper $ipLookupHelper, LeadModel $leadModel, FieldModel $leadFieldModel)
    {
        $this->ipLookupHelper = $ipLookupHelper;
        $this->leadModel      = $leadModel;
        $this->leadFieldModel = $leadFieldModel;

        parent::__construct($factory);
    }

    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD         => ['onCampaignBuild', 0],
            LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION    => [
                ['onCampaignTriggerActionChangePoints', 0],
                ['onCampaignTriggerActionChangeLists', 1],
                ['onCampaignTriggerActionUpdateLead', 2],
                ['onCampaignTriggerActionUpdateTags', 3]
            ],
            LeadEvents::ON_CAMPAIGN_TRIGGER_CONDITION => ['onCampaignTriggerCondition', 0]
        ];
    }

    /**
     * Add event triggers and actions
     *
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        //Add actions
        $action = [
            'label'       => 'mautic.lead.lead.events.changepoints',
            'description' => 'mautic.lead.lead.events.changepoints_descr',
            'formType'    => 'leadpoints_action',
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION
        ];
        $event->addAction('lead.changepoints', $action);

        $action = [
            'label'       => 'mautic.lead.lead.events.changelist',
            'description' => 'mautic.lead.lead.events.changelist_descr',
            'formType'    => 'leadlist_action',
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION
        ];
        $event->addAction('lead.changelist', $action);

        $action = [
            'label'       => 'mautic.lead.lead.events.updatelead',
            'description' => 'mautic.lead.lead.events.updatelead_descr',
            'formType'    => 'updatelead_action',
            'formTheme'   => 'MauticLeadBundle:FormTheme\ActionUpdateLead',
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION
        ];
        $event->addAction('lead.updatelead', $action);

        $action = [
            'label'       => 'mautic.lead.lead.events.changetags',
            'description' => 'mautic.lead.lead.events.changetags_descr',
            'formType'    => 'modify_lead_tags',
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION
        ];
        $event->addAction('lead.changetags', $action);

        $trigger = [
            'label'       => 'mautic.lead.lead.events.field_value',
            'description' => 'mautic.lead.lead.events.field_value_descr',
            'formType'    => 'campaignevent_lead_field_value',
            'formTheme'   => 'MauticLeadBundle:FormTheme\FieldValueCondition',
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_CONDITION
        ];
        $event->addLeadCondition('lead.field_value', $trigger);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerActionChangePoints(CampaignExecutionEvent $event)
    {
        if (!$event->checkContext('lead.changepoints')) {

            return;
        }

        $lead   = $event->getLead();
        $points = $event->getConfig()['points'];

        $somethingHappened = false;

        if ($lead !== null && !empty($points)) {
            $lead->addToPoints($points);

            //add a lead point change log
            $log = new PointsChangeLog();
            $log->setDelta($points);
            $log->setLead($lead);
            $log->setType('campaign');
            $log->setEventName("{$event->getEvent()['campaign']['id']}: {$event->getEvent()['campaign']['name']}");
            $log->setActionName("{$event->getEvent()['id']}: {$event->getEvent()['name']}");
            $log->setIpAddress($this->ipLookupHelper->getIpAddress());
            $log->setDateAdded(new \DateTime());
            $lead->addPointsChangeLog($log);

            $this->leadModel->saveEntity($lead);
            $somethingHappened = true;
        }

        return $event->setResult($somethingHappened);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerActionChangeLists(CampaignExecutionEvent $event)
    {
        if (!$event->checkContext('lead.changelist')) {

            return;
        }

        $addTo      = $event->getConfig()['addToLists'];
        $removeFrom = $event->getConfig()['removeFromLists'];

        $lead              = $event->getLead();
        $somethingHappened = false;

        if (!empty($addTo)) {
            $this->leadModel->addToLists($lead, $addTo);
            $somethingHappened = true;
        }

        if (!empty($removeFrom)) {
            $this->leadModel->removeFromLists($lead, $removeFrom);
            $somethingHappened = true;
        }

        return $event->setResult($somethingHappened);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerActionUpdateLead(CampaignExecutionEvent $event)
    {
        if (!$event->checkContext('lead.updatelead')) {

            return;
        }

        $lead = $event->getLead();

        $this->leadModel->setFieldValues($lead, $event->getConfig(), false);
        $this->leadModel->saveEntity($lead);

        return $event->setResult(true);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerActionUpdateTags(CampaignExecutionEvent $event)
    {
        if (!$event->checkContext('lead.changetags')) {

            return;
        }

        $config = $event->getConfig();
        $lead   = $event->getLead();

        $addTags    = (!empty($config['add_tags'])) ? $config['add_tags'] : [];
        $removeTags = (!empty($config['remove_tags'])) ? $config['remove_tags'] : [];

        $this->leadModel->modifyTags($lead, $addTags, $removeTags);

        return $event->setResult(true);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerCondition(CampaignExecutionEvent $event)
    {
        $lead = $event->getLead();

        if (!$lead || !$lead->getId()) {
            return $event->setResult(false);
        }

        $operators = $this->leadModel->getFilterExpressionFunctions();

        $result = $this->leadFieldModel->getRepository()->compareValue(
            $lead->getId(),
            $event->getConfig()['field'],
            $event->getConfig()['value'],
            $operators[$event->getConfig()['operator']]['expr']
        );

        return $event->setResult($result);
    }
}
