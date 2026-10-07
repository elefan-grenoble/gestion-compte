<?php

namespace App\Tests\Functional\Command;

use App\Tests\Functional\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Base class for the tests of the cron commands: runs a command through
 * CommandTester against the test database, spies on the events it dispatches
 * and on the emails it sends, and lets a test override the environment
 * variables the command's configuration is read from.
 *
 * The database is empty when the class starts (see DatabasePrimer): a test
 * builds the data it needs, with dates of its own rather than the fixtures'.
 *
 * @internal
 */
abstract class CommandTestCase extends FunctionalTestCase
{
    /** @var array<string, null|string> environment as it was before the test, by variable */
    private $originalEnv = [];

    /** @var array<string, object[]> events seen by spyOn(), by event name */
    private $events = [];

    /** @var object */
    private $listeningKernel;

    /** @var Email[] */
    private $emails = [];

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            self::writeEnv($name, $value);
        }
        $this->originalEnv = [];
        $this->events = [];
        $this->emails = [];
        $this->listeningKernel = null;

        parent::tearDown();
    }

    /**
     * Overrides an environment variable for the rest of the test. Call it
     * before the first runCommand(): the container reads the variable when it
     * is built.
     */
    protected function withEnv(string $name, string $value): void
    {
        if (!array_key_exists($name, $this->originalEnv)) {
            $original = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
            $this->originalEnv[$name] = false === $original ? null : (string) $original;
        }
        self::writeEnv($name, $value);
    }

    /**
     * Runs the command in the kernel booted for the test and returns the
     * tester, whose display and status code the test asserts on.
     *
     * @param array<string, mixed> $input arguments and options, as for CommandTester::execute()
     */
    protected function runCommand(string $name, array $input = []): CommandTester
    {
        // static::$kernel outlives the shutdown of the previous test: the container tells whether it is booted.
        $kernel = null !== static::$container ? static::$kernel : static::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);

        if ($this->listeningKernel !== $kernel) {
            $this->listenTo($kernel);
            $this->listeningKernel = $kernel;
        }

        $tester = new CommandTester($application->find($name));
        $tester->execute($input);

        return $tester;
    }

    /**
     * Records the events of the given name dispatched from now on; the
     * recorded events are returned by dispatched(), in dispatch order.
     */
    protected function spyOn(string $eventName): void
    {
        $this->events[$eventName] = [];
    }

    /**
     * @return object[]
     */
    protected function dispatched(string $eventName): array
    {
        return $this->events[$eventName] ?? [];
    }

    /**
     * @return Email[] the emails sent through the mailer since the test began
     */
    protected function sentEmails(): array
    {
        return $this->emails;
    }

    private function listenTo($kernel): void
    {
        $dispatcher = $kernel->getContainer()->get('event_dispatcher');

        foreach (array_keys($this->events) as $eventName) {
            $dispatcher->addListener($eventName, function ($event) use ($eventName) {
                $this->events[$eventName][] = $event;
            }, 1000);
        }

        $dispatcher->addListener(MessageEvent::class, function (MessageEvent $event) {
            $message = $event->getMessage();
            if ($message instanceof Email) {
                $this->emails[] = $message;
            }
        });
    }

    private static function writeEnv(string $name, ?string $value): void
    {
        if (null === $value) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }

        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}
