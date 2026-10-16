<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Events\CancelEventRequest;
use App\Http\Requests\Admin\Events\DeleteEventRequest;
use App\Http\Requests\Admin\Events\ExportAttendeesRequest;
use App\Http\Requests\Admin\Events\ListAttendeesRequest;
use App\Http\Requests\Admin\Events\ListEventsRequest;
use App\Http\Requests\Admin\Events\MarkAttendedRequest;
use App\Http\Requests\Admin\Events\PublishEventRequest;
use App\Http\Requests\Admin\Events\ShowEventRequest;
use App\Http\Requests\Admin\Events\StoreEventRequest;
use App\Http\Requests\Admin\Events\UpdateEventRequest;
use App\Http\Requests\Admin\Events\WaitlistRequest;
use App\Http\Resources\EventRegistrationResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventRegistrationService $registrations,
    ) {}

    public function index(ListEventsRequest $request): JsonResponse
    {
        return JsonResponser::success(EventResource::collection($this->events->list($request->validated())));
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        return JsonResponser::success(new EventResource($this->events->create($request->validated())), 'Event created.', 201);
    }

    public function show(ShowEventRequest $request, Event $event): JsonResponse
    {
        return JsonResponser::success(new EventResource($this->events->show($event->getKey())));
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $updated = $this->events->update($event->getKey(), $request->validated(), $request->user());

        return JsonResponser::success(new EventResource($updated), 'Event updated.');
    }

    public function destroy(DeleteEventRequest $request, Event $event): JsonResponse
    {
        $this->events->delete($event->getKey());

        return JsonResponser::success(null, 'Event deleted.');
    }

    public function publish(PublishEventRequest $request, Event $event): JsonResponse
    {
        return JsonResponser::success(new EventResource($this->events->publish($event->getKey())), 'Event published.');
    }

    public function cancel(CancelEventRequest $request, Event $event): JsonResponse
    {
        $cancelled = $this->events->cancel($event->getKey(), $request->validated()['reason']);

        return JsonResponser::success(new EventResource($cancelled), 'Event cancelled.');
    }

    public function attendees(ListAttendeesRequest $request, Event $event): JsonResponse
    {
        return JsonResponser::success(EventRegistrationResource::collection($this->registrations->attendees($event->getKey(), $request->validated())));
    }

    public function waitlist(WaitlistRequest $request, Event $event): JsonResponse
    {
        return JsonResponser::success(EventRegistrationResource::collection($this->registrations->waitlist($event->getKey(), $request->validated())));
    }

    public function export(ExportAttendeesRequest $request, Event $event): StreamedResponse
    {
        $rows = $this->registrations->export($event->getKey(), $request->validated());

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, 'apfspn-event-'.$event->slug.'-attendees-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function markAttended(MarkAttendedRequest $request, Event $event, string $registration): JsonResponse
    {
        $attended = $this->registrations->markAttended($event->getKey(), $registration);

        return JsonResponser::success(new EventRegistrationResource($attended->load('user.membership', 'user.organisationProfile')), 'Attendance recorded.');
    }
}
