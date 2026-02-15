<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: ExamVoucherService.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/11/26
 * Time: 12:55 PM
 */

namespace App\Services;

use App\Models\Exam;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamVoucherService
{
    public function __construct() {}

    /**
     * @throws \Throwable
     */
    public static function generate(int $quantity, Exam $exam)
    {
        $ref = time();
        $vouchersToStore = [];

        for ($i = 0; $i < $quantity; $i++) {
            $rawPin = rand(1000, 9999).rand(1000, 9999).rand(1000, 9999);
            $serial = 'SN-'.strtoupper(Str::random(8));

            // Data for the database (Hashed)
            $vouchersToStore[] = [
                'serial' => $serial,
                'pin' => $rawPin,
                'reference' => $ref,
                'created_at' => now(),
                'updated_at' => now(),
                'exam_id' => $exam->id,
            ];
        }

        // Insert in chunks to prevent database memory issues
        DB::transaction(function () use ($vouchersToStore) {
            collect($vouchersToStore)->chunk(200)->each(function ($chunk) {
                Voucher::insert($chunk->toArray());
            });
        });

        return $ref;
    }
}
