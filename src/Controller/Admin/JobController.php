<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Queue\JobQueue;

final class JobController extends Controller
{
    public function __construct(private readonly JobQueue $jobs)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status');
        $allowed = [JobQueue::STATUS_PENDING, JobQueue::STATUS_RUNNING, JobQueue::STATUS_COMPLETED, JobQueue::STATUS_FAILED, JobQueue::STATUS_CANCELLED];
        $status = in_array($status, $allowed, true) ? $status : '';
        $page = $this->page($request);

        return $this->render($request, 'admin/jobs/index.twig', [
            'result' => $this->jobs->paginate($status, $page),
            'counts' => $this->jobs->counts(),
            'status' => $status,
            'page' => $page,
        ]);
    }

    public function retry(Request $request, int $id): Response
    {
        $job = $this->jobs->find($id) ?? throw HttpException::notFound();
        if ($this->jobs->retry($id)) {
            $this->audit($request, 'job.retried', 'job', $id, ['type' => $job['type']]);
            $this->flash($request, 'success', 'The job has been queued again.');
        } else {
            $this->flash($request, 'error', 'Only failed or cancelled jobs can be retried.');
        }

        return $this->redirect('/admin/jobs');
    }

    public function cancel(Request $request, int $id): Response
    {
        $job = $this->jobs->find($id) ?? throw HttpException::notFound();
        if ($this->jobs->cancel($id)) {
            $this->audit($request, 'job.cancelled', 'job', $id, ['type' => $job['type']]);
            $this->flash($request, 'success', 'The job has been cancelled.');
        } else {
            $this->flash($request, 'error', 'Only pending or failed jobs can be cancelled.');
        }

        return $this->redirect('/admin/jobs');
    }
}
