<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        if (! $event->order->user) {
            return;
        }

        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}
