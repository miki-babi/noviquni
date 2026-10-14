<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudyProgressReport;
use App\Support\TelegramCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __invoke(StudyProgressReport $report): View
    {
        /** @var User $user */
        $user = Auth::user();
        $copy = TelegramCopy::for($user);

        return view('telegram.mini-app.progress', [
            'copy' => $copy,
            'user' => $user,
            'report' => $report->forUser($user),
            'activeNav' => 'profile',
            'title' => 'Study progress',
            'backUrl' => route('tg.profile.edit'),
        ]);
    }
}
