<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: CloseExamHallEvent.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/15/26
 * Time: 1:45 PM
 */

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloseExamHallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct() {}

    public function broadcastOn(): Channel
    {
        return new Channel('close-exam-hall');
    }
}
