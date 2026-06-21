<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private const STATUSES = ['draft', 'published', 'cancelled'];

    private const SORTABLE_FIELDS = [
        'title',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'location' => ['sometimes', 'string', 'max:255'],
            'starts_from' => ['sometimes', 'date'],
            'starts_until' => ['sometimes', 'date'],
            'ends_from' => ['sometimes', 'date'],
            'ends_until' => ['sometimes', 'date'],
            'sort_by' => ['sometimes', Rule::in(self::SORTABLE_FIELDS)],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = Event::query()->with('ticketTypes');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('ticketTypes', function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['location'])) {
            $query->where('location', 'like', "%{$validated['location']}%");
        }

        if (isset($validated['starts_from'])) {
            $query->where('starts_at', '>=', $validated['starts_from']);
        }

        if (isset($validated['starts_until'])) {
            $query->where('starts_at', '<=', $validated['starts_until']);
        }

        if (isset($validated['ends_from'])) {
            $query->where('ends_at', '>=', $validated['ends_from']);
        }

        if (isset($validated['ends_until'])) {
            $query->where('ends_at', '<=', $validated['ends_until']);
        }

        $events = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'count' => $events->count(),
            'total' => $events->total(),
            'per_page' => $events->perPage(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'sort' => [
                'by' => $sortBy,
                'direction' => $sortDirection,
            ],
            'filters' => $request->only([
                'search',
                'status',
                'location',
                'starts_from',
                'starts_until',
                'ends_from',
                'ends_until',
            ]),
            'events' => EventResource::collection($events->getCollection()),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'events-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'title',
                'description',
                'location',
                'starts_at',
                'ends_at',
                'status',
                'ticket_types_count',
                'tickets_total',
                'tickets_available',
                'created_at',
                'updated_at',
            ]);

            Event::query()
                ->withCount('ticketTypes')
                ->withSum('ticketTypes as tickets_total', 'quantity_total')
                ->withSum('ticketTypes as tickets_available', 'quantity_available')
                ->orderBy('created_at')
                ->chunk(200, function ($events) use ($handle): void {
                    foreach ($events as $event) {
                        fputcsv($handle, [
                            $event->id,
                            $event->title,
                            $event->description,
                            $event->location,
                            $event->starts_at?->toDateTimeString(),
                            $event->ends_at?->toDateTimeString(),
                            $event->status,
                            $event->ticket_types_count,
                            $event->tickets_total ?? 0,
                            $event->tickets_available ?? 0,
                            $event->created_at?->toDateTimeString(),
                            $event->updated_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate($this->rules());
        $this->ensureValidDateRange($validated);
        $validated['status'] ??= 'draft';

        $event = Event::create($validated)->load('ticketTypes');

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => new EventResource($event),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('ticketTypes');

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate($this->rules(true));

        if ($validated === []) {
            $event->load('ticketTypes');

            return response()->json([
                'message' => 'Nothing to update.',
                'event' => new EventResource($event),
            ]);
        }

        $this->ensureValidDateRange($validated, $event);

        $event->update($validated);
        $event->load('ticketTypes');

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => [$required, 'string', 'max:255'],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureValidDateRange(array $validated, ?Event $event = null): void
    {
        $startsAt = Carbon::parse($validated['starts_at'] ?? $event?->starts_at);
        $endsAt = array_key_exists('ends_at', $validated) ? $validated['ends_at'] : $event?->ends_at;

        if ($endsAt === null) {
            return;
        }

        if (Carbon::parse($endsAt)->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['The ends at must be after starts at.'],
            ]);
        }
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()?->role === User::ROLE_ADMIN;
    }
}