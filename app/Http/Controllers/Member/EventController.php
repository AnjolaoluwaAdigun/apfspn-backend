<?php

namespace App\Http\Controllers\Member;

use App\Enums\EventRegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\Events\CancelEventRegistrationRequest;
use App\Http\Requests\Member\Events\ListMyEventsRequest;
use App\Http\Requests\Member\Events\RegisterForEventRequest;
use App\Http\Resources\EventRegistrationResource;
use App\Services\Events\EventRegistrationService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function __construct(private readonly EventRegistrationService $registrations) {}

    public function index(ListMyEventsRequest $request): JsonResponse
    {
        return JsonResponser::success(EventRegistrationResource::collection($this->registrations->myEvents($request->user(), $request->validated())));
    }

    public function register(RegisterForEventRequest $request, string $event): JsonResponse
    {
        $registration = $this->registrations->register($request->user(), $event);

        $message = $registration->status === EventRegistrationStatus::Waitlisted
            ? 'The event is full. You have been added to the waitlist.'
            : 'You are registered for this event.';

        return JsonResponser::success(new EventRegistrationResource($registration), $message);
    }

    public function cancel(CancelEventRegistrationRequest $request, string $event): JsonResponse
    {
        $registration = $this->registrations->cancel($request->user(), $event);

        return JsonResponser::success(new EventRegistrationResource($registration), 'Your registration has been cancelled.');
    }
}
