<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const FINAL_STATUSES = ['paid', 'cancelled'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['sometimes', 'integer', 'exists:events,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        if (! $this->isAdmin($request) && isset($validated['user_id']) && (int) $validated['user_id'] !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Order::query()->with(['user', 'ticketType.event']);

        if (! $this->isAdmin($request)) {
            $query->where('user_id', $user->id);
        } elseif (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (isset($validated['event_id'])) {
            $query->whereHas('ticketType', function ($query) use ($validated): void {
                $query->where('event_id', $validated['event_id']);
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        $orders = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'count' => $orders->count(),
            'total' => $orders->total(),
            'per_page' => $orders->perPage(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'filters' => $request->only([
                'event_id',
                'user_id',
            ]),
            'orders' => OrderResource::collection($orders->getCollection()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'pay_now' => ['required', 'boolean'],
        ]);

        $order = DB::transaction(function () use ($request, $validated): Order {
            $ticketType = TicketType::query()
                ->whereKey($validated['ticket_type_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($ticketType->quantity_available < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ['There are not enough tickets available for this ticket type.'],
                ]);
            }

            $ticketType->decrement('quantity_available', $validated['quantity']);

            $status = $validated['pay_now'] ? 'paid' : 'pending';

            return Order::create([
                'user_id' => $request->user()->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $validated['quantity'],
                'total_price' => $ticketType->price * $validated['quantity'],
                'status' => $status,
                'purchased_at' => $status === 'paid' ? now() : null,
            ]);
        });

        $order->load(['user', 'ticketType.event']);

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if (! $this->canView($request, $order)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load(['user', 'ticketType.event']);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        if (! $this->canView($request, $order)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->ensureOnlyStatusIsSubmitted($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::FINAL_STATUSES)],
        ]);

        $order = DB::transaction(function () use ($order, $validated): Order {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending orders can be updated.'],
                ]);
            }

            if ($validated['status'] === 'cancelled') {
                TicketType::query()
                    ->whereKey($order->ticket_type_id)
                    ->lockForUpdate()
                    ->increment('quantity_available', $order->quantity);
            }

            $order->update([
                'status' => $validated['status'],
                'purchased_at' => $validated['status'] === 'paid' ? now() : null,
            ]);

            return $order;
        });

        $order->load(['user', 'ticketType.event']);

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => new OrderResource($order),
        ]);
    }

    private function canView(Request $request, Order $order): bool
    {
        return $this->isAdmin($request) || $order->user_id === $request->user()?->id;
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()?->role === User::ROLE_ADMIN;
    }

    /**
     * @throws ValidationException
     */
    private function ensureOnlyStatusIsSubmitted(Request $request): void
    {
        $invalidFields = array_diff(array_keys($request->all()), ['status']);

        if ($invalidFields === []) {
            return;
        }

        throw ValidationException::withMessages(
            collect($invalidFields)
                ->mapWithKeys(fn (string $field): array => [
                    $field => ['Only status can be updated on an order.'],
                ])
                ->all()
        );
    }
}