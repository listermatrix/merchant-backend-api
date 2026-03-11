<?php

use App\Models\Wallet;
use Illuminate\Support\Facades\DB;


if (! function_exists('safelyCreditBrijx')) {
    function safelyCreditBrijx(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        $amount = normalizeFloat($amount);

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'balance' => DB::raw("balance + $amount"),
                'credit' => DB::raw("credit + $amount"),
                'brijx_escrow'  => DB::raw("brijx_escrow - $amount"),
                'updated_at' => now(),
            ]);
    }
}


if (! function_exists('safelyDebitBrijx')) {
    function safelyDebitBrijx(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;
        
        $amount = normalizeFloat($amount);

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'balance' => DB::raw("balance - $amount"),
                'debit' => DB::raw("debit + $amount"),
                'brijx_escrow'  => DB::raw("brijx_escrow + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyCredit')) {
    function safelyCredit(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'balance' => DB::raw("balance + $amount"),
                'credit' => DB::raw("credit + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyDebit')) {
    function safelyDebit(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'balance' => DB::raw("balance - $amount"),
                'debit' => DB::raw("debit + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyCreditRnBal')) {
    function safelyCreditRnBal(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'running_bal' => DB::raw("running_bal + $amount"),
                'running_bal_credit' => DB::raw("running_bal_credit + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyDebitRnBal')) {
    function safelyDebitRnBal(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'running_bal' => DB::raw("running_bal - $amount"),
                'running_bal_debit' => DB::raw("running_bal_debit + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyDebitOverdraft')) {
    function safelyDebitOverdraft(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'overdraft_bal' => DB::raw("overdraft_bal - $amount"),
                'overdraft_bal_debit' => DB::raw("overdraft_bal_debit + $amount"),
                'updated_at' => now(),
            ]);
    }
}

if (! function_exists('safelyCreditOverdraft')) {
    function safelyCreditOverdraft(Wallet|int $wallet, float $amount): int
    {
        $id = ($wallet instanceof Wallet) ? $wallet->id : $wallet;

        return DB::table('wallets')
            ->where('id', $id)
            ->update([
                'overdraft_bal' => DB::raw("overdraft_bal + $amount"),
                'overdraft_bal_credit' => DB::raw("overdraft_bal_credit + $amount"),
                'updated_at' => now(),
            ]);
    }
}
