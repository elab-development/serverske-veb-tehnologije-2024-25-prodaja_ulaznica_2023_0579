<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TicketTypeController extends Controller
{
    /**
     * Display a listing of the resource for the specified event.
     */
    public function index(Event $event): JsonResponse
    {
        $ticketTypes = $event->ticketTypes()
            ->with('event')
            ->get();

        return response()->json([
            'event_id' => $event->id,
            'ticket_types' => TicketTypeResource::collection($ticketTypes),
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
        $this->ensureValidQuantity($validated);
        $this->ensureValidSaleDateRange($validated);

        $ticketType = TicketType::create($validated)->load('event');

        return response()->json([
            'message' => 'Ticket type created successfully.',
            'ticket_type' => new TicketTypeResource($ticketType),
        ], 201);
    }

    /**
     * Display the specified resource for the specified event.
     */
    public function show(Event $event, TicketType $ticketType): JsonResponse
    {
        if ($ticketType->event_id !== $event->id) {
            abort(404);
        }

        $ticketType->load('event');

        return response()->json([
            'event_id' => $event->id,
            'ticket_type' => new TicketTypeResource($ticketType),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketType $ticketType): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate($this->rules(true));

        if ($validated === []) {
            $ticketType->load('event');

            return response()->json([
                'message' => 'Nothing to update.',
                'ticket_type' => new TicketTypeResource($ticketType),
            ]);
        }

        $this->ensureValidQuantity($validated, $ticketType);
        $this->ensureValidSaleDateRange($validated, $ticketType);

        $ticketType->update($validated);
        $ticketType->load('event');

        return response()->json([
            'message' => 'Ticket type updated successfully.',
            'ticket_type' => new TicketTypeResource($ticketType),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, TicketType $ticketType): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ticketType->delete();

        return response()->json([
            'message' => 'Ticket type deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'event_id' => [$required, 'integer', 'exists:events,id'],
            'name' => [$required, 'string', 'max:255'],
            'price' => [$required, 'numeric', 'min:0'],
            'quantity_total' => [$required, 'integer', 'min:0'],
            'quantity_available' => [$required, 'integer', 'min:0'],
            'sale_starts_at' => ['sometimes', 'nullable', 'date'],
            'sale_ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureValidQuantity(array $validated, ?TicketType $ticketType = null): void
    {
        $quantityTotal = $validated['quantity_total'] ?? $ticketType?->quantity_total;
        $quantityAvailable = $validated['quantity_available'] ?? $ticketType?->quantity_available;

        if ($quantityTotal === null || $quantityAvailable === null) {
            return;
        }

        if ($quantityAvailable > $quantityTotal) {
            throw ValidationException::withMessages([
                'quantity_available' => ['The quantity available must be less than or equal to quantity total.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureValidSaleDateRange(array $validated, ?TicketType $ticketType = null): void
    {
        $saleStartsAt = array_key_exists('sale_starts_at', $validated)
            ? $validated['sale_starts_at']
            : $ticketType?->sale_starts_at;

        $saleEndsAt = array_key_exists('sale_ends_at', $validated)
            ? $validated['sale_ends_at']
            : $ticketType?->sale_ends_at;

        if ($saleStartsAt === null || $saleEndsAt === null) {
            return;
        }

        if (Carbon::parse($saleEndsAt)->lte(Carbon::parse($saleStartsAt))) {
            throw ValidationException::withMessages([
                'sale_ends_at' => ['The sale ends at must be after sale starts at.'],
            ]);
        }
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()?->role === User::ROLE_ADMIN;
    }
}