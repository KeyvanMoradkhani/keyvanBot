<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramBotController extends Controller
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    /**
     * @throws TelegramSDKException
     */
    public function webhook(Request $request)
    {
        $update = $this->telegram->getWebhookUpdate();

        $message = $update->getMessage();

        if (isset($message['text'])){
            $text = $message['text'];
            $chatId = $message['chat']['id'] ?? null;
            $username = $message['chat']['first_name'] ?? 'بدون نام';

            if (!$chatId) return;

            $user = User::query()->where('telegram_id', $chatId)->first();


            if ($text === '/balance') {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "💰 اعتبار شما: " . $user->balance . " دلار"
                ]);
                return;
            }

            if ($text === '/start') {
                if (!$user) {
                    $user = User::query()->create([
                        'telegram_id' => $chatId,
                        'username' => $username,
                        'balance' => 1000,
                        'is_active' => true,
                    ]);

                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ خوش آمدید! شما ثبت‌نام شدید.\n💰 اعتبار اولیه شما: 1000 دلار"
                    ]);
                } else {
                    if ($user->is_active) {
                        $this->telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => "👋 خوش برگشتی! اعتبار فعلی شما: " . $user->balance . " دلار"
                        ]);
                    }else{
                        $this->telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => "اکانت شما مسدود شده است جهت رفع مسدودی حساب خود با ادمین تماس بگیرید🙏🏻."
                        ]);
                    }
                }
            }

//            if (str_starts_with($text, '/trade')) {
//                $parts = explode(' ', $text);
//                if (count($parts) < 3) {
//                    $this->telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => "❌ فرمت اشتباه است.\n✅ مثال: /trade 500 123456789"
//                    ]);
//                    return;
//                }
//
//                $amount = (float) $parts[1];
//                $toUserId = (int) $parts[2];
//
//                if ($amount <= 0) {
//                    $this->telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => "❌ مقدار معامله باید بیشتر از 0 باشد!"
//                    ]);
//                    return;
//                }
//
//                if ($user->balance < $amount) {
//                    $this->telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => "⛔ شما اعتبار کافی ندارید! موجودی شما: " . $user->balance . " دلار"
//                    ]);
//                    return;
//                }
//
//                $toUser = User::where('telegram_id', $toUserId)->first();
//                if (!$toUser) {
//                    $this->telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => "⛔ کاربر مقصد یافت نشد!"
//                    ]);
//                    return;
//                }
//
//                $transaction = Transaction::query()->create([
//                    'from_user_id' => $user->id,
//                    'to_user_id' => $toUser->id,
//                    'amount' => $amount,
//                    'status' => 1
//                ]);
//
//                $this->telegram->sendMessage([
//                    'chat_id' => $toUserId,
//                    'text' => "📢 شما یک درخواست معامله دریافت کردید:\n\n💰 مبلغ: $amount دلار\n👤 از طرف: $username\n\n🔹 برای قبول معامله:\n/accept " . $transaction->id . "\n\n🔻 برای رد کردن معامله:\n/reject " . $transaction->id
//                ]);
//
//                $this->telegram->sendMessage([
//                    'chat_id' => $chatId,
//                    'text' => "✅ درخواست معامله شما برای کاربر ارسال شد."
//                ]);
//            }


            if (str_starts_with($text, '/trade')) {
                $parts = explode(' ', $text);
                if (count($parts) < 4) {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "❌ فرمت اشتباه است.\n✅ مثال: `/trade 500 123456789 buy`\n(نوع معامله: buy = خرید | sell = فروش)",
                        'parse_mode' => 'Markdown'
                    ]);
                    return;
                }

                $amount = (float) $parts[1];
                $toUserId = (int) $parts[2];
                $tradeType = strtolower($parts[3]); // تعیین نوع معامله (buy یا sell)

                if (!in_array($tradeType, ['buy', 'sell'])) {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⛔ نوع معامله نامعتبر است! باید `buy` یا `sell` باشد.",
                        'parse_mode' => 'Markdown'
                    ]);
                    return;
                }

                if ($amount <= 0) {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "❌ مقدار معامله باید بیشتر از 0 باشد!"
                    ]);
                    return;
                }

                $toUser = User::where('telegram_id', $toUserId)->first();
                if (!$toUser) {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⛔ کاربر مقصد یافت نشد!"
                    ]);
                    return;
                }

                // تعیین نقش فروشنده و خریدار
                if ($tradeType === 'buy') {
                    $buyer = $user;
                    $seller = $toUser;
                } else {
                    $buyer = $toUser;
                    $seller = $user;
                }

                if ($seller->balance < $amount) {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "⛔ فروشنده اعتبار کافی ندارد! موجودی فعلی فروشنده: " . $seller->balance . " دلار"
                    ]);
                    return;
                }

                // ایجاد رکورد معامله
                $transaction = Transaction::query()->create([
                    'from_user_id' => $seller->id,
                    'to_user_id' => $buyer->id,
                    'amount' => $amount,
                    'status' => 1 // وضعیت اولیه
                ]);

                // ارسال پیام به طرف مقابل برای تأیید معامله
                $this->telegram->sendMessage([
                    'chat_id' => $toUserId,
                    'text' => "📢 شما یک درخواست معامله دریافت کردید:\n\n💰 مبلغ: *$amount دلار*\n👤 از طرف: @$user->telegram_id\n📌 نوع معامله: *" . strtoupper($tradeType) . "*\n\n🔹 برای قبول معامله:\n/accept " . $transaction->id . "\n🔻 برای رد کردن معامله:\n/reject " . $transaction->id,
                    'parse_mode' => 'Markdown'
                ]);

                // ارسال تأییدیه به درخواست‌دهنده
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✅ درخواست معامله شما برای کاربر @$toUser->telegram_id ارسال شد."
                ]);
            }


            if (str_starts_with($text, '/accept') || str_starts_with($text, '/reject')) {
                $this->handleTradeResponse($text, $user, $chatId);
                return;
            }

            if ($text === '/transactions') {
                $this->showTransactions($user, $chatId);
                return;
            }

        }
    }


//    public function handleTradeResponse($text, $user, $chatId)
//    {
//        $parts = explode(' ', $text);
//        if (count($parts) < 2 || !is_numeric($parts[1])) {
//            $this->telegram->sendMessage([
//                'chat_id' => $chatId,
//                'text' => "❌ فرمت اشتباه است. لطفاً به صورت `/accept 123` یا `/reject 123` وارد کنید."
//            ]);
//            return;
//        }
//
//        $transactionId = (int) $parts[1];
//
//        $transaction = Transaction::query()->where('id', $transactionId)
//            ->where('to_user_id', $user->id)
//            ->where('status', 1)
//            ->first();
//
//        if (!$transaction) {
//            $this->telegram->sendMessage([
//                'chat_id' => $chatId,
//                'text' => "❌ معامله‌ای با این شناسه یافت نشد یا قبلاً پردازش شده است."
//            ]);
//            return;
//        }
//
//        if (str_starts_with($text, '/accept')) {
//            $transaction->status = 2;
//            $transaction->save();
//
//            $fromUser = $transaction->sender;
//            $toUser = $transaction->receiver;
//
//            $fromUser->balance -= $transaction->amount;
//            $toUser->balance += $transaction->amount;
//
//            $fromUser->save();
//            $toUser->save();
//
//            $this->telegram->sendMessage([
//                'chat_id' => $fromUser->telegram_id,
//                'text' => "✅ معامله شما با کاربر @$toUser->telegram_id تایید شد!\n💰 مقدار: {$transaction->amount} دلار"
//            ]);
//
//            $this->telegram->sendMessage([
//                'chat_id' => $toUser->telegram_id,
//                'text' => "✅ شما معامله را تایید کردید.\n💰 مقدار: {$transaction->amount} دلار"
//            ]);
//
//        } elseif (str_starts_with($text, '/reject')) {
//            $transaction->status = 3;
//            $transaction->save();
//
//            $this->telegram->sendMessage([
//                'chat_id' => $transaction->sender->telegram_id,
//                'text' => "❌ معامله شما با کاربر @$transaction->receiver->telegram_id رد شد!"
//            ]);
//
//            $this->telegram->sendMessage([
//                'chat_id' => $transaction->receiver->telegram_id,
//                'text' => "❌ شما معامله را رد کردید."
//            ]);
//        }
//    }
    public function handleTradeResponse($text, $user, $chatId)
    {
        if (str_starts_with($text, '/accept')) {
            $parts = explode(' ', $text);
            if (count($parts) < 2) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❌ فرمت اشتباه است.\n✅ مثال: `/accept 2`",
                    'parse_mode' => 'Markdown'
                ]);
                return;
            }

            $transactionId = (int) $parts[1];
            $transaction = Transaction::find($transactionId);

            if (!$transaction || $transaction->status !== 1) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ معامله نامعتبر یا قبلاً پردازش شده است!"
                ]);
                return;
            }

            // دریافت اطلاعات خریدار و فروشنده
            $buyer = User::find($transaction->to_user_id);
            $seller = User::find($transaction->from_user_id);

            if (!$buyer || !$seller) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ کاربران مربوط به این معامله وجود ندارند!"
                ]);
                return;
            }

            // بررسی اینکه کاربر فعلی گیرنده معامله است
            if ($chatId != $buyer->telegram_id) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ شما مجاز به تأیید این معامله نیستید!"
                ]);
                return;
            }

            // بررسی اعتبار فروشنده
            if ($seller->balance < $transaction->amount) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ فروشنده اعتبار کافی ندارد! معامله لغو شد."
                ]);

                // تغییر وضعیت معامله به canceled
                $transaction->update(['status' => 3]);
                return;
            }

            // کاهش اعتبار فروشنده و افزایش اعتبار خریدار
            $seller->decrement('balance', $transaction->amount);
            $buyer->increment('balance', $transaction->amount);

            // تغییر وضعیت معامله به completed
            $transaction->update(['status' => 2]);

            // ارسال پیام به خریدار و فروشنده
            $this->telegram->sendMessage([
                'chat_id' => $buyer->telegram_id,
                'text' => "✅ معامله شما با @$seller->telegram_id تکمیل شد!\n💰 مبلغ: *{$transaction->amount} دلار*",
                'parse_mode' => 'Markdown'
            ]);

            $this->telegram->sendMessage([
                'chat_id' => $seller->telegram_id,
                'text' => "✅ معامله شما با @$buyer->telegram_id تکمیل شد!\n💰 مبلغ: *{$transaction->amount} دلار*",
                'parse_mode' => 'Markdown'
            ]);
        }

        if (str_starts_with($text, '/reject')) {
            $parts = explode(' ', $text);
            if (count($parts) < 2) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❌ فرمت اشتباه است.\n✅ مثال: `/reject 2`",
                    'parse_mode' => 'Markdown'
                ]);
                return;
            }

            $transactionId = (int) $parts[1];
            $transaction = Transaction::find($transactionId);

            if (!$transaction || $transaction->status !== 1) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ معامله نامعتبر یا قبلاً پردازش شده است!"
                ]);
                return;
            }

            // دریافت اطلاعات کاربران
            $buyer = User::find($transaction->to_user_id);
            $seller = User::find($transaction->from_user_id);

            if (!$buyer || !$seller) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ کاربران مربوط به این معامله وجود ندارند!"
                ]);
                return;
            }

            // بررسی اینکه کاربر فعلی گیرنده معامله است
            if ($chatId != $buyer->telegram_id) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ شما مجاز به رد کردن این معامله نیستید!"
                ]);
                return;
            }

            // تغییر وضعیت معامله به rejected
            $transaction->update(['status' => 3]);

            // ارسال پیام به دو طرف
            $this->telegram->sendMessage([
                'chat_id' => $buyer->telegram_id,
                'text' => "❌ شما معامله با @$seller->telegram_id را رد کردید!"
            ]);

            $this->telegram->sendMessage([
                'chat_id' => $seller->telegram_id,
                'text' => "❌ @$buyer->telegram_id معامله را رد کرد."
            ]);
        }

    }


    public function showTransactions($user, $chatId)
    {
        $transactions = Transaction::where('from_user_id', $user->id)
            ->orWhere('to_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "📜 شما هیچ معامله‌ای ندارید!"
            ]);
            return;
        }

        $message = "📊 **آخرین 10 معامله شما:**\n\n";
        foreach ($transactions as $t) {
            $status = match ($t->status) {
                1 => '⏳ در انتظار تایید',
                2 => '✅ تایید شده',
                3 => '❌ رد شده',
            };

            $from = $t->sender->telegram_id == $user->telegram_id ? "👤 شما" : "👤 " . $t->sender->telegram_id;
            $to = $t->receiver->telegram_id == $user->telegram_id ? "👤 شما" : "👤 " . $t->receiver->telegram_id;

            $message .= "🔹 از **$from** به **$to**\n💰 مقدار: *{$t->amount} دلار*\n📌 وضعیت: $status\n\n";
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);
    }


}
