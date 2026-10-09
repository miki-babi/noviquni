<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentBotController extends Controller
{
    public function quote(Request $request, SettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'telegram_id' => ['required', 'string', 'max:64'],
        ]);

        $user = $this->findStudentByTelegramId($validated['telegram_id']);

        return response()->json([
            'user_id' => $user->id,
            'telegram_id' => (string) $user->telegram_id,
            'name' => $user->name,
            'is_premium' => $user->hasActivePremium(),
            'amount' => $settings->premiumPrice(),
            'currency' => 'ETB',
            'pitch_title' => $settings->premiumPitchTitle(),
            'pitch_body' => $settings->premiumPitchBody(),
            'payment_instructions' => $settings->paymentInstructions(),
        ]);
    }

    public function store(Request $request, PaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'telegram_id' => ['required', 'string', 'max:64'],
        ]);

        $user = $this->findStudentByTelegramId($validated['telegram_id']);

        if ($user->hasActivePremium()) {
            return response()->json([
                'message' => 'User already has active premium.',
                'is_premium' => true,
            ], 409);
        }

        $payment = $payments->createOrReusePendingPaymentBotPayment($user);

        return response()->json($this->paymentPayload($payment), $payment->wasRecentlyCreated ? 201 : 200);
    }

    public function verify(Payment $payment, PaymentService $payments): JsonResponse
    {
        abort_unless($payment->provider === Payment::PROVIDER_PAYMENT_BOT, 404);
        abort_unless(
            in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Verified], true),
            422,
            'Payment cannot be verified in its current status.',
        );

        $payment = $payments->verifyViaPaymentBot($payment);

        return response()->json($this->paymentPayload($payment));
    }

    public function reject(Payment $payment, PaymentService $payments): JsonResponse
    {
        abort_unless($payment->provider === Payment::PROVIDER_PAYMENT_BOT, 404);
        abort_unless($payment->status === PaymentStatus::Pending, 422, 'Only pending payments can be rejected.');

        $payment = $payments->rejectViaPaymentBot($payment);

        return response()->json($this->paymentPayload($payment));
    }

    protected function findStudentByTelegramId(string $telegramId): User
    {
        $user = User::query()
            ->where('telegram_id', $telegramId)
            ->first();

        abort_if($user === null, 404, 'Student not found.');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing('user');

        return [
            'id' => $payment->id,
            'user_id' => $payment->user_id,
            'telegram_id' => $payment->user?->telegram_id !== null
                ? (string) $payment->user->telegram_id
                : null,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'external_ref' => $payment->external_ref,
            'status' => $payment->status->value,
            'purpose' => $payment->purpose,
            'fulfilled_at' => $payment->fulfilled_at?->toIso8601String(),
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'is_premium' => $payment->user?->hasActivePremium() ?? false,
            'meta' => $payment->meta,
        ];
    }
}
