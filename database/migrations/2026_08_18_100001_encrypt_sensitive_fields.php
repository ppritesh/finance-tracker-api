<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('persons')->whereNotNull('phone')->cursor() as $person) {
            if ($person->phone === null || $person->phone === '') {
                continue;
            }

            if ($this->isEncrypted($person->phone)) {
                continue;
            }

            DB::table('persons')->where('id', $person->id)->update([
                'phone' => Crypt::encryptString($person->phone),
            ]);
        }

        foreach (DB::table('transactions')->cursor() as $transaction) {
            $updates = [];

            if ($transaction->note !== null && $transaction->note !== '' && ! $this->isEncrypted($transaction->note)) {
                $updates['note'] = Crypt::encryptString($transaction->note);
            }

            if ($transaction->received_note !== null && $transaction->received_note !== '' && ! $this->isEncrypted($transaction->received_note)) {
                $updates['received_note'] = Crypt::encryptString($transaction->received_note);
            }

            if ($updates !== []) {
                DB::table('transactions')->where('id', $transaction->id)->update($updates);
            }
        }

        foreach (DB::table('settlements')->whereNotNull('note')->cursor() as $settlement) {
            if ($settlement->note === null || $settlement->note === '') {
                continue;
            }

            if ($this->isEncrypted($settlement->note)) {
                continue;
            }

            DB::table('settlements')->where('id', $settlement->id)->update([
                'note' => Crypt::encryptString($settlement->note),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible without storing plaintext backups.
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
