<?php
/**
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\SmsBundle\EventListener;

use Mautic\AssetBundle\Helper\TokenHelper as AssetTokenHelper;
use Mautic\PageBundle\Entity\Trackable;
use Mautic\PageBundle\Helper\TokenHelper as PageTokenHelper;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\TokenHelper;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\SmsBundle\Event\SmsEvent;
use Mautic\SmsBundle\SmsEvents;

/**
 * Class CampaignSubscriber.
 */
class SmsSubscriber extends CommonSubscriber
{
    /**
     * @var TrackableModel
     */
    protected $trackableModel;

    /**
     * @var PageTokenHelper
     */
    protected $pageTokenHelper;

    /**
     * @var AssetTokenHelper
     */
    protected $assetTokenHelper;

    /**
     * DynamicContentSubscriber constructor.
     *
     * @param MauticFactory    $factory
     * @param TrackableModel   $trackableModel
     * @param PageTokenHelper  $pageTokenHelper
     * @param AssetTokenHelper $assetTokenHelper
     */
    public function __construct(
        MauticFactory $factory,
        TrackableModel $trackableModel,
        PageTokenHelper $pageTokenHelper,
        AssetTokenHelper $assetTokenHelper
    ) {
        $this->trackableModel   = $trackableModel;
        $this->pageTokenHelper  = $pageTokenHelper;
        $this->assetTokenHelper = $assetTokenHelper;

        parent::__construct($factory);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            SmsEvents::SMS_POST_SAVE     => ['onPostSave', 0],
            SmsEvents::SMS_POST_DELETE   => ['onDelete', 0],
            SmsEvents::TOKEN_REPLACEMENT => ['onTokenReplacement', 0],
        ];
    }

    /**
     * Add an entry to the audit log.
     *
     * @param SmsEvent $event
     */
    public function onPostSave(SmsEvent $event)
    {
        $entity = $event->getSms();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'   => 'sms',
                'object'   => 'sms',
                'objectId' => $entity->getId(),
                'action'   => ($event->isNew()) ? 'create' : 'update',
                'details'  => $details,
            ];
            $this->factory->getModel('core.auditLog')->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log.
     *
     * @param SmsEvent $event
     */
    public function onDelete(SmsEvent $event)
    {
        $entity = $event->getSms();
        $log    = [
            'bundle'   => 'sms',
            'object'   => 'sms',
            'objectId' => $entity->getId(),
            'action'   => 'delete',
            'details'  => ['name' => $entity->getName()],
        ];
        $this->factory->getModel('core.auditLog')->writeToLog($log);
    }

    /**
     * @param TokenReplacementEvent $event
     */
    public function onTokenReplacement(TokenReplacementEvent $event)
    {
        /** @var Lead $lead */
        $lead         = $event->getLead();
        $content      = $event->getContent();
        $clickthrough = $event->getClickthrough();

        if ($content) {
            $tokens = array_merge(
                TokenHelper::findLeadTokens($content, $lead->getProfileFields()),
                $this->pageTokenHelper->findPageTokens($content, $clickthrough),
                $this->assetTokenHelper->findAssetTokens($content, $clickthrough)
            );

            list($content, $trackables) = $this->trackableModel->parseContentForTrackables(
                $content,
                $tokens,
                'sms',
                $clickthrough['channel'][1]
            );

            /**
             * @var string
             * @var Trackable $trackable
             */
            foreach ($trackables as $token => $trackable) {
                $tokens[$token] = $this->trackableModel->generateTrackableUrl($trackable, $clickthrough);
            }

            $content = str_replace(array_keys($tokens), array_values($tokens), $content);

            $event->setContent($content);
        }
    }
}
