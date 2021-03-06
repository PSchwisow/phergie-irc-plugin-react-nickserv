<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link https://github.com/phergie/phergie-irc-plugin-react-nickserv for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license Simplified BSD License
 * @package Phergie\Irc\Plugin\React\NickServ
 */

namespace Phergie\Irc\Tests\Plugin\React\NickServ;

use Phake;
use Phergie\Irc\Event\UserEventInterface;
use Phergie\Irc\Event\ServerEventInterface;
use Phergie\Irc\Bot\React\EventQueueInterface;
use Phergie\Irc\Plugin\React\NickServ\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Phergie
 * @package Phergie\Irc\Plugin\React\NickServ
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Instance of the class under test
     *
     * @var \Phergie\Irc\Plugin\React\NickServ\Plugin
     */
    protected $plugin;

    /**
     * Mock user event
     *
     * @var \Phergie\Irc\Event\UserEventInterface
     */
    protected $event;

    /**
     * Mock event queue
     *
     * @var \Phergie\Irc\Bot\React\EventQueueInterface
     */
    protected $queue;

    /**
     * Mock connection
     *
     * @var \Phergie\Irc\ConnectionInterface
     */
    protected $connection;

    /**
     * Mock connection
     *
     * @var \Evenement\EventEmitterInterface
     */
    protected $emitter;

    /**
     * Instantiates the class under test.
     */
    protected function setUp()
    {
        $this->plugin = new Plugin(array('password' => 'password'));
        $this->connection = $this->getMockConnection();
        $this->event = $this->getMockUserEvent();
        Phake::when($this->event)->getConnection()->thenReturn($this->connection);
        $this->queue = $this->getMockQueue();
        $this->emitter = $this->getMockEventEmitter();
        $this->plugin->setEventEmitter($this->emitter);
    }

    /**
     * Tests that notices that are not from the NickServ agent are ignored.
     */
    public function testHandleNoticeFromNonNickServUser()
    {
        Phake::when($this->event)->getNick()->thenReturn('foo');
        Phake::verifyNoFurtherInteraction($this->queue);
        $this->plugin->handleNotice($this->event, $this->queue);
    }

    /**
     * Tests that irrelevant notices from the NickServ agent are ignored.
     */
    public function testHandleNoticeWithIrrelevantNoticeFromNickServ()
    {
        Phake::when($this->event)->getParams()->thenReturn(['text' => 'You are already logged in as Phergie']);
        Phake::verifyNoFurtherInteraction($this->queue);
        $this->plugin->handleNotice($this->event, $this->queue);
    }

    /**
     * Tests that identity confirmation notices from the NickServ emit an event.
     */
    public function testHandleNoticeWithIdentityConfirmation()
    {
        Phake::when($this->event)->getParams()->thenReturn(['text' => 'You are now identified for Phergie']);
        $this->plugin->handleNotice($this->event, $this->queue);
        Phake::verify($this->emitter)->emit('nickserv.confirmed', [$this->connection]);
    }

    /**
     * Tests that authentication requests are handled.
     */
    public function testHandleNoticeWithAuthenticationRequest()
    {
        $text = 'This nickname is registered. Please choose a different nickname, or identify via /msg NickServ identify <password>.';
        Phake::when($this->event)->getParams()->thenReturn(array('text' => $text));
        $this->plugin->handleNotice($this->event, $this->queue);
        Phake::verify($this->queue)->ircPrivmsg('NickServ', 'IDENTIFY Phergie password');
    }

    /**
     * Tests that ghost connection kills are handled.
     */
    public function testHandleNoticeWithGhostKillNotification()
    {
        $text = 'Phergie has been ghosted.';
        Phake::when($this->event)->getParams()->thenReturn(array('text' => $text));
        $this->plugin->handleNotice($this->event, $this->queue);
        Phake::verify($this->queue)->ircNick('Phergie');
    }

    /**
     * Tests that irrelevant QUIT events are ignored.
     */
    public function testHandleQuitWithIrrelevantEvent()
    {
        Phake::verifyNoFurtherInteraction($this->queue);
        $this->plugin->handleQuit($this->event, $this->queue);
    }

    /**
     * Tests that the bot reclaims its nick if a user using it quits.
     */
    public function testHandleQuitWithRelevantEvent()
    {
        Phake::when($this->event)->getNick()->thenReturn('Phergie');
        $this->plugin->handleQuit($this->event, $this->queue);
        Phake::verify($this->queue)->ircNick('Phergie');
    }

    /**
     * Tests that irrelevant NICK events are ignored.
     */
    public function testHandleNickWithIrrelevantEvent()
    {
        Phake::verifyNoFurtherInteraction($this->queue);
        $this->plugin->handleNick($this->event, $this->queue);
    }

    /**
     * Tests that the bot updates its nick if it is successfully changed on the
     * server.
     */
    public function testHandleNickWithRelevantEvent()
    {
        Phake::when($this->event)->getNick()->thenReturn('Phergie');
        Phake::when($this->event)->getParams()->thenReturn(array('nickname' => 'Phergie'));
        $this->plugin->handleNick($this->event, $this->queue);
        Phake::verify($this->connection)->setNickname('Phergie');
    }

    /**
     * Tests that the bot attempts to kill ghost connections.
     */
    public function testHandleNicknameInUse()
    {
        $event = Phake::mock('\Phergie\Irc\Event\ServerEventInterface');
        Phake::when($event)->getConnection()->thenReturn($this->connection);
        $this->plugin->handleNicknameInUse($event, $this->queue);
        Phake::inOrder(
            Phake::verify($this->queue)->ircNick('Phergie_'),
            Phake::verify($this->queue)->ircPrivmsg('NickServ', 'GHOST Phergie password')
        );
    }

    /**
     * Data provider for testInvalidConfiguration().
     *
     * @return array
     */
    public function dataProviderInvalidConfiguration()
    {
        $config = array('password' => 'password');

        $data = array();

        $error = 'password must be a non-empty string';
        $data[] = array(array('password' => 1), $error);
        $data[] = array(array('password' => ''), $error);

        return $data;
    }

    /**
     * Tests the plugin handling invalid configuration.
     *
     * @param array $config
     * @param string $error
     * @dataProvider dataProviderInvalidConfiguration
     */
    public function testInvalidConfiguration(array $config, $error)
    {
        try {
            $plugin = new Plugin($config);
            $this->fail('Expected exception was not thrown');
        } catch (\DomainException $e) {
            $this->assertSame($error, $e->getMessage());
        }
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

    /**
     * Returns a mock event.
     *
     * @return \Phergie\Irc\Event\UserEventInterface
     */
    protected function getMockUserEvent()
    {
        $event = Phake::mock('\Phergie\Irc\Event\UserEventInterface');
        Phake::when($event)->getNick()->thenReturn('NickServ');
        return $event;
    }

    /**
     * Returns a mock event queue.
     *
     * @return \Phergie\Irc\Bot\React\EventQueueInterface
     */
    protected function getMockQueue()
    {
        return Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
    }

    /**
     * Returns a mock connection.
     *
     * @return \Phergie\Irc\ConnectionInterface
     */
    protected function getMockConnection()
    {
        $connection = Phake::mock('\Phergie\Irc\ConnectionInterface');
        Phake::when($connection)->getNickname()->thenReturn('Phergie');
        return $connection;
    }

    /**
     * Returns a mock event emitter.
     *
     * @return \Evenement\EventEmitterInterface
     */
    protected function getMockEventEmitter()
    {
        return Phake::mock('Evenement\EventEmitterInterface');
    }
}
