<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreUpdater\tests\Integration;

use Piwik\Config;
use Piwik\Option;
use Piwik\Plugins\CoreUpdater\UpdateCommunication;
use Piwik\Tests\Framework\Fixture;
use Piwik\UpdateCheck;
use Piwik\Version;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\View;

/**
 * @group Plugins
 */
class UpdateCommunicationTest extends IntegrationTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function test_isEnabled()
    {
        $updateCommunication = new UpdateCommunication();

        $this->assertTrue($updateCommunication->isEnabled());

        Config::getInstance()->General['enable_update_communication'] = 0;
        $this->assertFalse($updateCommunication->isEnabled());

        Config::getInstance()->General['enable_update_communication'] = 1;
        $this->assertTrue($updateCommunication->isEnabled());
    }

    /**
     * @dataProvider provideSendNotificationData
     */
    public function test_sendNotificationIfUpdateAvailable($latestVersion, $lastSentVersion, $expects, $expectedLastSentVersion)
    {
        $this->setLatestVersion($latestVersion);
        $this->setLastSentVersion($lastSentVersion);

        $mock = $this->getCommunicationMock(array('sendNotifications'));
        $mock->expects($expects)->method('sendNotifications');
        $mock->sendNotificationIfUpdateAvailable();

        $this->assertEquals($expectedLastSentVersion, $this->getLastSentVersion());
    }

    public function provideSendNotificationData()
    {
        return array(
            array(Version::VERSION, false, $this->never(), false), // shouldNotSend_IfNoUpdateAvailable
            array('33.0.0', '33.0.0', $this->never(), '33.0.0'),   // shouldNotSend_IfAlreadyNotified
            array('31.0.0', '33.0.0', $this->never(), '33.0.0'),   // shouldNotSend_IfAlreadyNotifiedAboutLaterRelease
            array('3333.3333.3333-bbeta10', '31.0.0', $this->never(), '31.0.0'),  // shouldNotSend_IfLatestVersionIsNotVersionLike,
            array('33.0.0', false,    $this->once(), '33.0.0'),    // shouldSend_IfUpdateAvailableAndNeverSentAnyBefore
            array('33.0.0', '31.0.0', $this->once(), '33.0.0'),    // shouldSend_IfUpdateAvailable
        );
    }

    public function test_sendNotifications_shouldSentCorrectEmail()
    {
        $rootUrl = Fixture::getTestRootUrl();
        $message = "<p>ScheduledReports_EmailHello</p>

<p>CoreUpdater_ThereIsNewVersionAvailableForUpdate</p>

<p>CoreUpdater_YouCanUpgradeAutomaticallyOrDownloadPackage<br/>
{$rootUrl}index.php?module=CoreUpdater&action=newVersionAvailable
</p>

<p>
    CoreUpdater_ViewVersionChangelog
    <br/>
    https://matomo.org/changelog/matomo-33-0-0/
</p>

<p>CoreUpdater_ReceiveEmailBecauseIsSuperUser</p>
<p>CoreUpdater_FeedbackRequest<br/>https://matomo.org/contact/</p>
";

        $this->assertEmailForVersion('33.0.0', $message);
    }

    public function test_sendNotifications_shouldNotIncludeChangelogIfNotMajorVersionUpdate()
    {
        $rootUrl = Fixture::getTestRootUrl();
        $message = "<p>ScheduledReports_EmailHello</p>

<p>CoreUpdater_ThereIsNewVersionAvailableForUpdate</p>

<p>CoreUpdater_YouCanUpgradeAutomaticallyOrDownloadPackage<br/>
{$rootUrl}index.php?module=CoreUpdater&action=newVersionAvailable
</p>


<p>CoreUpdater_ReceiveEmailBecauseIsSuperUser</p>
<p>CoreUpdater_FeedbackRequest<br/>https://matomo.org/contact/</p>
";

        $this->assertEmailForVersion('33.0.0-b1', $message);
    }

    private function assertEmailForVersion($version, $expectedMessage)
    {
        $this->setLatestVersion($version);

        $subject = 'CoreUpdater_NotificationSubjectAvailableCoreUpdate';

        $mock = $this->getCommunicationMock(array('sendEmailNotification'));
        $mock->expects($this->once())
            ->method('sendEmailNotification')
            ->with($this->equalTo($subject), $this->callback(function (View $view) use ($expectedMessage) {
                $this->assertEquals($expectedMessage, $view->render());
                return true;
            }));
        $mock->sendNotificationIfUpdateAvailable();
    }

    private function setLastSentVersion($value)
    {
        Option::set('last_update_communication_sent_core', $value);
    }

    private function getLastSentVersion()
    {
        return Option::get('last_update_communication_sent_core');
    }

    private function setLatestVersion($value)
    {
        $this->preventVersionIsOverwrittenByActualVersionCheck();
        Option::set(UpdateCheck::LATEST_VERSION, $value);
    }

    private function preventVersionIsOverwrittenByActualVersionCheck()
    {
        Config::getInstance()->General['enable_auto_update'] = false;
    }

    /**
     * @param array $methodsToOverwrite
     * @return UpdateCommunication
     */
    private function getCommunicationMock($methodsToOverwrite)
    {
        return $this->getMockBuilder('\Piwik\Plugins\CoreUpdater\UpdateCommunication')
                    ->setMethods($methodsToOverwrite)
                    ->getMock();
    }
}
