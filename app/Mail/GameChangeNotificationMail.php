<?php

namespace App\Mail;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GameChangeNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrderItem $item) {}

    public function build()
    {
        return $this->subject('Cambio de juego — ' . $this->item->game_title)
            ->view('emails.game-change')
            ->with([
                'customerName' => $this->item->order?->customer_name ?: 'cliente',
                'gameTitle'    => $this->item->game_title,
            ]);
    }
}