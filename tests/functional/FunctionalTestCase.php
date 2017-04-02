<?php
namespace Upswarm\FunctionalTest;

use PHPUnit_Framework_IncompleteTestError;
use PHPUnit_Framework_TestCase;
use Exception;
use ReflectionClass;

/**
 * Functional test class helps when testing a feature or a behavior of a set
 * of components working together.
 */
class FunctionalTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * Used for the visual reporting at tearDownAfterClass
     * @var string
     */
    public static $testOutput;

    /**
     * Pid of the supervisor process
     * @var string
     */
    public $supervisorPid;

    /**
     * Services running
     * @var array
     * @example [
     *     <pid> => <serviceName>,
     *     <pid> => <serviceName>,
     *     <pid> => <serviceName>,
     * ];
     */
    public $servicesRunning;

    /**
     * Setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * After each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->stopSupervisor();
        $this->stopServices();
    }

    /**
     * Prepares the test output based in the @feature annotation or Class name.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $docs = (new ReflectionClass(static::class))->getDocComment();
        preg_match('/@feature ([^@\/]+)/', $docs, $matches);
        $featureAnnotation = trim(str_replace('*', '', preg_replace('/ +/', ' ', $matches[1] ?? "")));

        echo "\n ";
        static::$testOutput = "\n\033[32m [✓] $featureAnnotation\033[0m\n";
    }

    /**
     * Prepares the test output string in case a test was not successfull.
     *
     * @param  Exception $e Test failure.
     *
     * @throws Exception Always.
     *
     * @return void
     */
    protected function onNotSuccessfulTest(Exception $e)
    {
        if ($e instanceof PHPUnit_Framework_IncompleteTestError) {
            static::$testOutput = str_replace('[32m [✓]', '[33m [ ]', static::$testOutput);
        }

        static::$testOutput = str_replace('[32m [✓]', '[31m [×]', static::$testOutput);

        throw $e;
    }

    /**
     * Prints out the feature annotation (if present) with the status of the
     * given test class.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        echo static::$testOutput;
    }

    /**
     * When an instruction that was not implemented is called
     *
     * @param  string $method Method name.
     * @param  array  $args   Method params.
     *
     * @throws Exception Always.
     *
     * @return void
     */
    public function __call(string $method, array $args)
    {
        $this->markTestIncomplete("Test instruction '$method' is not implemented");
    }

    protected function haveTheSupervisorIsRunning()
    {
        $this->supervisorPid = exec(
            'php upswarm serve > tests/.output/supervisor.log 2>&1 & echo $!',
            $output
        );

        sleep(1);
    }

    protected function haveTheServicesRunning(string ...$services)
    {
        foreach ($services as $service) {
            $serviceLogName = str_replace('\\', '_', $service);
            $service = str_replace('\\', '\\\\', $service);
            $pid = exec("php upswarm spawn $service > tests/.output/$serviceLogName.log 2>&1 & echo $!");

            $this->servicesRunning[$pid] = $service;
        }
    }

    protected function wait($seconds)
    {
        sleep($seconds);
    }

    protected function shouldSeeServiceOutput($service, $content)
    {
        $serviceLogName = str_replace('\\', '_', $service);

        $this->assertContains(
            $content,
            file_get_contents("tests/.output/$serviceLogName.log"),
            "Couldn't find '$content' in '$serviceLogName' output."
        );
    }

    protected function stopSupervisor()
    {
        exec("kill {$this->supervisorPid} > /dev/null 2>&1");
    }

    protected function stopServices()
    {
        foreach ($this->servicesRunning as $pid => $service) {
            exec("kill $pid > /dev/null 2>&1");
        }
    }
}
