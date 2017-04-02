<?php
namespace Upswarm\FunctionalTest\Feature;

use Upswarm\FunctionalTest\FunctionalTestCase;

/**
 * @feature Services should be able to exchange messages
 *          since each service has it's own local memory and no access
 *          to the state of other services.
 */
class ServicesExchangeMessagesTest extends FunctionalTestCase
{
    public function testSendMessage()
    {
        // Given
        $this->haveTheSupervisorIsRunning();
        $this->haveTheServicesRunning(
            'Upswarm\\FunctionalTest\\Fixtures\\PingService',
            'Upswarm\\FunctionalTest\\Fixtures\\PongService'
        );

        // When
        $this->wait(3);

        // Then
        $this->shouldSeeServiceOutput(
            'Upswarm\\FunctionalTest\\Fixtures\\PingService',
            "Ping sent 1\n".
            "Ping received 1\n".
            "Ping sent 2\n".
            "Ping received 2\n".
            "Ping sent 3\n".
            "Ping received 3"
        );

        $this->shouldSeeServiceOutput(
            'Upswarm\\FunctionalTest\\Fixtures\\PongService',
            "Pong received 1\n".
            "Pong sent 1\n".
            "Pong received 2\n".
            "Pong sent 2\n".
            "Pong received 3\n".
            "Pong sent 3"
        );
    }
}
