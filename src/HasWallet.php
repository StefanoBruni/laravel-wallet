<?php

namespace StefanoBruni\Wallet;

trait HasWallet
{
    /**
     * Retrieve the balance of this user's wallet
     */
    public function getBalanceAttribute()
    {
        return $this->wallet->balance;
    }

    /**
     * Retrieve the wallet of this user
     */
    public function wallet()
    {
        return $this->hasOne(config('wallet.wallet_model', Wallet::class))->withDefault();
    }

    /**
     * Retrieve all transactions of this user
     */
    public function transactions()
    {
        return $this->hasManyThrough(config('wallet.transaction_model', Transaction::class), config('wallet.wallet_model', Wallet::class))->latest();
    }

    /**
     * Determine if the user can withdraw the given amount
     * @param  integer $amount
     * @return boolean
     */
    public function canWithdraw($amount)
    {
        return $this->balance >= $amount;
    }

    /**
     * Move credits to this account
     * @param  integer $amount
     * @param  string  $type
     * @param  array   $meta
     */
    public function deposit($amount, $type = 'deposit', $meta = [], $accepted = true)
    {
	    if ($type == "crmcredits") {

		    $this->wallet->crmcredits = $amount;
		    $this->wallet->balance = $amount + $this->actualBalance(false);
		    $this->wallet->save();
	    } 
	else if ($type == "academycredits") {
            $this->wallet->academycredits = $amount;
            $this->wallet->balance = $amount + $this->actualBalance(false) + $this->wallet->crmcredits  ;
            $this->wallet->save();
        }
	elseif ($accepted) {
            $this->wallet->balance += $amount;
            $this->wallet->save();
        } elseif (! $this->wallet->exists) {
            $this->wallet->save();
        }

        $this->wallet->transactions()
            ->create([
                'amount' => $amount,
                'hash' => uniqid('lwch_'),
                'type' => $type,
                'accepted' => $accepted,
                'meta' => $meta
            ]);
    }

    /**
     * Fail to move credits to this account
     * @param  integer $amount
     * @param  string  $type
     * @param  array   $meta
     */
    public function failDeposit($amount, $type = 'deposit', $meta = [])
    {
        $this->deposit($amount, $type, $meta, false);
    }

    /**
     * Attempt to move credits from this account
     * @param  integer $amount
     * @param  string  $type
     * @param  array   $meta
     * @param  boolean $shouldAccept
     */
    public function withdraw($amount, $type = 'withdraw', $meta = [], $shouldAccept = true)
    {
        $accepted = $shouldAccept ? $this->canWithdraw($amount) : true;

        if ($accepted) {
            $this->wallet->balance -= $amount;
            $this->wallet->save();
        } elseif (! $this->wallet->exists) {
            $this->wallet->save();
        }

        $this->wallet->transactions()
            ->create([
                'amount' => $amount,
                'hash' => uniqid('lwch_'),
                'type' => $type,
                'accepted' => $accepted,
                'meta' => $meta
            ]);
    }

    /**
     * Move credits from this account
     * @param  integer $amount
     * @param  string  $type
     * @param  array   $meta
     * @param  boolean $shouldAccept
     */
    public function forceWithdraw($amount, $type = 'withdraw', $meta = [])
    {
        return $this->withdraw($amount, $type, $meta, false);
    }

    /**
     * Returns the actual balance for this wallet.
     * Might be different from the balance property if the database is manipulated
     * @return float balance
     */
    public function actualBalance($withCrmCredits = false)
    {
	    $crmCredits = 0;

    	if($withCrmCredits)
	    {
		    $crmWallet = $this->wallet->transactions()
			    ->whereIn('type', ['crmcredits'])
			    ->where('accepted', 1)
			    ->orderBy('id','desc')->first();
		    $crmCredits = isset($crmWallet->amount) ? $crmWallet->amount : 0;
	    }

        $credits = $this->wallet->transactions()
            ->whereIn('type', ['deposit', 'refund'])
            ->where('accepted', 1)
            ->sum('amount');

        $debits = $this->wallet->transactions()
            ->whereIn('type', ['withdraw', 'payout'])
            ->where('accepted', 1)
            ->sum('amount');

        return $crmCredits + $credits - $debits;
    }
}
