<?php

namespace Webkul\Shop\Http\Controllers\Customer\Account;

use Illuminate\View\View;
use Webkul\Customer\Repositories\CustomerNoteRepository;
use Webkul\Shop\Http\Controllers\Controller;

class NotificationController extends Controller
{
    /**
     * The customer note repository.
     */
    protected CustomerNoteRepository $customerNoteRepository;

    /**
     * Create a new controller instance.
     */
    public function __construct(CustomerNoteRepository $customerNoteRepository)
    {
        $this->customerNoteRepository = $customerNoteRepository;
    }

    /**
     * Display notifications sent to the signed-in customer by the store.
     */
    public function index(): View
    {
        $notifications = $this->customerNoteRepository
            ->findWhere([
                'customer_id' => auth()->guard('customer')->id(),
                'customer_notified' => true,
            ])
            ->sortByDesc('created_at')
            ->values();

        foreach ($notifications->whereNull('customer_read_at') as $notification) {
            $this->customerNoteRepository->update([
                'customer_read_at' => now(),
            ], $notification->id);
        }

        return view('shop::customers.account.notifications.index', compact('notifications'));
    }
}
