<?php
namespace Upswarm\FunctionalTest\Feature;

use Upswarm\FunctionalTest\FunctionalTestCase;

/**
 * @feature HttpServer should handle Http requests
 *          and dispatch messages contaning HttpRequests
 *          to be handled and responded by Controllers.
 */
class HandleHttpRequestsTest extends FunctionalTestCase
{
    public function testHandleHttpRequestsWithController()
    {
        // Given
        $this->haveTheSupervisorIsRunning();
        $this->haveTheServicesRunning(
            'Upswarm\\FunctionalTest\\Fixtures\\HelloWorldWebServerService',
            'Upswarm\\FunctionalTest\\Fixtures\\HelloWorldController'
        );
        $this->wait(1);

        // When
        $this->sendHttpRequest('GET', 'http://127.0.0.1:8081/hello/phpunit');

        // Then
        $this->shouldReceiveResponseWithCode(200);
        $this->shouldReceiveResponseWithContent('Hello phpunit!');
    }
}
