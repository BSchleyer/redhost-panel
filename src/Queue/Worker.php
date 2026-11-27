<?php

declare(strict_types=1);

namespace Nebrix\Queue;

use Nebrix\Core\Container;
use Nebrix\Mail\Mailer;
use Nebrix\Provisioning\RetryLaterException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Processes queued jobs. Started by `bin/nebrix queue:work` (supervisor,
 * systemd or the docker-compose "worker" service).
 */
class Worker
{
    /**
     * @param array<string, class-string<JobHandler>> $handlers
     */
    public function __construct(
        private readonly JobQueue $queue,
        private readonly Container $container,
        private readonly LoggerInterface $logger,
        private readonly array $handlers,
    ) {
    }

    /**
     * Processes at most one job.
     *
     * @return bool whether a job was processed
     */
    public function runOnce(string $workerId): bool
    {
        $job = $this->queue->reserve($workerId);
        if ($job === null) {
            return false;
        }

        $class = $this->handlers[$job['type']] ?? null;
        if ($class === null) {
            $this->queue->fail($job['id'], 'No handler registered for job type ' . $job['type'], true);

            return true;
        }

        /** @var JobHandler $handler */
        $handler = $this->container->get($class);

        try {
            $handler->handle($job['payload']);
            // Queued e-mails may contain single-use links; drop the payload once delivered.
            $this->queue->complete($job['id'], $job['type'] === Mailer::JOB_TYPE);
        } catch (RetryLaterException $retry) {
            $this->queue->release($job['id'], $retry->delaySeconds);
        } catch (Throwable $exception) {
            $permanent = $exception instanceof PermanentJobFailure;
            $message = $exception->getMessage();
            $this->queue->fail($job['id'], $message, $permanent);
            $this->logger->warning('Job failed', ['job' => $job['id'], 'type' => $job['type'], 'attempt' => $job['attempts'], 'message' => $message]);

            if (($permanent || $job['attempts'] >= $job['max_attempts']) && $handler instanceof FailureAwareJobHandler) {
                try {
                    $handler->failed($job['payload'], $message);
                } catch (Throwable $hookError) {
                    $this->logger->error('Job failure hook failed', ['job' => $job['id'], 'message' => $hookError->getMessage()]);
                }
            }
        }

        return true;
    }

    /**
     * @param callable(): bool $shouldStop
     * @return int number of processed jobs
     */
    public function run(string $workerId, int $maxJobs, int $maxSeconds, int $sleepSeconds, callable $shouldStop): int
    {
        $started = time();
        $processed = 0;
        $this->queue->releaseStale();

        while (!$shouldStop() && ($maxJobs === 0 || $processed < $maxJobs) && ($maxSeconds === 0 || time() - $started < $maxSeconds)) {
            if ($this->runOnce($workerId)) {
                $processed++;
                continue;
            }
            sleep($sleepSeconds);
        }

        return $processed;
    }
}
